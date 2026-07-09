<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_sort_edit_and_hide_categories(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1]));

        $first = Category::query()->orderBy('sort_order')->firstOrFail();
        $second = Category::query()->orderBy('sort_order')->skip(1)->firstOrFail();

        $this->get(route('manage.categories.index'))
            ->assertOk()
            ->assertSee('Quản lý danh mục')
            ->assertSee($first->name);

        Livewire::test('pages::manage.categories')
            ->call('sortCategory', $second->id, 0)
            ->call('edit', $first->id)
            ->set('name', 'Danh mục test')
            ->set('slug', 'danh-muc-test')
            ->set('status', 'hidden')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertLessThan($first->fresh()->sort_order, $second->fresh()->sort_order);
        $this->assertDatabaseHas('categories', [
            'id' => $first->id,
            'name' => 'Danh mục test',
            'slug' => 'danh-muc-test',
            'status' => 'hidden',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Danh mục test');

        $this->get(route('categories.show', $first->fresh()))
            ->assertNotFound();
    }

    public function test_admin_can_create_category_with_auto_slug(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1]));

        Livewire::test('pages::manage.categories')
            ->set('newName', 'Meme mới')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('newName', '');

        $this->assertDatabaseHas('categories', [
            'name' => 'Meme mới',
            'slug' => 'meme-moi',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_category_with_custom_slug_and_hidden_status(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1]));

        Livewire::test('pages::manage.categories')
            ->set('newName', 'Thể loại ẩn')
            ->set('newSlug', 'hidden-cat')
            ->set('newStatus', 'hidden')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Thể loại ẩn',
            'slug' => 'hidden-cat',
            'status' => 'hidden',
        ]);
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1]));

        Category::query()->firstOrFail()->update(['slug' => 'taken']);

        Livewire::test('pages::manage.categories')
            ->set('newName', 'Trùng slug')
            ->set('newSlug', 'taken')
            ->call('create')
            ->assertHasErrors(['newSlug']);
    }

    public function test_non_admin_cannot_open_category_management(): void
    {
        $this->actingAs(User::factory()->create(['id' => 2]));

        $this->get(route('manage.categories.index'))
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Lén tạo']);
    }
}
