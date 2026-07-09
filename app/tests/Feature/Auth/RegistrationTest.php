<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_registration_can_be_disabled(): void
    {
        Setting::putValue('auth.registration_enabled', false);

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ])->assertSessionHasErrors('email');
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => UserRole::User->value,
        ]);
    }

    public function test_registration_password_must_be_at_least_six_characters(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'short@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('register'));

        $this->assertGuest();
    }

    public function test_new_users_can_register_without_email_verification_when_disabled(): void
    {
        Setting::putValue('auth.email_verification_required', false);

        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'no-verify@example.com',
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('home', absolute: false));

        $user = User::query()->where('email', 'no-verify@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->email_verified_at);
    }
}
