<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\LocalizedRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_pages_are_public_and_include_expected_content(): void
    {
        $pages = [
            'guide.index' => 'Choose your path',
            'guide.getting-started' => 'Create your first AI image',
            'guide.web' => 'Manage your complete image workflow',
            'guide.api' => 'Create images through the API',
            'guide.faq' => 'Frequently asked questions',
        ];

        foreach ($pages as $route => $heading) {
            $response = $this->get(route($route));

            $response
                ->assertOk()
                ->assertSee(__($heading))
                ->assertSee(__('User guide'))
                ->assertSee('<meta name="robots" content="index,follow,max-image-preview:large">', false);

            if ($route !== 'guide.faq') {
                $response->assertSee('loading="lazy"', false);
            }
        }
    }

    public function test_english_guide_uses_translated_url_canonical_and_reciprocal_hreflang(): void
    {
        Setting::putValue('locales.en.enabled', true);

        $viUrl = LocalizedRoute::url('guide.web', locale: 'vi');
        $enUrl = LocalizedRoute::url('guide.web', locale: 'en');

        $this->assertStringContainsString('/huong-dan/ung-dung-web', $viUrl);
        $this->assertStringContainsString('/en/guide/web-app', $enUrl);

        $this->get($enUrl)
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('<link rel="canonical" href="'.$enUrl.'">', false)
            ->assertSee('<link rel="alternate" hreflang="vi" href="'.$viUrl.'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.$enUrl.'">', false)
            ->assertSee('Manage your complete image workflow')
            ->assertSee('href="'.$viUrl.'">Tiếng Việt</a>', false);
    }

    public function test_guide_is_linked_from_user_menu_but_not_public_sidebar(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('guide.index'), false);

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('guide.index'), false)
            ->assertSeeInOrder([__('Appearance'), __('User guide'), __('Log out')]);
    }
}
