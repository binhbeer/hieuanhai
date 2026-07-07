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

    public function test_dashboard_redirects_to_home(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('home', absolute: false));
    }
}
