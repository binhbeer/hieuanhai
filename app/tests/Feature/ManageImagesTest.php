<?php

namespace Tests\Feature;

use App\Ai\ImageReviewAgent;
use App\Models\AiImage;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_and_publish_images(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => true, 'category' => 'portraits', 'tags' => ['chân dung', 'studio', 'avatar'], 'reason' => 'An toàn.']]);

        $this->actingAs(User::factory()->create(['id' => 1]));
        $category = Category::query()->where('slug', 'ads-product')->firstOrFail();
        $published = AiImage::create([
            'visitor_key' => 'visitor-a',
            'prompt' => 'Published product ad',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/published.png',
            'category_id' => $category->id,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $unpublished = AiImage::create([
            'visitor_key' => 'visitor-b',
            'prompt' => 'Unpublished portrait',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/unpublished.png',
        ]);

        $this->get(route('manage.images.index'))
            ->assertOk()
            ->assertSee('Quản lý ảnh đã tạo')
            ->assertSee('Published product ad')
            ->assertSee('Unpublished portrait');

        Livewire::test('pages::manage.images')
            ->set('publish', 'published')
            ->assertSee('Published product ad')
            ->assertDontSee('Unpublished portrait')
            ->call('unpublishImage', $published->id)
            ->set('publish', 'unpublished')
            ->assertSee('Published product ad')
            ->assertSee('Unpublished portrait')
            ->call('publishImage', $unpublished->id);

        $this->assertFalse($published->fresh()->is_published);
        $this->assertTrue($unpublished->fresh()->is_published);
    }
}
