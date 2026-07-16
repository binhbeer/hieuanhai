<?php

namespace Tests\Feature;

use App\Ai\ImageMetadataAgent;
use App\Ai\ImageReviewAgent;
use App\Ai\TagDescriptionAgent;
use App\Jobs\GenerateTagDescription;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Services\AiImageEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TagSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_page_uses_branded_title_and_saved_description(): void
    {
        $description = 'Bộ sưu tập brownie giới thiệu nhiều mẫu bánh ngọt với lớp vỏ nướng hấp dẫn, kết cấu mềm ẩm và cách trình bày đa dạng, phù hợp để tìm ý tưởng hình ảnh.';
        Setting::putValue('site.name', 'Gen Ảnh');
        $tag = Tag::create(['name' => 'brownie', 'slug' => 'brownie', 'description' => $description]);
        $tag->media()->attach($this->publishedImage('Bánh brownie socola'));

        $response = $this->get(route('tags.show', $tag));

        $response->assertOk()->assertSee('<meta name="description" content="'.$description.'">', false);
        $this->assertMatchesRegularExpression('/<title>\s*#brownie - Gen Ảnh\s*<\/title>/u', $response->getContent());
        $response->assertDontSee('#brownie - GenAnh');
    }

    public function test_tag_description_job_writes_human_seo_copy(): void
    {
        $description = 'Bộ sưu tập brownie giới thiệu nhiều mẫu bánh ngọt với lớp vỏ nướng hấp dẫn, kết cấu mềm ẩm và cách trình bày đa dạng, phù hợp để tìm ý tưởng hình ảnh.';
        Setting::putValue('ai.openai_api_key', 'test-key');
        TagDescriptionAgent::fake([['description' => $description]]);
        $tag = Tag::create(['name' => 'brownie', 'slug' => 'brownie']);
        $tag->media()->attach($this->publishedImage('Brownie socola mềm ẩm'));

        (new GenerateTagDescription($tag->id))->handle();

        $this->assertSame($description, $tag->fresh()->description);
        TagDescriptionAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Tên tag: brownie')
            && str_contains($prompt->prompt, 'Brownie socola mềm ẩm'));
    }

    public function test_tag_description_job_accepts_short_non_empty_copy(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        TagDescriptionAgent::fake([['description' => 'Mô tả ngắn nhưng hợp lệ.']]);
        $tag = Tag::create(['name' => 'brownie', 'slug' => 'brownie']);

        (new GenerateTagDescription($tag->id))->handle();

        $this->assertSame('Mô tả ngắn nhưng hợp lệ.', $tag->fresh()->description);
    }

    public function test_publishing_queues_descriptions_only_for_new_tags(): void
    {
        Bus::fake([GenerateTagDescription::class]);
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => true, 'blocked_policy' => 'none', 'reason' => 'An toàn.']]);
        ImageMetadataAgent::fake([[
            'title' => 'Brownie socola mềm ẩm',
            'description' => 'Brownie socola mềm ẩm với mặt bánh nứt nhẹ, lớp ruột đậm vị và cách trình bày gần gũi trong ánh sáng tự nhiên.',
            'category' => 'other',
            'tags' => ['brownie', 'socola', 'bánh ngọt', 'ẩm thực'],
        ]]);
        $existingTag = Tag::create(['name' => 'brownie', 'slug' => 'brownie']);
        $user = User::factory()->create();
        $this->actingAs($user);
        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'tag-seo-user',
            'prompt' => 'Brownie socola mềm ẩm',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/brownie.png',
        ]);

        app(AiImageEditor::class)->publish($image, Request::create('/', 'POST'));

        Bus::assertNotDispatched(GenerateTagDescription::class, fn (GenerateTagDescription $job): bool => $job->tagId === $existingTag->id);
        Bus::assertDispatchedTimes(GenerateTagDescription::class, 3);
    }

    public function test_backfill_command_queues_only_public_missing_descriptions(): void
    {
        Bus::fake([GenerateTagDescription::class]);
        $missing = Tag::create(['name' => 'brownie', 'slug' => 'brownie']);
        $missing->media()->attach($this->publishedImage('Brownie công khai'));
        $described = Tag::create(['name' => 'socola', 'slug' => 'socola', 'description' => 'Mô tả đã có.']);
        $described->media()->attach($this->publishedImage('Socola công khai'));
        $private = Tag::create(['name' => 'bí mật', 'slug' => 'bi-mat']);
        $private->media()->attach($this->publishedImage('Ảnh riêng tư', false));

        $this->artisan('tags:backfill-descriptions')
            ->expectsOutput('Queued 1 tag descriptions.')
            ->assertSuccessful();

        Bus::assertDispatched(GenerateTagDescription::class, fn (GenerateTagDescription $job): bool => $job->tagId === $missing->id);
        Bus::assertDispatchedTimes(GenerateTagDescription::class, 1);
    }

    private function publishedImage(string $title, bool $published = true): GeneratedMedia
    {
        return GeneratedMedia::create([
            'visitor_key' => 'tag-seo-'.str()->random(12),
            'title' => $title,
            'prompt' => $title,
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/'.str()->random(12).'.png',
            'is_published' => $published,
            'published_at' => $published ? now() : null,
        ]);
    }
}
