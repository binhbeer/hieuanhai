<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageLanguagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_language_management(): void
    {
        $this->actingAs(User::factory()->create(['id' => 2]))
            ->get(route('manage.languages.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['id' => 1]))
            ->get(route('manage.languages.index'))
            ->assertOk()
            ->assertSee('Quản lý ngôn ngữ');
    }

    public function test_english_requires_site_seo_before_it_can_be_enabled(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        Livewire::actingAs($admin)
            ->test('pages::manage.languages')
            ->call('toggleEnglish', true)
            ->assertSet('englishEnabled', false)
            ->set('homeTitleEn', 'AI image editor and gallery')
            ->set('siteDescriptionEn', 'Create, edit, publish, and discover AI images with practical tools for visual projects and creative inspiration.')
            ->set('siteKeywordsEn', 'AI image editor, AI image gallery')
            ->call('saveSite')
            ->assertHasNoErrors()
            ->call('toggleEnglish', true)
            ->assertSet('englishEnabled', true);

        $this->assertTrue((bool) Setting::getValue('locales.en.enabled'));
        $this->assertSame('AI image editor and gallery', Setting::getValue('site.home_title.en'));
    }

    public function test_admin_can_save_category_english_translation(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $category = Category::query()->where('slug', 'portraits')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('pages::manage.languages')
            ->call('setTab', 'categories')
            ->call('edit', 'category', $category->id)
            ->assertSet('sourceLabel', 'Chân dung')
            ->set('nameEn', 'Portraits')
            ->set('slugEn', 'portraits')
            ->set('descriptionEn', 'Browse expressive AI portraits with varied lighting, poses, and visual styles for polished creative inspiration.')
            ->call('saveTranslation')
            ->assertHasNoErrors()
            ->assertSet('showEditor', false);

        $category->refresh();
        $this->assertSame('Portraits', $category->getTranslationWithoutFallback('name', 'en'));
        $this->assertSame('portraits', $category->slug_en);
        $this->assertSame('Browse expressive AI portraits with varied lighting, poses, and visual styles for polished creative inspiration.', $category->getTranslationWithoutFallback('description', 'en'));
    }
}
