<?php

namespace Tests\Feature;

use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_visit_home(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('Quick'))
            ->assertSee(route('quick.index'), false)
            ->assertSee(route('creator.index'), false)
            ->assertSee(route('studio.index'), false)
            ->assertSee(route('gallery.index'), false)
            ->assertSee(route('guide.api'), false)
            ->assertSee(__('Create, edit, and discover images with GenAnh'))
            ->assertDontSee(route('search.index'), false);
    }

    public function test_authenticated_users_can_visit_home(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
    }

    public function test_home_featured_gallery_is_lazy_and_limited_to_public_images(): void
    {
        GeneratedMedia::create([
            'visitor_key' => 'home-featured',
            'prompt' => 'Featured home image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/featured.png',
            'is_published' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);
        foreach (range(1, 12) as $number) {
            GeneratedMedia::create([
                'visitor_key' => 'home-public-'.$number,
                'prompt' => 'Public home image '.$number,
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => 'succeeded',
                'result_path' => 'ai-images/public-'.$number.'.png',
                'is_published' => true,
                'published_at' => now()->subMinutes($number),
            ]);
        }
        GeneratedMedia::create([
            'visitor_key' => 'home-private',
            'prompt' => 'Private home image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/private.png',
        ]);

        $component = Livewire::withoutLazyLoading()->test('home.featured-gallery');

        $this->assertCount(10, $component->get('images'));
        $component->assertSee('featured.png', false);
        $component->assertDontSee('public-12.png', false);
        $component->assertDontSee('private.png', false);
    }

    public function test_home_uses_home_title_setting(): void
    {
        Setting::putValue('site.home_title', 'Chỉnh ảnh AI miễn phí');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>', false)
            ->assertSee('Chỉnh ảnh AI miễn phí - GenAnh');
    }

    public function test_home_exposes_brand_logo_social_metadata_and_schema(): void
    {
        Setting::putValue('site.name', 'Gen Ảnh');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.asset('logo.png'), false)
            ->assertSee('<meta name="twitter:image" content="'.asset('logo.png'), false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"publisher":{"@id":"'.route('home').'#organization"}', false)
            ->assertSee('alt="Gen Ảnh"', false);
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

    public function test_search_route_redirects_to_gallery(): void
    {
        $this->get(route('search.index'))
            ->assertRedirect(route('gallery.index', absolute: false));

        $this->get(route('search.index', ['q' => 'logo']))
            ->assertRedirect(route('gallery.index', ['q' => 'logo'], absolute: false));
    }

    public function test_gallery_includes_search_field(): void
    {
        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertSee(__('Search images...'))
            ->assertSee('wire:model.live.debounce.300ms="search"', false);

        $this->get(route('gallery.index', ['q' => 'logo']))
            ->assertOk()
            ->assertSee(__('Search results'))
            ->assertSee(__('Results for “:query”', ['query' => 'logo']));
    }

    public function test_regular_user_sees_daily_image_quota(): void
    {
        $user = User::factory()->create(['id' => 2]);

        $this->actingAs($user)->get(route('history.index'))
            ->assertOk()
            ->assertSee(__('Remaining today'))
            ->assertSee('5/5')
            ->assertSee(__(':remaining/:limit image generations left today.', ['remaining' => 5, 'limit' => 5]));
    }

    public function test_admin_sees_unlimited_daily_image_quota(): void
    {
        $admin = User::factory()->unverified()->create(['id' => 1, 'created_at' => now()->subDay()]);

        $this->actingAs($admin)->get(route('history.index'))
            ->assertOk()
            ->assertSee(__('Remaining today'))
            ->assertSee('∞')
            ->assertSee(__('Admin accounts are not limited by daily image quota.'));
    }

    public function test_dashboard_redirects_to_home(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('home', absolute: false));
    }
}
