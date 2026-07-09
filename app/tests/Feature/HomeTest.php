<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_visit_home(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_authenticated_users_can_visit_home(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
    }

    public function test_regular_user_sees_daily_image_quota(): void
    {
        $user = User::factory()->create(['id' => 2]);

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee(__('Remaining today'))
            ->assertSee('5/5')
            ->assertSee(__(':remaining/:limit image generations left today.', ['remaining' => 5, 'limit' => 5]));
    }

    public function test_admin_sees_unlimited_daily_image_quota(): void
    {
        $admin = User::factory()->unverified()->create(['id' => 1, 'created_at' => now()->subDay()]);

        $this->actingAs($admin)->get(route('home'))
            ->assertOk()
            ->assertSee(__('Remaining today'))
            ->assertSee(__('Unlimited'))
            ->assertSee(__('Admin accounts are not limited by daily image quota.'));
    }

    public function test_dashboard_redirects_to_home(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('home', absolute: false));
    }
}
