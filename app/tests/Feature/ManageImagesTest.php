<?php

namespace Tests\Feature;

use App\Ai\ImageReviewAgent;
use App\Models\AiImage;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_and_publish_images(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => true, 'category' => 'portraits', 'tags' => ['chân dung', 'studio', 'avatar'], 'reason' => 'An toàn.']]);

        $this->actingAs(User::factory()->create(['id' => 1]));
        $category = Category::query()->where('slug', 'ads-product')->firstOrFail();
        $published = AiImage::create([
            'visitor_key' => 'visitor-a',
            'prompt' => 'Published product ad',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/published.png',
            'category_id' => $category->id,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $unpublished = AiImage::create([
            'visitor_key' => 'visitor-b',
            'prompt' => 'Unpublished portrait',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/unpublished.png',
        ]);

        $this->get(route('manage.images.index'))
            ->assertOk()
            ->assertSee('Quản lý ảnh đã tạo')
            ->assertSee('Published product ad')
            ->assertSee('Unpublished portrait');

        Livewire::test('pages::manage.images')
            ->set('publish', 'published')
            ->assertSee('Published product ad')
            ->assertDontSee('Unpublished portrait')
            ->call('unpublishImage', $published->id)
            ->set('publish', 'unpublished')
            ->assertSee('Published product ad')
            ->assertSee('Unpublished portrait')
            ->call('publishImage', $unpublished->id);

        $this->assertFalse($published->fresh()->is_published);
        $this->assertTrue($unpublished->fresh()->is_published);
    }

    public function test_dashboard_groups_user_and_image_activity_for_the_last_thirty_days(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        User::factory()->create(['created_at' => now()->subDays(2)]);
        User::factory()->unverified()->create(['created_at' => now()->subDays(2)]);
        User::factory()->create(['created_at' => now()->subDays(30)]);

        foreach ([true, false] as $published) {
            $image = AiImage::create([
                'visitor_key' => 'dashboard-'.$published,
                'prompt' => 'Dashboard chart image',
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => 'succeeded',
                'result_path' => 'ai-images/dashboard.png',
                'is_published' => $published,
            ]);
            $image->forceFill(['created_at' => now()->subDay()])->save();
        }

        $oldImage = AiImage::create([
            'visitor_key' => 'dashboard-old',
            'prompt' => 'Old dashboard chart image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/dashboard-old.png',
            'is_published' => true,
        ]);
        $oldImage->forceFill(['created_at' => now()->subDays(30)])->save();

        $stats = Livewire::actingAs($admin)->test('pages::manage.dashboard')->get('dailyStats');
        $userDay = collect($stats)->first(fn (array $day): bool => $day['date']->isSameDay(now()->subDays(2)));
        $imageDay = collect($stats)->first(fn (array $day): bool => $day['date']->isSameDay(now()->subDay()));

        $this->assertCount(30, $stats);
        $this->assertSame(2, $userDay['users']);
        $this->assertSame(1, $userDay['verified_users']);
        $this->assertSame(2, $imageDay['images']);
        $this->assertSame(1, $imageDay['published_images']);
        $this->assertSame(3, collect($stats)->sum('users'));
        $this->assertSame(2, collect($stats)->sum('images'));
    }
}
