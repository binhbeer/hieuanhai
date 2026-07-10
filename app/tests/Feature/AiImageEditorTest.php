<?php

namespace Tests\Feature;

use App\Ai\ImageReviewAgent;
use App\Ai\PromptRewriteAgent;
use App\Models\AiImage;
use App\Models\AiTag;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiImageEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiImageEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_visit_gallery_but_not_create_images(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('Create image'))
            ->assertSee(__('Favorite images'))
            ->assertDontSee(__('Remaining'));
    }

    public function test_gallery_loads_more_images(): void
    {
        foreach (range(1, 37) as $i) {
            $this->publishedImage('Gallery image '.$i, now()->subMinutes($i));
        }

        Livewire::test('pages::gallery')
            ->assertSee('Gallery image 1')
            ->assertSee(__('Load more images'))
            ->assertDontSee('Gallery image 37')
            ->call('loadMore')
            ->assertSee('Gallery image 37')
            ->assertDontSee(__('Load more images'));
    }

    public function test_guest_cannot_create_pending_image(): void
    {
        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $this->assertSame(0, $editor->remainingToday($request));
        $this->assertTrue($editor->isLimitExceeded($request));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vui lòng đăng nhập để tạo ảnh.');

        $editor->createPending($request, [], 'Prompt');
    }

    public function test_non_admin_users_are_limited_to_five_images_per_day(): void
    {
        $user = User::factory()->create(['id' => 2]);
        $this->actingAs($user);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        foreach (range(1, 5) as $i) {
            AiImage::create([
                'user_id' => $user->id,
                'visitor_key' => $editor->visitorKey($request),
                'prompt' => 'Prompt '.$i,
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => 'succeeded',
            ]);
        }

        $this->assertSame(0, $editor->remainingToday($request));
        $this->assertTrue($editor->isLimitExceeded($request));
    }

    public function test_unverified_users_keep_daily_quota_only_on_registration_day(): void
    {
        $user = User::factory()->unverified()->create(['id' => 2, 'created_at' => now()->subDay()]);
        $this->actingAs($user);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $this->assertTrue($editor->requiresEmailVerificationForImageCreation());
        $this->assertSame(0, $editor->remainingToday($request));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vui lòng xác minh email để tiếp tục nhận lượt tạo ảnh hằng ngày sau ngày đăng ký đầu tiên.');

        $editor->createPending($request, [], 'Ảnh sau ngày đăng ký');
    }

    public function test_unverified_users_can_use_daily_quota_on_registration_day(): void
    {
        $user = User::factory()->unverified()->create(['id' => 200]);
        $this->actingAs($user);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $this->assertFalse($editor->requiresEmailVerificationForImageCreation());
        $this->assertSame(5, $editor->remainingToday($request));
    }

    public function test_user_cannot_create_pending_image_while_another_is_pending(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        AiImage::create([
            'user_id' => $user->id,
            'visitor_key' => $editor->visitorKey($request),
            'prompt' => 'Đang tạo',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bạn đang có ảnh đang tạo. Vui lòng chờ ảnh hiện tại hoàn tất.');

        $editor->createPending($request, [], 'Ảnh khác');
    }

    public function test_user_id_one_has_no_daily_image_limit(): void
    {
        $this->actingAs(User::factory()->unverified()->create(['id' => 1, 'created_at' => now()->subDay()]));

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        foreach (range(1, 10) as $i) {
            AiImage::create([
                'user_id' => 1,
                'visitor_key' => $editor->visitorKey($request),
                'prompt' => 'Prompt '.$i,
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => 'succeeded',
            ]);
        }

        $this->assertNull($editor->remainingToday($request));
        $this->assertFalse($editor->isLimitExceeded($request));
    }

    public function test_image_creation_uses_provider_generation_endpoint_with_reference_image(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_reference_field', 'input_image');
        Setting::putValue('ai.image_size', '1024x1024');
        Setting::putValue('ai.image_quality', 'hd');
        Setting::putValue('ai.image_detail', 'low');
        ImageReviewAgent::fake([$this->allowedReview()]);

        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);

        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $photo = UploadedFile::fake()->image('source.png', 2000, 1000);

        $image = app(AiImageEditor::class)->create(
            $request,
            [$photo],
            'A cute cat wearing a hat',
        );

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'http://42.112.31.227:22150/v1/images/generations'
            && $request['model'] === 'cx/gpt-5.5-image'
            && str_contains($request['prompt'], 'Edit that image according to the instructions')
            && str_contains($request['prompt'], 'A cute cat wearing a hat')
            && str_starts_with($request['input_image'], 'data:image/jpeg;base64,')
            && $this->encodedImageSizeIs($request['input_image'], 1024, 512)
            && $request['size'] === '1024x1024'
            && $request['quality'] === 'hd'
            && $request['image_detail'] === 'low'
            && $request['output_format'] === 'png');

        Storage::disk('public')->assertExists($image->result_path);
        $this->assertMatchesRegularExpression('#^ai-images/\d{6}/\d{2}/#', (string) $image->result_path);
        $this->assertMatchesRegularExpression('#^ai-image-sources/\d{6}/\d{2}/#', (string) $image->source_path);
        $this->assertSame('succeeded', $image->status);
    }

    public function test_image_creation_sends_multiple_reference_images(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([$this->allowedReview()]);

        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);

        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $photos = [
            UploadedFile::fake()->image('one.png'),
            UploadedFile::fake()->image('two.png'),
            UploadedFile::fake()->image('three.png'),
        ];

        $image = app(AiImageEditor::class)->create($request, $photos, 'Merge these references');

        Http::assertSent(fn (HttpRequest $request) => is_array($request['images'])
            && count($request['images']) === 3
            && ! isset($request['image'])
            && str_starts_with($request['images'][0], 'data:image/jpeg;base64,')
            && str_starts_with($request['images'][2], 'data:image/jpeg;base64,'));

        $this->assertSame('succeeded', $image->status);
        $this->assertCount(3, $image->response_meta['source_paths']);
    }

    public function test_pending_image_can_be_completed_later(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_reference_field', 'input_image');
        ImageReviewAgent::fake([$this->allowedReview()]);

        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);

        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $this->actingAs(User::factory()->create());

        $image = app(AiImageEditor::class)->createPending(
            $request,
            [UploadedFile::fake()->image('source.png', 2000, 1000)],
            'A cute cat wearing a hat',
        );

        $pendingPath = data_get($image->request_meta, 'pending_uploads.0.path');
        $this->assertSame('pending', $image->status);
        $this->assertIsString($pendingPath);
        Storage::disk('public')->assertExists($pendingPath);

        $image = app(AiImageEditor::class)->completePending($image);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'http://42.112.31.227:22150/v1/images/generations'
            && str_contains($request['prompt'], 'Edit that image according to the instructions')
            && str_contains($request['input_image'], 'data:image/jpeg;base64,'));
        $this->assertSame('succeeded', $image->status);
        Storage::disk('public')->assertExists($image->result_path);
        Storage::disk('public')->assertMissing($pendingPath);
    }

    public function test_child_generation_uses_parent_and_edit_prompts(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([$this->allowedReview()]);
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);

        $source = UploadedFile::fake()->image('source.png');
        $sourcePath = 'ai-image-sources/202607/09/source.jpg';
        Storage::disk('public')->put($sourcePath, file_get_contents($source->getRealPath()));
        $user = User::factory()->create();
        $this->actingAs($user);
        $parent = AiImage::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Original parent prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'response_meta' => ['source_paths' => [$sourcePath]],
        ]);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $child = app(AiImageEditor::class)->createPending(
            $request,
            [],
            'Make the lighting warmer',
            parentId: $parent->id,
            parentReferenceIndexes: [0],
        );
        $child = app(AiImageEditor::class)->completePending($child);

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request['prompt'], 'Original prompt: Original parent prompt')
            && str_contains($request['prompt'], 'Edit instructions: Make the lighting warmer'));
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame('Make the lighting warmer', $child->prompt);
        $this->assertSame('succeeded', $child->status);
    }

    public function test_rejected_prompt_does_not_create_image_or_call_generation_api(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => false, 'blocked_policy' => 'political', 'reason' => 'Không phù hợp.']]);
        Http::fake();

        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt không phù hợp để tạo hoặc publish ảnh.');

        try {
            app(AiImageEditor::class)->create($request, [], 'Tạo ảnh chính trị cực đoan');
        } finally {
            $this->assertSame(0, AiImage::query()->count());
            Http::assertNothingSent();
        }
    }

    public function test_non_blocked_false_review_policy_still_creates_image(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => false, 'blocked_policy' => 'none', 'reason' => 'An toàn.']]);
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);

        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $image = app(AiImageEditor::class)->create($request, [], 'Tạo ảnh comic bất kì');

        $this->assertSame('succeeded', $image->status);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'http://42.112.31.227:22150/v1/images/generations');
    }

    public function test_publish_sets_category_and_category_route_filters_gallery(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_review_model', 'gpt-5.5-mini');
        $category = Category::create(['name' => 'Meme nội bộ', 'slug' => 'internal-meme', 'sort_order' => 5, 'status' => 'active']);
        AiTag::create(['name' => 'Nước hoa', 'slug' => 'nuoc-hoa']);
        ImageReviewAgent::fake([$this->publishReview('internal-meme', ['Nước hoa', 'banner ads', 'sản phẩm'])]);

        $agent = new ImageReviewAgent;
        $this->assertStringContainsString('- internal-meme: Meme nội bộ', $agent->instructions());
        $this->assertStringContainsString('tạo title tiếng Việt ngắn', $agent->instructions());
        $this->assertStringContainsString('- Nước hoa', $agent->instructions());
        $this->assertStringContainsString('tạo 0-5 tags ngắn', $agent->instructions());
        $this->assertStringContainsString('Mặc định allowed=true và blocked_policy=none', $agent->instructions());
        $this->assertStringContainsString('blocked_policy=sexual', $agent->instructions());
        $this->assertStringContainsString('blocked_policy=political', $agent->instructions());
        $this->assertStringContainsString('Không từ chối vì thương hiệu, logo, người nổi tiếng, nhân vật bản quyền, deepfake', $agent->instructions());
        $this->assertStringContainsString('mô phỏng giao diện hồ sơ mạng xã hội', $agent->instructions());

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $image = AiImage::create([
            'visitor_key' => $editor->visitorKey($request),
            'prompt' => 'Tạo ads banner cho sản phẩm nước hoa',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/result.png',
        ]);

        $published = $editor->publish($image, $request);

        ImageReviewAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'gpt-5.5-mini'
            && $prompt->provider->name() === 'openai'
            && $prompt->provider->driver() === 'openrouter'
            && $prompt->provider->providerCredentials()['key'] === 'test-key'
            && $prompt->provider->additionalConfiguration()['url'] === 'http://42.112.31.227:22150/v1');

        $other = AiImage::create([
            'visitor_key' => $editor->visitorKey($request),
            'prompt' => 'Vẽ chân dung cổ điển',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/other.png',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertTrue($published->is_published);
        $this->assertSame('Banner nước hoa cao cấp', $published->title);
        $this->assertTrue($published->category->is($category));
        $this->assertSame(['banner-ads', 'nuoc-hoa', 'san-pham'], $published->tags->pluck('slug')->sort()->values()->all());
        $this->assertSame('Nước hoa', AiTag::query()->where('slug', 'nuoc-hoa')->value('name'));
        $this->assertSame(3, AiTag::query()->count());
        $this->assertTrue($editor->publishedGallery($category)->contains($published));
        $this->assertTrue($editor->publishedGallery(search: 'nước hoa')->contains($published));
        $this->assertFalse($editor->publishedGallery(search: 'nước hoa')->contains($other));

        $this->get(route('categories.show', $category))
            ->assertOk()
            ->assertSee('Meme nội bộ')
            ->assertSee('/thumb_x720x/storage/ai-images/202607/08/result.png')
            ->assertSee('Banner nước hoa cao cấp')
            ->assertSee("title: 'Banner nước hoa cao cấp'", false);

        $this->actingAs(User::factory()->create())->get(route('search.index', ['q' => 'nước hoa']))
            ->assertOk()
            ->assertSee('Banner nước hoa cao cấp')
            ->assertDontSee('Vẽ chân dung cổ điển');

        $this->get(route('images.show', $published))
            ->assertOk()
            ->assertSee('/anh/'.$published->id.'-banner-nuoc-hoa-cao-cap')
            ->assertSee('/thumb_x1024x/storage/ai-images/202607/08/result.png')
            ->assertSee('Banner nước hoa cao cấp')
            ->assertSee('Sao chép prompt')
            ->assertSee('Tạo ảnh tương tự')
            ->assertSee('Yêu thích')
            ->assertSee('0')
            ->assertSee('Đóng')
            ->assertSee('og:image');
    }

    public function test_guest_image_detail_truncates_prompt_and_login_sees_full_prompt(): void
    {
        $prompt = str_repeat('mô tả dài ', 30).'phần cuối bí mật';
        $image = $this->publishedImage($prompt);
        $image->update(['title' => 'Ảnh prompt dài']);

        $this->get(route('images.show', $image))
            ->assertOk()
            ->assertSee('/anh/'.$image->id.'-anh-prompt-dai')
            ->assertSee('Đăng nhập để xem đầy đủ prompt.')
            ->assertDontSee('phần cuối bí mật');

        $this->get('/anh/'.$image->id)->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('images.show', $image))
            ->assertOk()
            ->assertSee('phần cuối bí mật');
    }

    public function test_publish_allows_missing_review_tags(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([$this->publishReview('other', [])]);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $image = AiImage::create([
            'visitor_key' => $editor->visitorKey($request),
            'prompt' => 'Tạo ảnh comic bất kì',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/result.png',
        ]);

        $published = $editor->publish($image, $request);

        $this->assertTrue($published->is_published);
        $this->assertSame([], $published->tags->pluck('slug')->sort()->values()->all());
    }

    public function test_publish_reviews_settings_provider_via_chat_completions_sdk_driver(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_review_model', 'tss-2');
        $category = Category::create(['name' => 'Meme nội bộ', 'slug' => 'internal-meme', 'sort_order' => 5, 'status' => 'active']);
        Http::fake([
            '42.112.31.227:22150/v1/chat/completions' => Http::response([
                'model' => 'tss-2',
                'choices' => [[
                    'message' => [
                        'content' => json_encode($this->publishReview('internal-meme')),
                    ],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $image = AiImage::create([
            'visitor_key' => $editor->visitorKey($request),
            'prompt' => 'Tạo ads banner cho sản phẩm nước hoa',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/result.png',
        ]);

        $published = $editor->publish($image, $request);

        $this->assertTrue($published->is_published);
        $this->assertTrue($published->category->is($category));
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://42.112.31.227:22150/v1/chat/completions'
            && $request['model'] === 'tss-2'
            && ($request['response_format']['type'] ?? null) === 'json_schema');
    }

    public function test_prompt_rewrite_uses_separate_agent_and_model_setting(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.prompt_rewrite_model', 'gpt-5.5-rewrite');
        PromptRewriteAgent::fake([['prompt' => 'A cinematic portrait of a cat wearing a tiny hat.']]);

        $prompt = app(AiImageEditor::class)->rewritePrompt('cat with hat', 'make it cinematic');

        $this->assertSame('A cinematic portrait of a cat wearing a tiny hat.', $prompt);
        PromptRewriteAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'gpt-5.5-rewrite'
            && str_contains($prompt->prompt, 'cat with hat')
            && str_contains($prompt->prompt, 'make it cinematic'));
    }

    public function test_rejected_prompt_does_not_publish_image(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => false, 'blocked_policy' => 'political', 'reason' => 'Không phù hợp.']]);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $image = AiImage::create([
            'visitor_key' => $editor->visitorKey($request),
            'prompt' => 'Tạo ảnh bôi xấu lãnh tụ',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/result.png',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt không phù hợp để tạo hoặc publish ảnh.');

        try {
            $editor->publish($image, $request);
        } finally {
            $this->assertFalse($image->fresh()->is_published);
        }
    }

    public function test_related_images_are_ranked_by_shared_tags(): void
    {
        $editor = app(AiImageEditor::class);
        $selected = $this->publishedImage('Ảnh nước hoa', now()->subMinutes(4));
        $best = $this->publishedImage('Ảnh nước hoa studio', now()->subMinutes(10));
        $ok = $this->publishedImage('Ảnh sản phẩm', now()->subMinutes(2));
        $other = $this->publishedImage('Ảnh chân dung', now());

        $this->syncImageTags($selected, ['nước hoa', 'banner ads', 'sản phẩm']);
        $this->syncImageTags($best, ['nước hoa', 'banner ads', 'studio']);
        $this->syncImageTags($ok, ['nước hoa', 'chân dung', 'ngoài trời']);
        $this->syncImageTags($other, ['chân dung', 'avatar', 'comic']);

        $related = $editor->relatedPublished($selected, 6);

        $this->assertSame([$best->id, $ok->id], $related->pluck('id')->all());
        $this->assertFalse($related->contains($other));
    }

    public function test_admin_can_toggle_featured_image_from_detail_and_featured_sort_uses_it(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $featured = $this->publishedImage('Ảnh nên nổi bật', now()->subDay());
        $newer = $this->publishedImage('Ảnh mới hơn', now());

        Livewire::actingAs($admin)
            ->test('image-detail')
            ->call('openImage', $featured->id)
            ->assertSee(__('Feature image'))
            ->call('toggleFeatured', $featured->id)
            ->assertSee(__('Unfeature image'));

        $this->assertTrue($featured->fresh()->is_featured);
        $this->assertSame([$featured->id, $newer->id], app(AiImageEditor::class)->publishedGallery(sort: 'featured')->pluck('id')->take(2)->all());

        Livewire::actingAs(User::factory()->create())
            ->test('image-detail')
            ->call('openImage', $featured->id)
            ->assertDontSee(__('Unfeature image'))
            ->call('toggleFeatured', $featured->id);

        $this->assertTrue($featured->fresh()->is_featured);
    }

    private function publishedImage(string $prompt, ?\DateTimeInterface $publishedAt = null): AiImage
    {
        return AiImage::create([
            'visitor_key' => 'visitor-'.str()->random(8),
            'prompt' => $prompt,
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/'.str()->uuid().'.png',
            'is_published' => true,
            'published_at' => $publishedAt ?? now(),
        ]);
    }

    /**
     * @param  list<string>  $tags
     */
    private function syncImageTags(AiImage $image, array $tags): void
    {
        $image->tags()->sync(collect($tags)->map(fn (string $tag): int => (int) AiTag::query()->firstOrCreate([
            'slug' => Str::slug($tag, '-'),
        ], [
            'name' => $tag,
        ])->id));
    }

    /**
     * @return array{allowed: bool, blocked_policy: string, reason: string}
     */
    private function allowedReview(): array
    {
        return ['allowed' => true, 'blocked_policy' => 'none', 'reason' => 'An toàn.'];
    }

    /**
     * @param  list<string>  $tags
     * @return array{allowed: bool, blocked_policy: string, title: string, category: string, tags: list<string>, reason: string}
     */
    private function publishReview(string $category, array $tags = ['nước hoa', 'banner ads', 'sản phẩm'], string $title = 'Banner nước hoa cao cấp'): array
    {
        return ['allowed' => true, 'blocked_policy' => 'none', 'title' => $title, 'category' => $category, 'tags' => $tags, 'reason' => 'An toàn.'];
    }

    private function encodedImageSizeIs(string $image, int $width, int $height): bool
    {
        $content = base64_decode(substr($image, strlen('data:image/jpeg;base64,')), true);
        $size = is_string($content) ? getimagesizefromstring($content) : false;

        return is_array($size) && $size[0] === $width && $size[1] === $height;
    }
}
