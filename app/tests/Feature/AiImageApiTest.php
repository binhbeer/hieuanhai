<?php

namespace Tests\Feature;

use App\Ai\ImageReviewAgent;
use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
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
        $this->assertDatabaseHas('ai_api_requests', [
            'ai_api_key_id' => $key->id,
            'status_code' => 201,
            'status' => 'succeeded',
            'quota_charged' => true,
        ]);
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

    public function test_api_rejected_prompt_does_not_charge_quota_or_call_image_api(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => false, 'reason' => 'Không phù hợp.']]);
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

    public function test_api_allows_safe_profile_image_edit_false_positive(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => false, 'reason' => 'Không phù hợp.']]);
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);
        [$plain, $key] = $this->apiKey(quotaLimit: 2);

        $this
            ->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/ai/images', [
                'prompt' => 'Tác phẩm chỉnh sửa ảnh 3D sáng tạo, mô phỏng giao diện hồ sơ mạng xã hội hiện đại, nhân vật khác.',
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

    public function test_admin_can_update_settings(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        Livewire::actingAs($admin)
            ->test('pages::manage.settings')
            ->set('siteName', 'ChinhAnh Pro')
            ->set('registrationEnabled', false)
            ->set('emailVerificationRequired', false)
            ->set('aiReviewModel', 'gpt-5.5-mini')
            ->set('promptRewriteModel', 'gpt-5.5-rewrite')
            ->set('imageReferenceField', 'input_image')
            ->set('openaiApiKey', 'secret-key')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('ChinhAnh Pro', Setting::getValue('site.name'));
        $this->assertFalse((bool) Setting::getValue('auth.registration_enabled'));
        $this->assertFalse((bool) Setting::getValue('auth.email_verification_required'));
        $this->assertSame('gpt-5.5-mini', Setting::getValue('ai.image_review_model'));
        $this->assertSame('gpt-5.5-rewrite', Setting::getValue('ai.prompt_rewrite_model'));
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

    public function test_quota_check_shows_safe_summary_for_valid_key(): void
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

        Livewire::test('pages::quota-check')
            ->set('token', $plain)
            ->call('check')
            ->assertSee('Hướng dẫn sử dụng API')
            ->assertSee('POST')
            ->assertSee('/api/ai/images')
            ->assertSee('Authorization: Bearer hai_xxx')
            ->assertSee('Còn lại')
            ->assertSee('3')
            ->assertSee('HTTP 201')
            ->assertDontSee('hidden prompt')
            ->assertDontSee('hidden meta');
    }

    /**
     * @return array{allowed: bool, reason: string}
     */
    private function allowedReview(): array
    {
        return ['allowed' => true, 'reason' => 'An toàn.'];
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
