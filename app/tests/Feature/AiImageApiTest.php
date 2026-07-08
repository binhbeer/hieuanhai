<?php

namespace Tests\Feature;

use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        config([
            'ai.default_for_images' => 'openai',
            'ai.image_model' => 'cx/gpt-5.5-image',
            'ai.providers.openai.url' => 'http://42.112.31.227:22150/v1',
            'ai.providers.openai.key' => 'test-key',
        ]);
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

    public function test_only_user_one_can_open_api_key_management(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('api-keys.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('api-keys.index'))->assertOk()->assertSee('Quản lý API key');
    }

    public function test_admin_can_create_multiple_keys_with_different_quotas(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        $component = Livewire::actingAs($admin)
            ->test('pages::api-keys')
            ->set('quotaLimit', 10)
            ->call('createKey');

        $firstPlain = $component->get('newToken');

        $component
            ->set('quotaLimit', 25)
            ->call('createKey')
            ->assertSee('Danh sách API key')
            ->assertSee('10')
            ->assertSee('25');

        $this->assertIsString($firstPlain);
        $this->assertSame(2, AiApiKey::query()->where('user_id', $admin->id)->count());
        $this->assertDatabaseHas('ai_api_keys', ['user_id' => $admin->id, 'quota_limit' => 10]);
        $this->assertDatabaseHas('ai_api_keys', ['user_id' => $admin->id, 'quota_limit' => 25]);
    }

    public function test_regenerate_key_replaces_only_that_token(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        [$oldPlain, $targetKey] = $this->apiKey($admin);
        [$otherPlain, $otherKey] = $this->apiKey($admin);

        $component = Livewire::actingAs($admin)
            ->test('pages::api-keys')
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
            ->assertSee('Còn lại')
            ->assertSee('3')
            ->assertSee('HTTP 201')
            ->assertDontSee('hidden prompt')
            ->assertDontSee('hidden meta');
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
            'quota_limit' => $quotaLimit,
            'quota_used' => $quotaUsed,
        ]);

        return [$token['plain'], $key];
    }
}
