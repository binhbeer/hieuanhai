<?php

namespace Tests\Feature;

use App\Ai\ImageReviewAgent;
use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use App\Models\AiImage;
use App\Models\AiTag;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiImageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_a_valid_key(): void
    {
        $this->postJson('/api/ai/images')
            ->assertUnauthorized()
            ->assertJson(['message' => 'API key không hợp lệ.']);
    }

    public function test_valid_api_key_creates_image_and_logs_request(): void
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
        [$plain, $key] = $this->apiKey(quotaLimit: 2);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->post('/api/ai/images', [
                'prompt' => 'Make this a comic portrait',
                'images' => [UploadedFile::fake()->image('source.jpg', 800, 600)],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('quota.used', 1)
            ->assertJsonPath('quota.remaining', 1);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'http://42.112.31.227:22150/v1/images/generations'
            && str_contains($request['prompt'], 'Make this a comic portrait'));

        $key->refresh();
        $this->assertSame(1, $key->quota_used);
        $this->assertFalse(AiImage::query()->latest('id')->firstOrFail()->is_published);
        $this->assertDatabaseHas('ai_api_requests', [
            'ai_api_key_id' => $key->id,
            'status_code' => 201,
            'status' => 'succeeded',
            'quota_charged' => true,
        ]);
    }

    public function test_publish_api_creates_publishes_and_logs_image(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([
            $this->allowedReview(),
            $this->publishReview('portraits', ['avatar', 'studio']),
        ]);
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);
        [$plain, $key] = $this->apiKey(quotaLimit: 2);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->post('/api/ai/images/publish', [
                'prompt' => 'Make this a public avatar portrait',
                'source' => 'meigen-123',
                'images' => [UploadedFile::fake()->image('source.jpg', 800, 600)],
            ]);

        $image = AiImage::query()->latest('id')->firstOrFail()->load(['category', 'tags']);

        $response
            ->assertCreated()
            ->assertJsonPath('id', $image->id)
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('published', true)
            ->assertJsonPath('title', 'Public avatar portrait')
            ->assertJsonPath('source', 'meigen-123')
            ->assertJsonPath('category.slug', 'portraits')
            ->assertJsonPath('tags.0', 'avatar')
            ->assertJsonPath('tags.1', 'studio')
            ->assertJsonPath('quota.used', 1)
            ->assertJsonPath('quota.remaining', 1)
            ->assertJson(fn ($json) => $json
                ->where('public_url', route('images.show', $image))
                ->where('public_url', fn (string $url): bool => str_contains($url, '/anh/'.$image->id.'-public-avatar-portrait'))
                ->has('url')
                ->has('download_name')
                ->etc()
            );

        $key->refresh();
        $this->assertSame(1, $key->quota_used);
        $this->assertTrue($image->is_published);
        $this->assertSame('meigen-123', $image->source);
        $this->assertSame('portraits', $image->category?->slug);
        $this->assertSame(['avatar', 'studio'], $image->tags->pluck('name')->values()->all());
        $this->assertDatabaseHas('ai_api_requests', [
            'ai_api_key_id' => $key->id,
            'ai_image_id' => $image->id,
            'status_code' => 201,
            'status' => 'succeeded',
            'quota_charged' => true,
        ]);
    }

    public function test_publish_api_rejection_does_not_charge_quota(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([
            $this->allowedReview(),
            ['allowed' => false, 'blocked_policy' => 'sexual', 'reason' => 'Không phù hợp.'],
        ]);
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);
        [$plain, $key] = $this->apiKey(quotaLimit: 2);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images/publish', [
                'prompt' => 'Unsafe public image',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Prompt không phù hợp để tạo hoặc publish ảnh.');

        $key->refresh();
        $image = AiImage::query()->latest('id')->firstOrFail();
        $this->assertSame(0, $key->quota_used);
        $this->assertFalse($image->is_published);
        $this->assertDatabaseHas('ai_api_requests', [
            'ai_api_key_id' => $key->id,
            'ai_image_id' => $image->id,
            'status_code' => 422,
            'status' => 'validation_failed',
            'quota_charged' => false,
        ]);
    }

    public function test_categories_api_returns_active_categories_without_key(): void
    {
        Category::create(['name' => 'ZZZ Hidden', 'slug' => 'hidden', 'sort_order' => 1, 'status' => 'inactive']);
        Category::create(['name' => 'AAA Public', 'slug' => 'aaa-public', 'sort_order' => 1, 'status' => 'active']);

        $response = $this->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'aaa-public')
            ->assertJsonMissing(['slug' => 'hidden'])
            ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
    }

    public function test_categories_api_is_rate_limited_by_ip(): void
    {
        $ip = '203.0.113.'.random_int(1, 254);

        for ($i = 0; $i < 60; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson('/api/categories')->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson('/api/categories')->assertTooManyRequests();
    }

    public function test_public_images_search_filters_published_images(): void
    {
        $user = User::factory()->create(['name' => 'Search User']);
        $otherUser = User::factory()->create();
        $category = Category::create(['name' => 'Search Portraits', 'slug' => 'search-portraits', 'sort_order' => 1, 'status' => 'active']);
        $otherCategory = Category::create(['name' => 'Search Products', 'slug' => 'search-products', 'sort_order' => 2, 'status' => 'active']);
        $tag = AiTag::create(['name' => 'Meigen Tag', 'slug' => 'meigen-tag']);
        $otherTag = AiTag::create(['name' => 'Other Tag', 'slug' => 'other-tag']);
        $image = AiImage::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Meigen portrait result',
            'visitor_key' => 'visitor-search-a',
            'prompt' => 'A searchable prompt with meigen keyword',
            'source' => 'meigen-456',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/10/search.png',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $image->tags()->sync([$tag->id]);
        $other = AiImage::create([
            'user_id' => $otherUser->id,
            'category_id' => $otherCategory->id,
            'title' => 'Other result',
            'visitor_key' => 'visitor-search-b',
            'prompt' => 'Different prompt',
            'source' => 'other-456',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/10/other.png',
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
        $other->tags()->sync([$otherTag->id]);
        AiImage::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Hidden meigen result',
            'visitor_key' => 'visitor-search-c',
            'prompt' => 'Hidden prompt',
            'source' => 'meigen-456',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/10/hidden.png',
            'is_published' => false,
        ]);

        $response = $this->getJson('/api/images/search?keyword=meigen&category=search-portraits&tag=meigen-tag&source=meigen-456&user='.$user->id);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $image->id)
            ->assertJsonPath('data.0.title', 'Meigen portrait result')
            ->assertJsonPath('data.0.source', 'meigen-456')
            ->assertJsonPath('data.0.category.slug', 'search-portraits')
            ->assertJsonPath('data.0.tags.0.slug', 'meigen-tag')
            ->assertJsonPath('data.0.user.id', $user->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['id' => $other->id]);

        $this->getJson('/api/images/search?source=meigen-456')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/images/search?category=search-products')->assertJsonPath('data.0.id', $other->id);
        $this->getJson('/api/images/search?tag=other-tag')->assertJsonPath('data.0.id', $other->id);
        $this->getJson('/api/images/search?user='.$otherUser->id)->assertJsonPath('data.0.id', $other->id);
    }

    public function test_api_rejects_invalid_source_without_charging_quota(): void
    {
        [$plain, $key] = $this->apiKey(quotaLimit: 1);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images', [
                'prompt' => 'Make a comic portrait',
                'source' => 'bad source with spaces',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');

        $key->refresh();
        $this->assertSame(0, $key->quota_used);
    }

    public function test_api_accepts_prompt_without_images(): void
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
        [$plain, $key] = $this->apiKey(quotaLimit: 2);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images', [
                'prompt' => 'Make a comic portrait',
            ])
            ->assertCreated()
            ->assertJsonPath('quota.used', 1);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'http://42.112.31.227:22150/v1/images/generations'
            && $request['prompt'] === 'Make a comic portrait'
            && ! isset($request['image'])
            && ! isset($request['images']));

        $key->refresh();
        $this->assertSame(1, $key->quota_used);
    }

    public function test_api_requires_prompt_and_does_not_charge_quota_on_validation_error(): void
    {
        [$plain, $key] = $this->apiKey(quotaLimit: 1);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->post('/api/ai/images', [
                'images' => [UploadedFile::fake()->image('source.jpg')],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prompt');

        $key->refresh();
        $this->assertSame(0, $key->quota_used);
        $this->assertDatabaseHas('ai_api_requests', [
            'ai_api_key_id' => $key->id,
            'status_code' => 422,
            'status' => 'validation_failed',
            'quota_charged' => false,
        ]);
    }

    public function test_api_rejects_prompt_over_1200_words_without_charging_quota(): void
    {
        [$plain, $key] = $this->apiKey(quotaLimit: 1);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images', [
                'prompt' => str_repeat('mèo ', 1201),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prompt');

        $key->refresh();
        $this->assertSame(0, $key->quota_used);
        $this->assertDatabaseHas('ai_api_requests', [
            'ai_api_key_id' => $key->id,
            'status_code' => 422,
            'status' => 'validation_failed',
            'quota_charged' => false,
        ]);
    }

    public function test_api_rejected_prompt_does_not_charge_quota_or_call_image_api(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => false, 'blocked_policy' => 'political', 'reason' => 'Không phù hợp.']]);
        Http::fake();
        [$plain, $key] = $this->apiKey(quotaLimit: 2);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images', [
                'prompt' => 'Tạo ảnh bôi xấu lãnh tụ',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Prompt không phù hợp để tạo hoặc publish ảnh.');

        Http::assertNothingSent();
        $key->refresh();
        $this->assertSame(0, $key->quota_used);
        $this->assertDatabaseHas('ai_api_requests', [
            'ai_api_key_id' => $key->id,
            'status_code' => 422,
            'status' => 'validation_failed',
            'quota_charged' => false,
        ]);
    }

    public function test_api_allows_non_blocked_false_review_policy(): void
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
        [$plain, $key] = $this->apiKey(quotaLimit: 2);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images', [
                'prompt' => 'Tạo ảnh comic bất kì',
            ])
            ->assertCreated()
            ->assertJsonPath('quota.used', 1);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'http://42.112.31.227:22150/v1/images/generations');
        $key->refresh();
        $this->assertSame(1, $key->quota_used);
    }

    public function test_api_rate_limit_allows_ten_requests_per_second(): void
    {
        $token = 'invalid-'.Str::random(32);

        for ($i = 0; $i < 10; $i++) {
            $this
                ->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/ai/images')
                ->assertUnauthorized();
        }

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/ai/images')
            ->assertTooManyRequests();
    }

    public function test_api_stops_when_lifetime_quota_is_exhausted(): void
    {
        Http::fake();
        [$plain, $key] = $this->apiKey(quotaLimit: 1, quotaUsed: 1);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->post('/api/ai/images', [
                'prompt' => 'Try image',
                'images' => [UploadedFile::fake()->image('source.jpg')],
            ])
            ->assertStatus(429)
            ->assertJsonPath('quota.remaining', 0);

        Http::assertNothingSent();
        $key->refresh();
        $this->assertSame(1, $key->quota_used);
        $this->assertDatabaseHas('ai_api_requests', [
            'ai_api_key_id' => $key->id,
            'status_code' => 429,
            'status' => 'quota_exceeded',
            'quota_charged' => false,
        ]);
    }

    public function test_only_admins_can_open_api_key_management(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('manage.api-keys.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('manage.api-keys.index'))->assertOk()->assertSee('Quản lý API key');
        $this->actingAs($admin)->get(route('api-keys.index'))->assertRedirect(route('manage.api-keys.index', absolute: false));
    }

    public function test_admin_can_ban_users_from_management(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::manage.users')
            ->call('toggleBan', $user->id)
            ->assertSee('Đã khóa');

        $this->assertNotNull($user->refresh()->banned_at);
    }

    public function test_admin_can_create_user_from_management(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        Livewire::actingAs($admin)
            ->test('pages::manage.users')
            ->set('name', 'New Moderator')
            ->set('email', 'moderator@example.com')
            ->set('role', 'mod')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('name', '')
            ->assertSet('role', 'user')
            ->assertSet('password', '')
            ->assertSee('New Moderator');

        $user = User::query()->where('email', 'moderator@example.com')->firstOrFail();

        $this->assertSame('mod', $user->role->value);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_user_creation_rejects_duplicate_email_and_unconfirmed_password(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $existing = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::manage.users')
            ->set('name', 'Duplicate User')
            ->set('email', $existing->email)
            ->set('password', 'secret123')
            ->set('password_confirmation', 'different')
            ->call('create')
            ->assertHasErrors(['email', 'password']);
    }

    public function test_admin_can_update_user_api_key_quota_from_user_edit(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create();
        $key = $this->apiKey($user, quotaLimit: 5, quotaUsed: 2)[1];

        Livewire::actingAs($admin)
            ->test('pages::manage.user-edit', ['user' => $user])
            ->set('apiKeyQuotaLimit', 20)
            ->call('saveApiKeyQuota')
            ->assertHasNoErrors();

        $key->refresh();
        $this->assertSame(20, $key->quota_limit);
        $this->assertSame(18, $key->quotaRemaining());
    }

    public function test_settings_page_handles_invalid_encrypted_openai_key(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        DB::table('settings')->insert([
            'key' => 'ai.openai_api_key',
            'value' => 'not-encrypted-with-this-app-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->assertSet('openaiApiKey', '')
            ->assertOk();

        $this->assertNull(Setting::getValue('ai.openai_api_key'));
    }

    public function test_admin_can_update_settings(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->set('siteName', 'GenAnh Pro')
            ->set('homeTitle', 'Chỉnh ảnh AI miễn phí')
            ->set('googleMeasurementId', 'G-SZ9BZEKLZ1')
            ->set('registrationEnabled', false)
            ->set('emailVerificationRequired', false)
            ->set('aiReviewModel', 'gpt-5.5-mini')
            ->set('promptRewriteModel', 'gpt-5.5-rewrite')
            ->set('imageSize', '1536x1024')
            ->set('imageQuality', 'medium')
            ->set('imageDetail', 'original')
            ->set('imageReferenceField', 'input_image')
            ->set('openaiApiKey', 'secret-key')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('GenAnh Pro', Setting::getValue('site.name'));
        $this->assertSame('Chỉnh ảnh AI miễn phí', Setting::getValue('site.home_title'));
        $this->assertSame('G-SZ9BZEKLZ1', Setting::getValue('analytics.google_measurement_id'));
        $this->assertFalse((bool) Setting::getValue('auth.registration_enabled'));
        $this->assertFalse((bool) Setting::getValue('auth.email_verification_required'));
        $this->assertSame('gpt-5.5-mini', Setting::getValue('ai.image_review_model'));
        $this->assertSame('gpt-5.5-rewrite', Setting::getValue('ai.prompt_rewrite_model'));
        $this->assertSame('1536x1024', Setting::getValue('ai.image_size'));
        $this->assertSame('medium', Setting::getValue('ai.image_quality'));
        $this->assertSame('original', Setting::getValue('ai.image_detail'));
        $this->assertSame('input_image', Setting::getValue('ai.image_reference_field'));
        $this->assertSame('secret-key', Setting::getValue('ai.openai_api_key'));
    }

    public function test_admin_key_management_keeps_one_key_per_user(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        $component = Livewire::actingAs($admin)
            ->test('pages::manage.api-keys')
            ->set('quotaLimit', 10)
            ->call('createKey');

        $firstPlain = $component->get('newToken');
        $keyId = AiApiKey::query()->where('user_id', $admin->id)->firstOrFail()->id;

        $component
            ->set('quotaLimit', 25)
            ->call('createKey')
            ->assertSee('Danh sách API key')
            ->assertSee('25');

        $secondPlain = $component->get('newToken');

        $this->assertIsString($firstPlain);
        $this->assertIsString($secondPlain);
        $this->assertSame(1, AiApiKey::query()->where('user_id', $admin->id)->count());
        $this->assertDatabaseMissing('ai_api_keys', [
            'id' => $keyId,
            'token_hash' => AiApiKey::hashToken($firstPlain),
        ]);
        $this->assertDatabaseHas('ai_api_keys', [
            'id' => $keyId,
            'token_hash' => AiApiKey::hashToken($secondPlain),
            'quota_limit' => 25,
        ]);
    }

    public function test_api_key_management_can_show_and_copy_stored_key(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        [$plain] = $this->apiKey($admin);

        Livewire::actingAs($admin)
            ->test('pages::manage.api-keys')
            ->assertSee($plain)
            ->assertSee('Copy', false)
            ->assertDontSee('Key cũ chưa lưu plaintext');
    }

    public function test_regenerate_key_replaces_only_that_token(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $otherUser = User::factory()->create();
        [$oldPlain, $targetKey] = $this->apiKey($admin);
        [$otherPlain, $otherKey] = $this->apiKey($otherUser);

        $component = Livewire::actingAs($admin)
            ->test('pages::manage.api-keys')
            ->call('regenerateKey', $targetKey->id);

        $newPlain = $component->get('newToken');

        $this->assertIsString($newPlain);
        $this->assertStringStartsWith('hai_', $newPlain);
        $this->assertDatabaseMissing('ai_api_keys', [
            'id' => $targetKey->id,
            'token_hash' => AiApiKey::hashToken($oldPlain),
        ]);
        $this->assertDatabaseHas('ai_api_keys', [
            'id' => $targetKey->id,
            'token_hash' => AiApiKey::hashToken($newPlain),
        ]);
        $this->assertDatabaseHas('ai_api_keys', [
            'id' => $otherKey->id,
            'token_hash' => AiApiKey::hashToken($otherPlain),
        ]);
    }

    public function test_api_key_settings_shows_safe_summary_and_usage_guide(): void
    {
        [$plain, $key] = $this->apiKey(quotaLimit: 5, quotaUsed: 2);
        AiApiRequest::create([
            'ai_api_key_id' => $key->id,
            'user_id' => $key->user_id,
            'status_code' => 201,
            'status' => 'succeeded',
            'duration_ms' => 123,
            'quota_charged' => true,
            'request_meta' => ['prompt' => 'hidden prompt'],
            'response_meta' => ['secret' => 'hidden meta'],
        ]);

        Livewire::actingAs($key->user)
            ->test('settings.api-key')
            ->assertSee('Hướng dẫn sử dụng API')
            ->assertSee('POST')
            ->assertSee('/api/ai/images')
            ->assertSee('Authorization: Bearer hai_xxx')
            ->assertSee('Còn lại')
            ->assertSee('3')
            ->assertSee('HTTP 201')
            ->assertDontSee('hidden prompt')
            ->assertDontSee('hidden meta');

        $this->get('/quota-check')->assertNotFound();
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
     * @return array{allowed: bool, blocked_policy: string, reason: string, title: string, category: string, tags: list<string>}
     */
    private function publishReview(string $category = 'portraits', array $tags = [], string $title = 'Public avatar portrait'): array
    {
        return [
            'allowed' => true,
            'blocked_policy' => 'none',
            'reason' => 'An toàn.',
            'title' => $title,
            'category' => $category,
            'tags' => $tags,
        ];
    }

    /**
     * @return array{0: string, 1: AiApiKey}
     */
    private function apiKey(?User $user = null, int $quotaLimit = 100, int $quotaUsed = 0): array
    {
        $user ??= User::factory()->create();
        $token = AiApiKey::newToken();
        $key = AiApiKey::create([
            'user_id' => $user->id,
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
            'token' => $token['plain'],
            'quota_limit' => $quotaLimit,
            'quota_used' => $quotaUsed,
        ]);

        return [$token['plain'], $key];
    }
}
