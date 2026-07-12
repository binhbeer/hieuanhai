<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_visit_home(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertDontSee(__('Search images...'));
    }

    public function test_authenticated_users_can_visit_home(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
    }

    public function test_home_uses_home_title_setting(): void
    {
        Setting::putValue('site.home_title', 'Chỉnh ảnh AI miễn phí');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>', false)
            ->assertSee('Chỉnh ảnh AI miễn phí - GenAnh');
    }

    public function test_home_loads_google_analytics_when_configured(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false);

        Setting::putValue('analytics.google_measurement_id', 'G-SZ9BZEKLZ1');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-SZ9BZEKLZ1', false)
            ->assertSee("gtag('config', 'G-SZ9BZEKLZ1');", false);
    }

    public function test_manage_pages_skip_google_analytics(): void
    {
        Setting::putValue('analytics.google_measurement_id', 'G-SZ9BZEKLZ1');

        $admin = User::factory()->create(['id' => 1]);

        $this->actingAs($admin)->get(route('manage.index'))
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false);
    }

    public function test_guests_must_login_to_visit_search(): void
    {
        $this->get(route('search.index'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_users_can_visit_search(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('search.index'))
            ->assertOk()
            ->assertSee(__('Search images'))
            ->assertSee(__('Search images...'))
            ->assertSee('name="q"', false)
            ->assertDontSee('name="search"', false);

        $this->actingAs($user)->get(route('search.index', ['q' => 'logo']))
            ->assertOk()
            ->assertSee(__('Search results'));
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
