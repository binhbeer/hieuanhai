<?php

namespace Tests\Feature;

use App\Ai\CategoryDescriptionAgent;
use App\Jobs\GenerateCategoryDescription;
use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
            ->assertSet('showEditFlyout', true)
            ->set('name', 'Danh mục test')
            ->set('slug', 'danh-muc-test')
            ->set('description', 'Mô tả SEO mới hiển thị ngay trong danh sách sau khi lưu danh mục.')
            ->set('status', 'hidden')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showEditFlyout', false)
            ->assertSee('Mô tả SEO mới hiển thị ngay trong danh sách');

        $this->assertLessThan($first->fresh()->sort_order, $second->fresh()->sort_order);
        $this->assertDatabaseHas('categories', [
            'id' => $first->id,
            'name->vi' => 'Danh mục test',
            'slug' => 'danh-muc-test',
            'description->vi' => 'Mô tả SEO mới hiển thị ngay trong danh sách sau khi lưu danh mục.',
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
            'name->vi' => 'Meme mới',
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
            'name->vi' => 'Thể loại ẩn',
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

    public function test_category_page_uses_saved_description(): void
    {
        $description = 'Danh mục Chân dung tuyển chọn hình ảnh con người với biểu cảm, ánh sáng và phong cách đa dạng, giúp bạn nhanh chóng tìm cảm hứng sáng tạo.';
        $category = Category::query()->where('slug', 'portraits')->firstOrFail();
        $category->update(['description' => $description]);

        $this->get(route('categories.show', $category))
            ->assertOk()
            ->assertSee('<meta name="description" content="'.$description.'">', false);
    }

    public function test_category_description_job_writes_human_seo_copy(): void
    {
        $description = 'Danh mục Chân dung tuyển chọn hình ảnh con người với biểu cảm, ánh sáng và phong cách đa dạng, giúp bạn nhanh chóng tìm cảm hứng sáng tạo.';
        Setting::putValue('ai.openai_api_key', 'test-key');
        CategoryDescriptionAgent::fake([['description' => $description]]);
        $category = Category::query()->where('slug', 'portraits')->firstOrFail();
        $image = GeneratedMedia::create([
            'category_id' => $category->id,
            'visitor_key' => 'category-seo-job',
            'title' => 'Chân dung studio ánh sáng mềm',
            'prompt' => 'Chân dung studio ánh sáng mềm',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/category-seo.png',
            'is_published' => true,
            'published_at' => now(),
        ]);

        (new GenerateCategoryDescription($category->id))->handle();

        $this->assertSame($description, $category->fresh()->description);
        CategoryDescriptionAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Tên danh mục: Chân dung')
            && str_contains($prompt->prompt, $image->title));
    }

    public function test_category_description_job_accepts_short_non_empty_copy(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        CategoryDescriptionAgent::fake([['description' => 'Mô tả ngắn nhưng hợp lệ.']]);
        $category = Category::query()->where('slug', 'portraits')->firstOrFail();
        $category->update(['description' => null]);

        (new GenerateCategoryDescription($category->id))->handle();

        $this->assertSame('Mô tả ngắn nhưng hợp lệ.', $category->fresh()->description);
    }

    public function test_backfill_command_queues_only_active_missing_descriptions(): void
    {
        Bus::fake([GenerateCategoryDescription::class]);
        $missing = Category::query()->where('slug', 'portraits')->firstOrFail();
        Category::query()->whereKeyNot($missing->id)->update(['description' => 'Mô tả đã có.']);
        Category::query()->where('slug', 'other')->update(['description' => null, 'status' => 'hidden']);

        $this->artisan('categories:backfill-descriptions')
            ->expectsOutput('Queued 1 category descriptions.')
            ->assertSuccessful();

        Bus::assertDispatched(GenerateCategoryDescription::class, fn (GenerateCategoryDescription $job): bool => $job->categoryId === $missing->id);
        Bus::assertDispatchedTimes(GenerateCategoryDescription::class, 1);
    }

    public function test_non_admin_cannot_open_category_management(): void
    {
        $this->actingAs(User::factory()->create(['id' => 2]));

        $this->get(route('manage.categories.index'))
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Lén tạo']);
    }
}
