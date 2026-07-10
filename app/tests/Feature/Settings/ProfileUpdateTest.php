<?php

namespace Tests\Feature\Settings;

use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_avatar_can_be_updated(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('pages::settings.profile')
            ->set('avatar', UploadedFile::fake()->image('avatar.jpg'))
            ->call('updateAvatar')
            ->assertHasNoErrors();

        $path = $user->refresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_api_key_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('api-key.edit'))->assertOk()->assertSee('API key');
    }

    public function test_user_can_generate_one_settings_api_key_and_view_stats(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test('pages::settings.api-key')
            ->call('generateApiKey')
            ->assertHasNoErrors();

        $firstPlain = $component->get('newApiToken');
        $key = AiApiKey::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertIsString($firstPlain);
        $this->assertStringStartsWith('hai_', $firstPlain);
        $this->assertSame(100, $key->quota_limit);

        $key->update(['quota_used' => 1]);
        AiApiRequest::create([
            'ai_api_key_id' => $key->id,
            'user_id' => $user->id,
            'status_code' => 201,
            'status' => 'succeeded',
            'duration_ms' => 123,
            'quota_charged' => true,
        ]);

        Livewire::test('pages::settings.api-key')
            ->assertSee('HTTP 201')
            ->assertSee('99');

        $component->call('generateApiKey');
        $secondPlain = $component->get('newApiToken');

        $this->assertIsString($secondPlain);
        $this->assertSame(1, AiApiKey::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('ai_api_keys', [
            'id' => $key->id,
            'token_hash' => AiApiKey::hashToken($firstPlain),
        ]);
        $this->assertDatabaseHas('ai_api_keys', [
            'id' => $key->id,
            'token_hash' => AiApiKey::hashToken($secondPlain),
        ]);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertFalse(auth()->check());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }
}
