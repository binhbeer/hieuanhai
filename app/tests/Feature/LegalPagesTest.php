<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\LocalizedRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_legal_pages_are_public_indexable_and_linked(): void
    {
        foreach ([
            'legal.privacy' => 'Privacy Policy',
            'legal.terms' => 'Terms of Service',
            'legal.support' => 'Support',
            'legal.delete-account' => 'Delete Account',
        ] as $route => $title) {
            $url = route($route);

            $this->get($url)
                ->assertOk()
                ->assertSee(__($title))
                ->assertSee('<link rel="canonical" href="'.$url.'">', false)
                ->assertSee('<meta name="robots" content="index,follow,max-image-preview:large">', false);
        }
    }

    public function test_privacy_policy_discloses_third_party_ai_and_face_data(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee(__('Third-party AI image processing'))
            ->assertSee(__('Face data in uploaded photos'))
            ->assertSee(__('Before GenAnh sends prompt or image data to the AI service, the app asks for your permission with an in-app consent control. If you do not agree, GenAnh will not send that request. Providers may process data in other countries under their safeguards and applicable law, and must use the data only to deliver contracted services.'))
            ->assertSee(__('GenAnh does not use Face ID, TrueDepth, face geometry APIs, biometric templates, or facial recognition to identify people. The app does not create a faceprint or use face data for authentication, advertising, or surveillance.'))
            ->assertSee(__('Last updated: July 20, 2026'));
    }

    public function test_creator_requires_ai_data_consent_before_create(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('gallery.creator')
            ->set('showComposer', true)
            ->set('prompt', 'a red bicycle on a sunny street')
            ->set('aiDataConsent', false)
            ->call('createImage')
            ->assertHasErrors(['aiDataConsent']);
    }

    public function test_english_legal_routes_use_public_slugs(): void
    {
        Setting::putValue('locales.en.enabled', true);

        foreach ([
            'legal.privacy' => '/en/privacy-policy',
            'legal.terms' => '/en/terms-of-service',
            'legal.support' => '/en/support',
            'legal.delete-account' => '/en/delete-account',
        ] as $route => $path) {
            $url = LocalizedRoute::url($route, locale: 'en');

            $this->assertStringEndsWith($path, $url);
            $this->get($url)->assertOk()->assertSee('lang="en"', false);
        }
    }

    public function test_navigation_registration_and_deletion_paths_are_discoverable(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.support'), false)
            ->assertSee(route('legal.delete-account'), false);

        $this->get(route('legal.delete-account'))
            ->assertOk()
            ->assertSee(__('Sign in to delete your account'))
            ->assertSee("component: 'auth.login'", false);

        Livewire::test('auth.register')
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false);

        $user = User::factory()->unverified()->create();
        $this->actingAs($user)
            ->get(route('legal.delete-account'))
            ->assertOk()
            ->assertSee('data-test="delete-user-button"', false);

        $this->get(route('home'))
            ->assertSee(__('Privacy Policy'))
            ->assertSee(__('Terms of Service'));
    }

    public function test_support_normalizes_legacy_zalo_url_to_https(): void
    {
        Setting::putValue('contact.zalo_url', 'http://zalo.me/0963559309');

        $this->get(route('legal.support'))
            ->assertOk()
            ->assertSee('href="https://zalo.me/0963559309"', false)
            ->assertDontSee('href="http://zalo.me/0963559309"', false);
    }

    public function test_sitemap_contains_legal_pages(): void
    {
        Artisan::call('sitemap:generate');
        $sitemap = file_get_contents(public_path('sitemap-pages.xml'));

        $this->assertIsString($sitemap);
        $this->assertStringContainsString(route('legal.privacy'), $sitemap);
        $this->assertStringContainsString(route('legal.terms'), $sitemap);
        $this->assertStringContainsString(route('legal.support'), $sitemap);
        $this->assertStringContainsString(route('legal.delete-account'), $sitemap);
    }
}
