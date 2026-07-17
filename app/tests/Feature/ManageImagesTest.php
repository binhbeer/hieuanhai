<?php

namespace Tests\Feature;

use App\Ai\ImageMetadataAgent;
use App\Ai\ImageReviewAgent;
use App\Models\Category;
use App\Models\GeneratedMedia;
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
        ImageReviewAgent::fake([['allowed' => true, 'blocked_policy' => 'none', 'reason' => 'An toàn.', 'matches_prompt' => true]]);
        ImageMetadataAgent::fake([['title' => 'Chân dung studio', 'description' => 'Chân dung studio chuyên nghiệp, ánh sáng mềm, nền sạch, phù hợp avatar và hồ sơ công khai.', 'category' => 'portraits', 'tags' => ['chân dung', 'studio', 'avatar', 'chuyên nghiệp']]]);

        $this->actingAs(User::factory()->create(['id' => 1]));
        $category = Category::query()->where('slug', 'ads-product')->firstOrFail();
        $published = GeneratedMedia::create([
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
        $unpublished = GeneratedMedia::create([
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

    public function test_admin_can_filter_images_by_creator(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        $creator = User::factory()->create(['name' => 'Creator Alpha']);
        $otherCreator = User::factory()->create(['name' => 'Creator Beta']);

        foreach ([
            [$creator->id, 'Alpha image'],
            [$otherCreator->id, 'Beta image'],
            [null, 'Guest image'],
        ] as [$userId, $prompt]) {
            GeneratedMedia::create([
                'user_id' => $userId,
                'visitor_key' => 'creator-filter-'.$prompt,
                'prompt' => $prompt,
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => 'succeeded',
            ]);
        }

        Livewire::actingAs($admin)
            ->test('pages::manage.images')
            ->assertSee('Creator Alpha')
            ->set('creatorId', (string) $creator->id)
            ->assertSee('Alpha image')
            ->assertDontSee('Beta image')
            ->assertDontSee('Guest image')
            ->set('creatorId', 'guest')
            ->assertSee('Guest image')
            ->assertDontSee('Alpha image');
    }

    public function test_dashboard_groups_user_and_image_activity_for_the_last_thirty_days(): void
    {
        $admin = User::factory()->create(['id' => 1]);
        User::factory()->create(['created_at' => now()->subDays(2)]);
        User::factory()->unverified()->create(['created_at' => now()->subDays(2)]);
        User::factory()->create(['created_at' => now()->subDays(30)]);

        foreach ([true, false] as $published) {
            $image = GeneratedMedia::create([
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

        $oldImage = GeneratedMedia::create([
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

    public function test_images_page_groups_publish_status_activity_for_the_last_thirty_days(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        foreach ([
            ['published' => true, 'status' => 'succeeded', 'path' => 'ai-images/pub.png'],
            ['published' => false, 'status' => 'succeeded', 'path' => 'ai-images/unpub.png'],
            ['published' => false, 'status' => 'failed', 'path' => null],
        ] as $index => $attrs) {
            $image = GeneratedMedia::create([
                'visitor_key' => 'manage-images-'.$index,
                'prompt' => 'Manage images chart '.$index,
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => $attrs['status'],
                'result_path' => $attrs['path'],
                'is_published' => $attrs['published'],
            ]);
            $image->forceFill(['created_at' => now()->subDay()])->save();
        }

        $oldImage = GeneratedMedia::create([
            'visitor_key' => 'manage-images-old',
            'prompt' => 'Old manage images chart',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/old.png',
            'is_published' => true,
        ]);
        $oldImage->forceFill(['created_at' => now()->subDays(30)])->save();

        $component = Livewire::actingAs($admin)->test('pages::manage.images');
        $stats = $component->get('dailyStats');
        $imageDay = collect($stats)->first(fn (array $day): bool => $day['date']->isSameDay(now()->subDay()));

        $this->assertCount(30, $stats);
        $this->assertSame(3, $imageDay['total']);
        $this->assertSame(1, $imageDay['published']);
        $this->assertSame(1, $imageDay['unpublished']);
        $this->assertSame(1, $imageDay['failed']);
        $this->assertSame(3, collect($stats)->sum('total'));
        $component
            ->assertSee(__('Total images'))
            ->assertSee(__('Published'))
            ->assertSee(__('Unpublished'))
            ->assertSee(__('Failed'))
            ->assertSee(__('Last 30 days'));
    }
}
