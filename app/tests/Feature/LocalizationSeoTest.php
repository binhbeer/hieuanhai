<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\Tag;
use App\Support\LocalizedRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalizationSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_routes_are_unavailable_while_locale_is_disabled(): void
    {
        Setting::putValue('locales.en.enabled', false);

        $this->get('/en')->assertNotFound();
        $this->get('/')->assertOk();
    }

    public function test_ready_category_uses_english_slug_canonical_and_reciprocal_hreflang(): void
    {
        Setting::putValue('locales.en.enabled', true);
        Setting::putValue('site.home_title.en', 'AI image editor and gallery');
        Setting::putValue('site.description.en', 'Create, edit, publish, and discover AI images with practical tools for visual projects and creative inspiration.');
        $category = Category::query()->where('slug', 'portraits')->firstOrFail();
        $category
            ->setTranslation('name', 'en', 'Portraits')
            ->setTranslation('description', 'en', 'Browse expressive AI portraits with varied lighting, poses, and visual styles for polished creative inspiration.')
            ->forceFill(['slug_en' => 'portraits'])
            ->save();
        GeneratedMedia::create([
            'category_id' => $category->id,
            'title' => ['vi' => 'Chân dung studio', 'en' => 'Studio portrait'],
            'description' => ['vi' => 'Chân dung studio ánh sáng mềm.', 'en' => 'A studio portrait with soft lighting and a clean background for polished visual inspiration.'],
            'visitor_key' => 'seo-ready',
            'prompt' => 'Studio portrait',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/seo-ready.png',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $viUrl = route('categories.show', $category);
        app()->setLocale('en');
        $enUrl = route('categories.show', $category);
        app()->setLocale('vi');

        $this->assertStringContainsString('/en/categories/portraits', $enUrl);
        $this->get($enUrl)
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('<link rel="canonical" href="'.$enUrl.'">', false)
            ->assertSee('<link rel="alternate" hreflang="vi" href="'.$viUrl.'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.$enUrl.'">', false)
            ->assertSee('href="'.$viUrl.'"', false)
            ->assertSee('aria-label="Tiếng Việt"', false)
            ->assertDontSee('/vi/c/', false)
            ->assertSee('"inLanguage":"en"', false)
            ->assertSee('Browse expressive AI portraits');
    }

    public function test_untranslated_entity_is_not_available_on_english_route(): void
    {
        Setting::putValue('locales.en.enabled', true);
        $category = Category::query()->where('slug', 'portraits')->firstOrFail();

        $this->get('/en/categories/'.$category->slug)->assertNotFound();
    }

    public function test_guest_language_switch_on_untranslated_tag_page_does_not_crash(): void
    {
        Setting::putValue('locales.en.enabled', true);

        $tag = Tag::query()->create([
            'name' => ['vi' => 'Studio'],
            'slug' => 'studio-guest-switch',
            'description' => ['vi' => 'Tag studio tiếng Việt'],
        ]);

        $this->get(route('tags.show', $tag))
            ->assertOk()
            ->assertDontSee('>English</a>', false);
    }

    public function test_language_switch_falls_back_home_when_livewire_context_loses_route_parameters(): void
    {
        Setting::putValue('locales.en.enabled', true);

        Route::get('/__test/livewire-route-context', function (): string {
            request()->route()?->name('translated_en.tags.show');

            return LocalizedRoute::currentUrl('en');
        });

        $this->get('/__test/livewire-route-context')
            ->assertOk()
            ->assertSee(LocalizedRoute::url('home', locale: 'en'));
    }

    public function test_private_skills_variant_is_noindex(): void
    {
        $this->get(route('studio.index', ['view' => 'projects']))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }
}
