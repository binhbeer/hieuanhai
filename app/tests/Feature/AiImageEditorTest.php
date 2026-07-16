<?php

namespace Tests\Feature;

use App\Ai\ImageMetadataAgent;
use App\Ai\ImageReviewAgent;
use App\Ai\ImageToPromptAgent;
use App\Ai\PromptRewriteAgent;
use App\Ai\PromptTranslationAgent;
use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Services\AiImageEditor;
use App\Support\GptImageOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Base64Image;
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

    public function test_category_can_be_filtered_by_tag_without_changing_canonical(): void
    {
        $category = Category::create(['name' => 'Sản phẩm', 'slug' => 'san-pham', 'sort_order' => 1, 'status' => 'active']);
        $otherCategory = Category::create(['name' => 'Chân dung', 'slug' => 'chan-dung', 'sort_order' => 2, 'status' => 'active']);
        $tag = Tag::create(['name' => '3D', 'slug' => '3d']);
        $matching = $this->publishedImage('Matching category and tag');
        $wrongTag = $this->publishedImage('Same category without tag');
        $wrongCategory = $this->publishedImage('Same tag in another category');

        $matching->update(['category_id' => $category->id]);
        $matching->tags()->sync([$tag->id]);
        $wrongTag->update(['category_id' => $category->id]);
        $wrongCategory->update(['category_id' => $otherCategory->id]);
        $wrongCategory->tags()->sync([$tag->id]);

        $url = route('categories.show', ['category' => $category, 'tag' => $tag->slug]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Matching category and tag')
            ->assertDontSee('Same category without tag')
            ->assertDontSee('Same tag in another category')
            ->assertSee('#3D')
            ->assertSee(__('Clear tag filter'))
            ->assertSee('href="'.route('categories.show', $category).'?tag=3d"', false)
            ->assertSee('<link rel="canonical" href="'.route('categories.show', $category).'">', false);

        Livewire::test('pages::gallery', ['category' => $category, 'tag' => $tag])
            ->call('clearTag')
            ->assertRedirect(route('categories.show', $category));
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
            GeneratedMedia::create([
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

    public function test_verified_daily_image_limit_uses_setting(): void
    {
        Setting::putValue('auth.verified_daily_image_limit', 3);

        $user = User::factory()->create(['id' => 2]);
        $this->actingAs($user);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $this->assertSame(3, $editor->dailyLimit());
        $this->assertSame(3, $editor->remainingToday($request));

        foreach (range(1, 3) as $i) {
            GeneratedMedia::create([
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
        $this->assertSame($editor->dailyLimit(), $editor->remainingToday($request));
        $this->assertSame(5, $editor->dailyLimit());
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

        GeneratedMedia::create([
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
            GeneratedMedia::create([
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
        $this->assertMatchesRegularExpression('#^image/generatedmedia/\d{6}/\d{2}/\d+/\d+/[\w-]+\.png$#', (string) $image->result_path);
        $this->assertMatchesRegularExpression('#^image/generatedmedia/\d{6}/\d{2}/\d+/\d+/[\w-]+\.jpg$#', (string) ($image->response_meta['source_paths'][0] ?? ''));
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
        $photos = collect(range(1, 5))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image($index.'.png'))
            ->all();

        $image = app(AiImageEditor::class)->create($request, $photos, 'Merge these references');

        Http::assertSent(fn (HttpRequest $request) => is_array($request['images'])
            && count($request['images']) === 5
            && ! isset($request['image'])
            && str_starts_with($request['images'][0], 'data:image/jpeg;base64,')
            && str_starts_with($request['images'][4], 'data:image/jpeg;base64,'));

        $this->assertSame('succeeded', $image->status);
        $this->assertCount(5, $image->response_meta['source_paths']);
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

        ImageReviewAgent::assertPrompted(fn ($prompt): bool => $prompt->attachments->first() instanceof Base64Image);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'http://42.112.31.227:22150/v1/images/generations'
            && str_contains($request['prompt'], 'Edit that image according to the instructions')
            && str_contains($request['input_image'], 'data:image/jpeg;base64,'));
        $this->assertSame('succeeded', $image->status);
        Storage::disk('public')->assertExists($image->result_path);
        Storage::disk('public')->assertMissing($pendingPath);
    }

    public function test_product_detail_pending_generation_uses_reference_role_contract(): void
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
        $user = User::factory()->create();
        $this->actingAs($user);
        $paths = [];

        foreach (['product', 'logo', 'model', 'side', 'back'] as $name) {
            $path = 'ai-image-pending/'.$name.'.png';
            Storage::disk('public')->put($path, UploadedFile::fake()->image($name.'.png')->getContent());
            $paths[] = ['path' => $path, 'name' => $name.'.png', 'mime' => 'image/png'];
        }

        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'role-contract',
            'prompt' => 'Create a branded lifestyle image.',
            'preset' => 'product-detail',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
            'request_meta' => [
                'pending_uploads' => $paths,
                'prompt_contract' => 'product-detail-v2',
                'reference_roles' => ['product', 'logo', 'model', 'additional_product', 'additional_product'],
            ],
        ]);

        app(AiImageEditor::class)->completePending($image);

        Http::assertSent(fn (HttpRequest $request): bool => count($request['images']) === 5
            && str_contains($request['prompt'], 'PRIMARY_PRODUCT')
            && str_contains($request['prompt'], 'BRAND_LOGO')
            && str_contains($request['prompt'], 'MODEL_IDENTITY')
            && str_contains($request['prompt'], 'SUPPLEMENTAL_PRODUCT_VIEW'));
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
        $parent = GeneratedMedia::create([
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

    public function test_rejected_reference_image_does_not_create_image_or_call_generation_api(): void
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
            app(AiImageEditor::class)->create($request, [UploadedFile::fake()->image('politician.png')], 'Chế ảnh ác quỷ');
        } finally {
            ImageReviewAgent::assertPrompted(fn ($prompt): bool => $prompt->attachments->first() instanceof Base64Image);
            $this->assertSame(0, GeneratedMedia::query()->count());
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

    public function test_metadata_agent_prioritizes_visible_subject_tags(): void
    {
        $instructions = (new ImageMetadataAgent)->instructions();

        $this->assertStringContainsString('Đặt từ khóa chính gần đầu', $instructions);
        $this->assertStringContainsString('Title và description phải khác nhau về câu chữ', $instructions);
        $this->assertStringContainsString('2-3 chủ thể/vật thể chính', $instructions);
        $this->assertStringContainsString('Không tạo hai tag đồng nghĩa/gần trùng', $instructions);
        $this->assertStringContainsString('Chỉ dùng lại tag có sẵn khi khớp chính xác', $instructions);
    }

    public function test_publish_sets_category_and_category_route_filters_gallery(): void
    {
        Storage::fake('public');
        $result = UploadedFile::fake()->image('result.png', 1200, 800);
        Storage::disk('public')->put('ai-images/202607/08/result.png', file_get_contents($result->getRealPath()));
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_review_model', 'gpt-5.5-mini');
        Setting::putValue('ai.tag_model', 'gpt-5.5-metadata');
        $category = Category::create(['name' => 'Meme nội bộ', 'slug' => 'internal-meme', 'sort_order' => 5, 'status' => 'active']);
        Tag::create(['name' => 'Nước hoa', 'slug' => 'nuoc-hoa']);
        ImageReviewAgent::fake([$this->allowedReview()]);
        ImageMetadataAgent::fake([$this->publishReview('internal-meme', ['Nước hoa', 'banner ads', 'sản phẩm'])]);

        $agent = new ImageReviewAgent;
        $metadataAgent = new ImageMetadataAgent;
        $this->assertStringContainsString('- internal-meme: Meme nội bộ', $metadataAgent->instructions());
        $this->assertStringContainsString('Tạo title tiếng Việt ngắn', $metadataAgent->instructions());
        $this->assertStringContainsString('- Nước hoa', $metadataAgent->instructions());
        $this->assertStringContainsString('Tạo 4-7 tags ngắn', $metadataAgent->instructions());
        $this->assertStringContainsString('2-3 chủ thể/vật thể chính', $metadataAgent->instructions());
        $this->assertStringContainsString('Không tạo hai tag đồng nghĩa/gần trùng', $metadataAgent->instructions());
        $this->assertStringContainsString('Chỉ dùng lại tag có sẵn khi khớp chính xác', $metadataAgent->instructions());
        $this->assertStringContainsString('Tạo description tiếng Việt', $metadataAgent->instructions());
        $this->assertStringContainsString('Mặc định allowed=true và blocked_policy=none', $agent->instructions());
        $this->assertStringContainsString('blocked_policy=sexual', $agent->instructions());
        $this->assertStringContainsString('blocked_policy=political', $agent->instructions());
        $this->assertStringContainsString('Ảnh chính trị vẫn phải bị chặn dù prompt dùng từ trung tính', $agent->instructions());
        $this->assertStringContainsString('Cho phép người nổi tiếng không giữ vai trò chính trị', $agent->instructions());
        $this->assertStringContainsString('mô phỏng giao diện hồ sơ mạng xã hội', $agent->instructions());

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $image = GeneratedMedia::create([
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
        ImageMetadataAgent::assertPrompted(function ($prompt): bool {
            $attachment = $prompt->attachments->first();
            $size = $attachment instanceof Base64Image ? getimagesizefromstring($attachment->content()) : false;

            return $prompt->model === 'gpt-5.5-metadata'
                && $prompt->provider->name() === 'openai'
                && $attachment instanceof Base64Image
                && is_array($size)
                && $size[0] === 1024
                && $size[1] === 683
                && $attachment->mimeType() === 'image/jpeg';
        });

        $other = GeneratedMedia::create([
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
        $this->assertSame('Banner nước hoa cao cấp cho quảng cáo sản phẩm, bố cục rõ chủ thể, phong cách thương mại hiện đại, phù hợp SEO gallery công khai.', $published->description);
        $this->assertTrue($published->category->is($category));
        $this->assertSame(['banner-ads', 'nuoc-hoa', 'san-pham'], $published->tags->pluck('slug')->sort()->values()->all());
        $this->assertSame('Nước hoa', Tag::query()->where('slug', 'nuoc-hoa')->value('name'));
        $this->assertSame(3, Tag::query()->count());
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

    public function test_image_detail_displays_saved_generation_options(): void
    {
        $image = $this->publishedImage('Ảnh có thiết lập');
        $image->update([
            'request_meta' => [
                'aspect_ratio' => '16:9',
                'resolution' => '2k',
                'size' => '1536x864',
                'image_detail' => 'original',
            ],
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('images.show', $image))
            ->assertOk()
            ->assertSee('x-show="expanded"', false)
            ->assertSee('x-cloak', false)
            ->assertSee('Thiết lập ảnh')
            ->assertSee('16:9')
            ->assertSee('2K')
            ->assertSee('Chất lượng: Cao');
    }

    public function test_guest_image_detail_truncates_prompt_and_login_sees_full_prompt(): void
    {
        $prompt = str_repeat('mô tả dài ', 30).'phần cuối bí mật';
        $image = $this->publishedImage($prompt);
        $image->update(['title' => 'Ảnh prompt dài']);
        $this->syncImageTags($image, ['chân dung', 'ánh sáng studio']);

        $this->get(route('images.show', $image))
            ->assertOk()
            ->assertSee('/anh/'.$image->id.'-anh-prompt-dai')
            ->assertSee('<meta name="keywords" content="chân dung, ánh sáng studio">', false)
            ->assertSee('Đăng nhập để xem đầy đủ prompt.')
            ->assertDontSee('Tải thêm ảnh')
            ->assertDontSee('phần cuối bí mật');

        $this->get('/anh/'.$image->id)
            ->assertStatus(301)
            ->assertRedirect(route('images.show', $image));
        $this->get('/anh/'.$image->id.'-slug-sai')
            ->assertStatus(301)
            ->assertRedirect(route('images.show', $image));
        $this->assertLessThanOrEqual(100, strlen(Str::after($image->getRouteKey(), '-')));

        $this->actingAs(User::factory()->create())
            ->get(route('images.show', $image))
            ->assertOk()
            ->assertSee('phần cuối bí mật');
    }

    public function test_publish_sends_stored_image_as_base64_attachment(): void
    {
        Storage::fake('public');
        $result = UploadedFile::fake()->image('result.png', 1200, 800);
        Storage::disk('public')->put('ai-images/result.png', file_get_contents($result->getRealPath()));
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([$this->allowedReview()]);
        ImageMetadataAgent::fake([$this->publishReview('other', [])]);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $image = GeneratedMedia::create([
            'visitor_key' => $editor->visitorKey($request),
            'prompt' => 'Tạo ảnh phong cảnh',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/result.png',
        ]);

        $editor->publish($image, $request);

        ImageReviewAgent::assertPrompted(fn ($prompt): bool => $prompt->attachments->first() instanceof Base64Image);
        ImageMetadataAgent::assertPrompted(fn ($prompt): bool => $prompt->attachments->first() instanceof Base64Image);
    }

    public function test_publish_allows_missing_review_tags(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([$this->allowedReview()]);
        ImageMetadataAgent::fake([$this->publishReview('other', [])]);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $image = GeneratedMedia::create([
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

    public function test_publish_turns_structured_prompt_titles_into_readable_text(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        $prompt = '{"render_goal":"Narrative điện ảnh fashion chân dung","subject":{"pose":"female seated in the back seat"}}';
        ImageReviewAgent::fake([$this->allowedReview()]);
        ImageMetadataAgent::fake([$this->publishReview('other', [], $prompt)]);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $image = GeneratedMedia::create([
            'visitor_key' => $editor->visitorKey($request),
            'prompt' => $prompt,
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/result.png',
        ]);

        $published = $editor->publish($image, $request);

        $this->assertSame('Narrative điện ảnh fashion chân dung', $published->title);
        $this->assertStringNotContainsString('{', $published->title);
    }

    public function test_publish_reviews_settings_provider_via_chat_completions_sdk_driver(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_review_model', 'tss-2');
        Setting::putValue('ai.tag_model', 'tss-metadata');
        $category = Category::create(['name' => 'Meme nội bộ', 'slug' => 'internal-meme', 'sort_order' => 5, 'status' => 'active']);
        Http::fakeSequence('42.112.31.227:22150/v1/chat/completions')
            ->push([
                'model' => 'tss-2',
                'choices' => [[
                    'message' => ['content' => json_encode($this->allowedReview())],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ])
            ->push([
                'model' => 'tss-metadata',
                'choices' => [[
                    'message' => ['content' => json_encode($this->publishReview('internal-meme'))],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]);

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $image = GeneratedMedia::create([
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
            && in_array($request['model'], ['tss-2', 'tss-metadata'], true)
            && ($request['response_format']['type'] ?? null) === 'json_schema');
        $this->assertSame(2, count(Http::recorded()));
    }

    public function test_image_to_prompt_agent_requests_advanced_visual_analysis(): void
    {
        $instructions = (new ImageToPromptAgent)->instructions();

        $this->assertStringContainsString('Viết prompt bằng tiếng Việt', $instructions);
        $this->assertStringContainsString('Máy ảnh và quang học', $instructions);
        $this->assertStringContainsString('Chất liệu và rendering', $instructions);
        $this->assertStringContainsString('* **Phong cách:**', $instructions);
        $this->assertStringContainsString('* **Máy ảnh:**', $instructions);
        $this->assertStringContainsString('* **Cần giữ:**', $instructions);
    }

    public function test_image_to_prompt_uses_processed_attachment_and_separate_model(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_to_prompt_model', 'gpt-5.5-vision');
        ImageToPromptAgent::fake([['prompt' => 'A cinematic portrait with soft window light.']]);

        $prompt = app(AiImageEditor::class)->promptFromImage(UploadedFile::fake()->image('source.png', 1600, 900));

        $this->assertSame('A cinematic portrait with soft window light.', $prompt);
        ImageToPromptAgent::assertPrompted(function ($prompt): bool {
            $attachment = $prompt->attachments->first();

            $size = $attachment instanceof Base64Image
                ? getimagesizefromstring($attachment->content())
                : false;

            return $prompt->model === 'gpt-5.5-vision'
                && $attachment instanceof Base64Image
                && $attachment->mimeType() === 'image/jpeg'
                && is_array($size)
                && [$size[0], $size[1]] === [1024, 576];
        });
    }

    public function test_image_to_prompt_does_not_truncate_advanced_output(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        $advancedPrompt = str_repeat('Chi tiết thị giác chuyên sâu. ', 150);
        ImageToPromptAgent::fake([['prompt' => $advancedPrompt]]);

        $prompt = app(AiImageEditor::class)->promptFromImage(UploadedFile::fake()->image('source.jpg'));

        $this->assertGreaterThan(2000, mb_strlen($prompt));
        $this->assertSame(trim($advancedPrompt), $prompt);
    }

    public function test_image_to_prompt_inherits_default_text_model(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.text_model', 'gpt-5.5-default-text');
        Setting::putValue('ai.image_to_prompt_model', '');
        ImageToPromptAgent::fake([['prompt' => 'A detailed image prompt.']]);

        app(AiImageEditor::class)->promptFromImage(UploadedFile::fake()->image('source.jpg'));

        ImageToPromptAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'gpt-5.5-default-text');
    }

    public function test_prompt_translation_uses_separate_agent_and_model_setting(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.prompt_translation_model', 'gpt-5.5-translation');
        PromptTranslationAgent::fake([['prompt' => 'Một chú mèo đội chiếc mũ nhỏ.']]);

        $prompt = app(AiImageEditor::class)->translatePrompt('A cat wearing a tiny hat.');

        $this->assertSame('Một chú mèo đội chiếc mũ nhỏ.', $prompt);
        PromptTranslationAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'gpt-5.5-translation'
            && str_contains($prompt->prompt, 'A cat wearing a tiny hat.'));
    }

    public function test_prompt_translation_inherits_default_text_model(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.text_model', 'gpt-5.5-default-text');
        Setting::putValue('ai.prompt_translation_model', '');
        PromptTranslationAgent::fake([['prompt' => 'Một chú mèo.']]);

        app(AiImageEditor::class)->translatePrompt('A cat.');

        PromptTranslationAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'gpt-5.5-default-text');
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

    public function test_prompt_rewrite_inherits_default_text_model(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.text_model', 'gpt-5.5-default-text');
        Setting::putValue('ai.prompt_rewrite_model', '');
        PromptRewriteAgent::fake([['prompt' => 'A cinematic portrait of a cat wearing a tiny hat.']]);

        app(AiImageEditor::class)->rewritePrompt('cat with hat');

        PromptRewriteAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'gpt-5.5-default-text');
    }

    public function test_prompt_rewrite_accepts_instruction_without_current_prompt(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        PromptRewriteAgent::fake([['prompt' => 'A cinematic portrait of a cat wearing a tiny hat.']]);

        $prompt = app(AiImageEditor::class)->rewritePrompt('', 'create a cinematic cat portrait');

        $this->assertSame('A cinematic portrait of a cat wearing a tiny hat.', $prompt);
        PromptRewriteAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'create a cinematic cat portrait'));
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

        $image = GeneratedMedia::create([
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
            $image->refresh();
            $this->assertFalse($image->is_published);
            $this->assertSame('Prompt không phù hợp để tạo hoặc publish ảnh.', data_get($image->response_meta, 'publish_error'));
        }
    }

    public function test_non_admin_cannot_retry_rejected_publish_but_admin_can(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => true, 'blocked_policy' => 'none', 'reason' => 'An toàn.']]);
        ImageMetadataAgent::fake([$this->publishReview('other', [])]);
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create();
        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Tạo ảnh phong cảnh',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/result.png',
            'response_meta' => ['publish_error' => 'Prompt không phù hợp để tạo hoặc publish ảnh.'],
        ]);
        $request = Request::create('/', 'POST');

        $this->actingAs($user);

        try {
            app(AiImageEditor::class)->publish($image, $request);
            $this->fail('Non-admin publish retry should be blocked.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Prompt không phù hợp để tạo hoặc publish ảnh.', $e->getMessage());
        }

        ImageReviewAgent::assertNeverPrompted();

        $this->actingAs($admin);
        $published = app(AiImageEditor::class)->publish($image, $request, requireOwner: false);

        $this->assertTrue($published->is_published);
        $this->assertNull(data_get($published->response_meta, 'publish_error'));
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
            ->test('gallery.detail', ['selectedImageId' => $featured->id, 'standalone' => true])
            ->assertSee(__('Feature image'))
            ->call('toggleFeatured', $featured->id)
            ->assertSee(__('Featured'));

        $this->assertTrue($featured->fresh()->is_featured);
        $this->assertSame([$featured->id, $newer->id], app(AiImageEditor::class)->publishedGallery(sort: 'featured')->pluck('id')->take(2)->all());

        Livewire::actingAs(User::factory()->create())
            ->test('gallery.detail', ['selectedImageId' => $featured->id, 'show' => true, 'standalone' => true])
            ->assertSet('selectedImageId', $featured->id)
            ->assertDontSee(__('Feature image'))
            ->call('toggleFeatured', $featured->id);

        $this->assertTrue($featured->fresh()->is_featured);
    }

    public function test_gpt_image_options_map_aspect_and_resolution_to_valid_sizes(): void
    {
        $this->assertSame('1024x1024', GptImageOptions::size('auto', '1k'));
        $this->assertSame('1248x1248', GptImageOptions::size('1:1', '2k'));
        $this->assertSame('1248x1248', GptImageOptions::size('1:1', '4k'));
        $this->assertSame('688x1024', GptImageOptions::size('2:3', '1k'));
        $this->assertSame('1024x688', GptImageOptions::size('3:2', '1k'));
        $this->assertSame('1248x1664', GptImageOptions::size('3:4', '4k'));
        $this->assertSame('1664x928', GptImageOptions::size('16:9', '4k'));
        $this->assertSame('1536x864', GptImageOptions::size('16:9', '2k'));

        foreach (GptImageOptions::ASPECT_RATIOS as $aspect) {
            foreach (GptImageOptions::RESOLUTIONS as $resolution) {
                $size = GptImageOptions::size($aspect, $resolution);
                $this->assertMatchesRegularExpression('/^\d+x\d+$/', $size);
                [$width, $height] = array_map('intval', explode('x', $size));
                $this->assertSame(0, $width % 16);
                $this->assertSame(0, $height % 16);
                $this->assertLessThanOrEqual(1664, max($width, $height));
                $this->assertLessThanOrEqual(3.0, max($width, $height) / min($width, $height));
            }
        }

        $this->assertSame(
            ['aspect_ratio' => '2:3', 'resolution' => '2k'],
            GptImageOptions::defaultsFromSettings('1024x1536'),
        );
        $this->assertSame(
            ['aspect_ratio' => 'auto', 'resolution' => '1k'],
            GptImageOptions::defaultsFromSettings('auto'),
        );
    }

    public function test_pending_generation_uses_request_meta_size_and_image_detail_overrides(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_reference_field', 'input_image');
        Setting::putValue('ai.image_size', '1024x1024');
        Setting::putValue('ai.image_quality', 'hd');
        Setting::putValue('ai.image_detail', 'low');
        ImageReviewAgent::fake([$this->allowedReview()]);

        $providerPng = $this->pngBinary(1672, 941);
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode($providerPng)]],
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
            size: GptImageOptions::size('16:9', '2k'),
            imageDetail: 'original',
            aspectRatio: '16:9',
            resolution: '2k',
        );

        $storedImage = GeneratedMedia::findOrFail($image->id);
        $this->assertSame('16:9', data_get($storedImage->request_meta, 'aspect_ratio'));
        $this->assertSame('2k', data_get($storedImage->request_meta, 'resolution'));
        $this->assertSame('1536x864', data_get($storedImage->request_meta, 'size'));
        $this->assertSame('original', data_get($storedImage->request_meta, 'image_detail'));

        $image = app(AiImageEditor::class)->completePending($image);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'http://42.112.31.227:22150/v1/images/generations'
            && $request['size'] === '1536x864'
            && $request['image_detail'] === 'original'
            && $request['quality'] === 'hd');
        $this->assertSame('succeeded', $image->status);
        $this->assertSame('16:9', data_get($image->request_meta, 'aspect_ratio'));
        $this->assertSame('2k', data_get($image->request_meta, 'resolution'));
        $this->assertSame('1536x864', data_get($image->request_meta, 'size'));
        $this->assertSame('original', data_get($image->request_meta, 'image_detail'));
        $this->assertSame(1672, data_get($image->response_meta, 'dimensions.width'));
        $this->assertSame(941, data_get($image->response_meta, 'dimensions.height'));
        $this->assertTrue((bool) data_get($image->response_meta, 'dimensions.meets_width_or_height'));
        $this->assertFalse((bool) data_get($image->response_meta, 'dimensions.resized'));
        $resultBinary = Storage::disk('public')->get($image->result_path);
        $resultSize = getimagesizefromstring($resultBinary);
        $this->assertSame(1672, $resultSize[0] ?? null);
        $this->assertSame(941, $resultSize[1] ?? null);

        $image->update(['status' => 'failed']);
        $retried = app(AiImageEditor::class)->retryFailed($image->refresh(), $request);
        $this->assertSame('16:9', data_get($retried->request_meta, 'aspect_ratio'));
        $this->assertSame('2k', data_get($retried->request_meta, 'resolution'));
        $this->assertSame('1536x864', data_get($retried->request_meta, 'size'));
        $this->assertSame('original', data_get($retried->request_meta, 'image_detail'));
    }

    public function test_pending_generation_rejects_provider_image_below_requested_size(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_quality', 'hd');
        ImageReviewAgent::fake([$this->allowedReview()]);

        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode($this->pngBinary(800, 600))]],
            ]),
        ]);

        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $this->actingAs(User::factory()->create());

        $image = app(AiImageEditor::class)->createPending(
            $request,
            [],
            'Small provider output',
            size: GptImageOptions::size('16:9', '2k'),
            imageDetail: 'high',
            aspectRatio: '16:9',
            resolution: '2k',
        );

        $image = app(AiImageEditor::class)->completePending($image);
        $this->assertSame('failed', $image->status);
        $this->assertStringContainsString('nhỏ hơn cấu hình', (string) $image->error);
    }

    public function test_pending_generation_falls_back_to_settings_without_overrides(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_size', '1024x1536');
        Setting::putValue('ai.image_quality', 'medium');
        Setting::putValue('ai.image_detail', 'high');
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

        $image = app(AiImageEditor::class)->createPending($request, [], 'Landscape portrait');
        $this->assertNull(data_get($image->request_meta, 'size'));
        $this->assertNull(data_get($image->request_meta, 'image_detail'));

        $image = app(AiImageEditor::class)->completePending($image);

        Http::assertSent(fn (HttpRequest $request) => $request['size'] === '1024x1536'
            && $request['image_detail'] === 'high'
            && $request['quality'] === 'medium');
        $this->assertSame('succeeded', $image->status);
    }

    public function test_generator_persists_aspect_resolution_and_image_quality(): void
    {
        Bus::fake();

        $this->actingAs(User::factory()->create());

        Livewire::test('gallery.generator')
            ->set('showComposer', true)
            ->set('prompt', 'Wide landscape with correct options')
            ->set('aspectRatio', '16:9')
            ->set('resolution', '2k')
            ->set('imageDetail', 'original')
            ->call('createImage')
            ->assertHasNoErrors();

        $image = GeneratedMedia::query()->latest('id')->firstOrFail();
        $this->assertSame('16:9', data_get($image->request_meta, 'aspect_ratio'));
        $this->assertSame('2k', data_get($image->request_meta, 'resolution'));
        $this->assertSame('1536x864', data_get($image->request_meta, 'size'));
        $this->assertSame('original', data_get($image->request_meta, 'image_detail'));
        $this->assertSame('1536x864', GptImageOptions::size('16:9', '2k'));
    }

    private function publishedImage(string $prompt, ?\DateTimeInterface $publishedAt = null): GeneratedMedia
    {
        return GeneratedMedia::create([
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
    private function syncImageTags(GeneratedMedia $image, array $tags): void
    {
        $image->tags()->sync(collect($tags)->map(fn (string $tag): int => (int) Tag::query()->firstOrCreate([
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
     * @return array{allowed: bool, blocked_policy: string, title: string, description: string, category: string, tags: list<string>, reason: string}
     */
    private function publishReview(string $category, array $tags = ['nước hoa', 'banner ads', 'sản phẩm'], string $title = 'Banner nước hoa cao cấp', string $description = 'Banner nước hoa cao cấp cho quảng cáo sản phẩm, bố cục rõ chủ thể, phong cách thương mại hiện đại, phù hợp SEO gallery công khai.'): array
    {
        return ['allowed' => true, 'blocked_policy' => 'none', 'title' => $title, 'description' => $description, 'category' => $category, 'tags' => $tags, 'reason' => 'An toàn.'];
    }

    private function pngBinary(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);
        $color = imagecolorallocate($image, 30, 120, 200);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);
        ob_start();
        imagepng($image);
        imagedestroy($image);
        $binary = ob_get_clean();
        $this->assertIsString($binary);
        $this->assertNotSame('', $binary);

        return $binary;
    }

    private function encodedImageSizeIs(string $image, int $width, int $height): bool
    {
        $content = base64_decode(substr($image, strlen('data:image/jpeg;base64,')), true);
        $size = is_string($content) ? getimagesizefromstring($content) : false;

        return is_array($size) && $size[0] === $width && $size[1] === $height;
    }
}
