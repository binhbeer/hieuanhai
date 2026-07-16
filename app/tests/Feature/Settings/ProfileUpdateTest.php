<?php

namespace Tests\Feature\Settings;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\Setting;
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

        $response = Livewire::test('settings.profile')
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

        Livewire::test('settings.profile')
            ->set('avatar', UploadedFile::fake()->image('avatar.jpg'))
            ->call('updateAvatar')
            ->assertHasNoErrors();

        $path = $user->refresh()->avatar_path;
        $this->assertNotNull($path);
        $this->assertMatchesRegularExpression('#^image/user/\d{6}/\d{2}/\d+/\d+/[\w-]+\.jpg$#', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('settings.profile')
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

    public function test_api_key_settings_show_configured_upgrade_link(): void
    {
        $user = User::factory()->create();
        Setting::putValue('contact.zalo_url', 'https://zalo.me/0123456789');

        Livewire::actingAs($user)
            ->test('settings.api-key')
            ->assertSee(__('Upgrade quota'))
            ->assertSee('https://zalo.me/0123456789', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);

        Setting::putValue('contact.zalo_url', false);

        Livewire::actingAs($user)
            ->test('settings.api-key')
            ->assertDontSee(__('Upgrade quota'));
    }

    public function test_user_can_generate_one_settings_api_key_and_view_stats(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test('settings.api-key')
            ->call('generateApiKey')
            ->assertHasNoErrors()
            ->assertDispatched('api-key-updated');

        $firstPlain = $component->get('newApiToken');
        $key = ApiKey::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertIsString($firstPlain);
        $this->assertStringStartsWith('genanh_', $firstPlain);
        $this->assertSame(100, $key->quota_limit);

        $key->update(['quota_used' => 1]);
        ApiRequest::create([
            'api_key_id' => $key->id,
            'user_id' => $user->id,
            'status_code' => 201,
            'status' => 'succeeded',
            'duration_ms' => 123,
            'quota_charged' => true,
        ]);

        Livewire::test('settings.api-key')
            ->assertSee('HTTP 201')
            ->assertSee('99');

        $component
            ->call('generateApiKey')
            ->assertSee($component->get('newApiToken'), false)
            ->assertSee(substr((string) $component->get('newApiToken'), 0, 12), false);

        $secondPlain = $component->get('newApiToken');

        $this->assertIsString($secondPlain);
        $this->assertNotSame($firstPlain, $secondPlain);
        $this->assertSame(1, ApiKey::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('api_keys', [
            'id' => $key->id,
            'token_hash' => ApiKey::hashToken($firstPlain),
        ]);
        $this->assertDatabaseHas('api_keys', [
            'id' => $key->id,
            'token_hash' => ApiKey::hashToken($secondPlain),
        ]);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('settings.delete-user-modal')
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

        $response = Livewire::test('settings.delete-user-modal')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }
}
