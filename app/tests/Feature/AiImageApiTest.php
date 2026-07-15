<?php

namespace Tests\Feature;

use App\Ai\ImageMetadataAgent;
use App\Ai\ImageReviewAgent;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\Tag;
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

    public function test_legacy_hai_api_key_remains_valid(): void
    {
        $user = User::factory()->create();
        $plain = 'hai_'.Str::random(48);
        ApiKey::create([
            'user_id' => $user->id,
            'token_hash' => ApiKey::hashToken($plain),
            'token_prefix' => substr($plain, 0, 12),
            'quota_limit' => 1,
            'quota_used' => 0,
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('prompt');
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
        $this->assertFalse(GeneratedMedia::query()->latest('id')->firstOrFail()->is_published);
        $this->assertDatabaseHas('api_requests', [
            'api_key_id' => $key->id,
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
        ImageReviewAgent::fake([$this->allowedReview(), $this->allowedReview()]);
        ImageMetadataAgent::fake([$this->publishReview('portraits', ['avatar', 'studio'])]);
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode(UploadedFile::fake()->image('result.png')->getContent())]],
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

        $image = GeneratedMedia::query()->latest('id')->firstOrFail()->load(['category', 'tags']);

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
        $this->assertDatabaseHas('api_requests', [
            'api_key_id' => $key->id,
            'media_id' => $image->id,
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
                'data' => [['b64_json' => base64_encode(UploadedFile::fake()->image('result.png')->getContent())]],
            ]),
        ]);
        [$plain, $key] = $this->apiKey(quotaLimit: 2);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images/publish', [
                'prompt' => 'Unsafe public image',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Prompt không phù hợp để tạo hoặc publish ảnh.')
            ->assertJsonPath('error_code', 'IMAGE_REVIEW_BLOCKED_SEXUAL');

        $key->refresh();
        $image = GeneratedMedia::query()->latest('id')->firstOrFail();
        $this->assertSame(0, $key->quota_used);
        $this->assertFalse($image->is_published);
        $this->assertSame('IMAGE_REVIEW_BLOCKED_SEXUAL', data_get(ApiRequest::query()->latest('id')->firstOrFail()->response_meta, 'error_code'));
        $this->assertDatabaseHas('api_requests', [
            'api_key_id' => $key->id,
            'media_id' => $image->id,
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
        $tag = Tag::create(['name' => 'Meigen Tag', 'slug' => 'meigen-tag']);
        $otherTag = Tag::create(['name' => 'Other Tag', 'slug' => 'other-tag']);
        $image = GeneratedMedia::create([
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
        $other = GeneratedMedia::create([
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
        GeneratedMedia::create([
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
        $this->assertDatabaseHas('api_requests', [
            'api_key_id' => $key->id,
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
        $this->assertDatabaseHas('api_requests', [
            'api_key_id' => $key->id,
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
            ->assertJsonPath('message', 'Prompt không phù hợp để tạo hoặc publish ảnh.')
            ->assertJsonPath('error_code', 'IMAGE_REVIEW_BLOCKED_POLITICAL');

        Http::assertNothingSent();
        $key->refresh();
        $this->assertSame(0, $key->quota_used);
        $this->assertDatabaseHas('api_requests', [
            'api_key_id' => $key->id,
            'status_code' => 422,
            'status' => 'validation_failed',
            'quota_charged' => false,
        ]);
    }

    public function test_api_returns_review_unavailable_code_without_charging_quota(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake(fn () => throw new \RuntimeException('Provider unavailable'));
        [$plain, $key] = $this->apiKey(quotaLimit: 2);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images', ['prompt' => 'Tạo ảnh phong cảnh'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Không duyệt được prompt ảnh. Vui lòng thử lại sau.')
            ->assertJsonPath('error_code', 'IMAGE_REVIEW_UNAVAILABLE');

        $key->refresh();
        $this->assertSame(0, $key->quota_used);
        $this->assertDatabaseHas('api_requests', [
            'api_key_id' => $key->id,
            'status_code' => 503,
            'quota_charged' => false,
        ]);
        $this->assertSame('IMAGE_REVIEW_UNAVAILABLE', data_get(ApiRequest::query()->latest('id')->firstOrFail()->response_meta, 'error_code'));
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
        $this->assertDatabaseHas('api_requests', [
            'api_key_id' => $key->id,
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

    public function test_manage_users_list_shows_api_key_usage(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create(['name' => 'Usage User', 'email' => 'usage-user@example.com']);
        $this->apiKey($user, quotaLimit: 50, quotaUsed: 12);

        Livewire::actingAs($admin)
            ->test('pages::manage.users')
            ->assertSee(__('API key usage'))
            ->assertSee('12 / 50')
            ->assertSee('Usage User');
    }

    public function test_admin_can_update_user_avatar_from_user_edit(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::manage.user-edit', ['user' => $user])
            ->set('avatar', UploadedFile::fake()->image('avatar.jpg'))
            ->call('updateAvatar')
            ->assertHasNoErrors();

        $path = $user->refresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_user_edit_shows_daily_api_usage_chart(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create();
        [, $key] = $this->apiKey($user, quotaLimit: 20, quotaUsed: 3);

        foreach ([
            [now(), true],
            [now()->subDay(), true],
            [now()->subDay(), false],
            [now()->subDays(30), true],
        ] as [$createdAt, $charged]) {
            ApiRequest::create([
                'api_key_id' => $key->id,
                'user_id' => $user->id,
                'media_id' => null,
                'ip_address' => '127.0.0.1',
                'status_code' => $charged ? 200 : 422,
                'status' => $charged ? 'succeeded' : 'failed',
                'duration_ms' => 10,
                'quota_charged' => $charged,
                'error' => $charged ? null : 'validation',
                'request_meta' => [],
                'response_meta' => [],
            ])->forceFill(['created_at' => $createdAt])->save();
        }

        $component = Livewire::actingAs($admin)
            ->test('pages::manage.user-edit', ['user' => $user]);

        $dailyApiUsage = $component->get('dailyApiUsage');

        $this->assertCount(30, $dailyApiUsage);
        $this->assertSame(3, collect($dailyApiUsage)->sum('total'));
        $this->assertSame(2, collect($dailyApiUsage)->sum('charged'));
        $this->assertSame(0, $dailyApiUsage[0]['total']);
        $this->assertSame(2, $dailyApiUsage[28]['total']);
        $this->assertSame(1, $dailyApiUsage[29]['total']);
        $component
            ->assertSee(__('API key usage'))
            ->assertSee(__('Last 30 days'))
            ->assertSee(__('Quota charged'))
            ->assertSee(__('Latest logs'))
            ->assertSee('validation')
            ->set('logSearch', 'validation')
            ->assertSee('HTTP 422')
            ->assertDontSee('HTTP 200', false);
    }

    public function test_admin_can_verify_and_change_user_password(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->unverified()->create();

        Livewire::actingAs($admin)
            ->test('pages::manage.user-edit', ['user' => $user])
            ->set('verified', true)
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_admin_can_generate_api_key_for_user(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create();

        $component = Livewire::actingAs($admin)
            ->test('pages::manage.user-edit', ['user' => $user])
            ->call('generateApiKey')
            ->assertHasNoErrors();

        $key = ApiKey::query()->whereBelongsTo($user)->sole();
        $plain = $component->get('newApiToken');

        $this->assertIsString($plain);
        $this->assertSame($key->token_hash, ApiKey::hashToken($plain));
        $this->assertSame(100, $key->quota_limit);
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
        $member = User::factory()->create();
        $key = $this->apiKey($member, quotaLimit: 100, quotaUsed: 20)[1];

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->assertSee(__('Image creation tools'))
            ->assertSeeHtml('md:grid-cols-2')
            ->set('siteName', 'GenAnh Pro')
            ->set('homeTitle', 'Chỉnh ảnh AI miễn phí')
            ->set('googleMeasurementId', 'G-SZ9BZEKLZ1')
            ->set('zaloUrl', 'https://zalo.me/0123456789')
            ->set('registrationEnabled', false)
            ->set('emailVerificationRequired', false)
            ->set('memberRequestLimit', 250)
            ->set('verifiedDailyImageLimit', 12)
            ->set('textModels', ['gpt-5.5', 'gpt-5.5-mini', 'gpt-5.5-translation', 'gpt-5.5-rewrite', 'gpt-5.5-vision'])
            ->set('aiReviewModel', 'gpt-5.5-mini')
            ->set('promptTranslationEnabled', false)
            ->set('promptRewriteEnabled', false)
            ->set('imageToPromptEnabled', false)
            ->set('promptTranslationModel', 'gpt-5.5-translation')
            ->set('promptRewriteModel', 'gpt-5.5-rewrite')
            ->set('imageToPromptModel', 'gpt-5.5-vision')
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
        $this->assertSame('https://zalo.me/0123456789', Setting::getValue('contact.zalo_url'));
        $this->assertFalse((bool) Setting::getValue('auth.registration_enabled'));
        $this->assertFalse((bool) Setting::getValue('auth.email_verification_required'));
        $this->assertSame(250, Setting::getValue('auth.member_request_limit'));
        $this->assertSame(12, Setting::getValue('auth.verified_daily_image_limit'));
        $this->assertSame(100, $key->fresh()->quota_limit);
        $this->assertSame(20, $key->fresh()->quota_used);
        $this->assertSame('gpt-5.5-mini', Setting::getValue('ai.image_review_model'));
        $this->assertFalse((bool) Setting::getValue('ai.prompt_translation_enabled'));
        $this->assertFalse((bool) Setting::getValue('ai.prompt_rewrite_enabled'));
        $this->assertFalse((bool) Setting::getValue('ai.image_to_prompt_enabled'));
        $this->assertSame('gpt-5.5-translation', Setting::getValue('ai.prompt_translation_model'));
        $this->assertSame('gpt-5.5-rewrite', Setting::getValue('ai.prompt_rewrite_model'));
        $this->assertSame('gpt-5.5-vision', Setting::getValue('ai.image_to_prompt_model'));
        $this->assertSame('1536x1024', Setting::getValue('ai.image_size'));
        $this->assertSame('medium', Setting::getValue('ai.image_quality'));
        $this->assertSame('original', Setting::getValue('ai.image_detail'));
        $this->assertSame('input_image', Setting::getValue('ai.image_reference_field'));
        $this->assertSame('secret-key', Setting::getValue('ai.openai_api_key'));
    }

    public function test_settings_reject_invalid_zalo_url(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->set('zaloUrl', 'not-a-url')
            ->call('save')
            ->assertHasErrors('zaloUrl');
    }

    public function test_admin_manages_separate_image_and_text_models(): void
    {
        Http::fake([
            '42.112.31.227:22150/v1/models' => Http::response(['data' => [
                ['id' => 'cx/new-image-model'],
                ['id' => 'gpt-new-text'],
            ]]),
        ]);
        Setting::putValue('ai.openai_api_key', 'saved-key');
        $admin = User::factory()->create(['id' => 1]);

        $component = Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->call('openModelsModal', 'image', 'image')
            ->set('newModelId', 'cx/new-image-model')
            ->call('addModel')
            ->assertSet('aiModel', 'cx/new-image-model')
            ->assertSet('showModelsModal', true)
            ->call('closeModelsModal')
            ->call('openModelsModal', 'text', 'review')
            ->set('newModelId', 'gpt-new-text')
            ->call('addModel')
            ->assertSet('aiReviewModel', 'gpt-new-text')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertContains('cx/new-image-model', Setting::getValue('ai.image_models'));
        $this->assertNotContains('cx/new-image-model', Setting::getValue('ai.text_models'));
        $this->assertContains('gpt-new-text', Setting::getValue('ai.text_models'));
        $this->assertNotContains('gpt-new-text', Setting::getValue('ai.image_models'));
        $this->assertSame('gpt-new-text', Setting::getValue('ai.image_review_model'));

        $component
            ->set('newModelId', 'gpt-new-text')
            ->call('addModel')
            ->assertHasErrors('newModelId');
    }

    public function test_admin_can_add_custom_model_not_in_endpoint_catalog(): void
    {
        Http::fake([
            '42.112.31.227:22150/v1/models' => Http::response(['data' => [['id' => 'gpt-listed']]]),
        ]);
        Setting::putValue('ai.openai_api_key', 'saved-key');
        $admin = User::factory()->create(['id' => 1]);

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->call('openModelsModal', 'text', 'tag')
            ->assertSet('useCustomModelId', false)
            ->set('useCustomModelId', true)
            ->set('newModelId', 'vendor/custom-metadata-model')
            ->call('addModel')
            ->assertSet('tagModel', 'vendor/custom-metadata-model')
            ->assertSet('textModels', fn (array $models): bool => in_array('vendor/custom-metadata-model', $models, true))
            ->assertHasNoErrors();
    }

    public function test_settings_reject_model_selected_from_wrong_registry(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->set('aiModel', 'gpt-5.5')
            ->call('save')
            ->assertHasErrors('aiModel');

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->set('imageToPromptModel', 'cx/gpt-5.5-image')
            ->call('save')
            ->assertHasErrors('imageToPromptModel');
    }

    public function test_models_modal_loads_and_filters_endpoint_catalog(): void
    {
        Http::fake([
            '42.112.31.227:22150/v1/models' => Http::response(['data' => [
                ['id' => 'gpt-5.5'],
                ['id' => 'cx/gpt-5.5-image'],
                ['id' => 'gpt-5.5-mini'],
                ['bad' => 'ignored'],
            ]]),
        ]);
        Setting::putValue('ai.openai_api_key', 'saved-key');
        $admin = User::factory()->create(['id' => 1]);

        $component = Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->call('openModelsModal', 'text', 'review')
            ->assertSet('availableModels', ['cx/gpt-5.5-image', 'gpt-5.5', 'gpt-5.5-mini'])
            ->set('modelSearch', 'mini')
            ->assertSee('gpt-5.5-mini');

        $this->assertSame(['gpt-5.5-mini'], $component->instance()->filteredAvailableModels());

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://42.112.31.227:22150/v1/models'
            && $request->hasHeader('Authorization', 'Bearer saved-key'));
    }

    public function test_models_modal_shows_catalog_error(): void
    {
        Http::fake([
            '42.112.31.227:22150/v1/models' => Http::response(['error' => ['message' => 'Catalog unavailable']], 503),
        ]);
        Setting::putValue('ai.openai_api_key', 'saved-key');
        $admin = User::factory()->create(['id' => 1]);

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->call('openModelsModal', 'text')
            ->assertSet('modelCatalogError', 'Không tải được danh sách model: Catalog unavailable')
            ->assertSee('Không tải được danh sách model: Catalog unavailable');
    }

    public function test_admin_can_test_image_and_text_models(): void
    {
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response(['data' => [['b64_json' => 'image']]]),
            '42.112.31.227:22150/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'OK']]]]),
        ]);
        $admin = User::factory()->create(['id' => 1]);
        Setting::putValue('ai.openai_api_key', 'saved-key');

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->call('testModel', 'image', 'cx/gpt-5.5-image')
            ->assertSet('modelTestStatuses', fn (array $statuses): bool => ($statuses['image:cx/gpt-5.5-image'] ?? null) === 'success')
            ->call('testModel', 'text', 'gpt-5.5')
            ->assertSet('modelTestStatuses', fn (array $statuses): bool => ($statuses['text:gpt-5.5'] ?? null) === 'success')
            ->assertSet('modelTestError', null);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://42.112.31.227:22150/v1/images/generations'
            && $request['model'] === 'cx/gpt-5.5-image'
            && $request->hasHeader('Authorization', 'Bearer saved-key'));
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://42.112.31.227:22150/v1/chat/completions'
            && $request['model'] === 'gpt-5.5');
    }

    public function test_failed_model_test_shows_safe_error(): void
    {
        Http::fake([
            '42.112.31.227:22150/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Model is unavailable'],
            ], 404),
        ]);
        $admin = User::factory()->create(['id' => 1]);
        Setting::putValue('ai.openai_api_key', 'saved-key');

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->call('testModel', 'text', 'gpt-5.5')
            ->assertSet('modelTestStatuses', fn (array $statuses): bool => ($statuses['text:gpt-5.5'] ?? null) === 'error')
            ->assertSet('modelTestError', 'Test model thất bại: Model is unavailable')
            ->assertSee('Test model thất bại: Model is unavailable');
    }

    public function test_admin_key_management_keeps_one_key_per_user(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        $component = Livewire::actingAs($admin)
            ->test('pages::manage.api-keys')
            ->set('quotaLimit', 10)
            ->call('createKey');

        $firstPlain = $component->get('newToken');
        $keyId = ApiKey::query()->where('user_id', $admin->id)->firstOrFail()->id;

        $component
            ->set('quotaLimit', 25)
            ->call('createKey')
            ->assertSee('Danh sách API key')
            ->assertSee('25');

        $secondPlain = $component->get('newToken');

        $this->assertIsString($firstPlain);
        $this->assertIsString($secondPlain);
        $this->assertSame(1, ApiKey::query()->where('user_id', $admin->id)->count());
        $this->assertDatabaseMissing('api_keys', [
            'id' => $keyId,
            'token_hash' => ApiKey::hashToken($firstPlain),
        ]);
        $this->assertDatabaseHas('api_keys', [
            'id' => $keyId,
            'token_hash' => ApiKey::hashToken($secondPlain),
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
        $this->assertStringStartsWith('genanh_', $newPlain);
        $this->assertDatabaseMissing('api_keys', [
            'id' => $targetKey->id,
            'token_hash' => ApiKey::hashToken($oldPlain),
        ]);
        $this->assertDatabaseHas('api_keys', [
            'id' => $targetKey->id,
            'token_hash' => ApiKey::hashToken($newPlain),
        ]);
        $this->assertDatabaseHas('api_keys', [
            'id' => $otherKey->id,
            'token_hash' => ApiKey::hashToken($otherPlain),
        ]);
    }

    public function test_api_key_settings_shows_safe_summary_and_usage_guide(): void
    {
        [$plain, $key] = $this->apiKey(quotaLimit: 5, quotaUsed: 2);
        ApiRequest::create([
            'api_key_id' => $key->id,
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
            ->assertSee('Authorization: Bearer genanh_xxx')
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
    private function publishReview(string $category = 'portraits', array $tags = [], string $title = 'Public avatar portrait', string $description = 'Chân dung avatar studio công khai, ánh sáng mềm, nền sạch, phù hợp hồ sơ và gallery ảnh AI Việt Nam.'): array
    {
        return [
            'allowed' => true,
            'blocked_policy' => 'none',
            'reason' => 'An toàn.',
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'tags' => $tags,
        ];
    }

    /**
     * @return array{0: string, 1: ApiKey}
     */
    private function apiKey(?User $user = null, int $quotaLimit = 100, int $quotaUsed = 0): array
    {
        $user ??= User::factory()->create();
        $token = ApiKey::newToken();
        $key = ApiKey::create([
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
