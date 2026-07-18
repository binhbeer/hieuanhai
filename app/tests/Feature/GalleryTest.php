<?php

namespace Tests\Feature;

use App\Models\GeneratedMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_preview_appears_on_home_and_gallery_keeps_its_canonical(): void
    {
        GeneratedMedia::create([
            'visitor_key' => 'gallery-route',
            'prompt' => 'Published gallery image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/gallery.png',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('Featured from the Gallery'));

        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertSee('Published gallery image')
            ->assertSee('<link rel="canonical" href="'.route('gallery.index').'">', false)
            ->assertSee('"@type":"CollectionPage"', false);
    }

    public function test_gallery_navigation_skips_private_and_failed_images_even_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $previous = $this->publishedImage('Previous public image');
        GeneratedMedia::create([
            'user_id' => $admin->id,
            'visitor_key' => 'gallery-failed',
            'prompt' => 'Failed image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'failed',
        ]);
        GeneratedMedia::create([
            'user_id' => $admin->id,
            'visitor_key' => 'gallery-private',
            'prompt' => 'Private image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/private.png',
        ]);
        $selected = $this->publishedImage('Selected public image');
        $next = $this->publishedImage('Next public image');

        $navigation = Livewire::actingAs($admin)
            ->test('gallery.detail', ['selectedImageId' => $selected->id, 'standalone' => true])
            ->get('navigationImages');

        $this->assertSame($next->id, $navigation['previous']?->id);
        $this->assertSame($previous->id, $navigation['next']?->id);
    }

    private function publishedImage(string $prompt): GeneratedMedia
    {
        return GeneratedMedia::create([
            'visitor_key' => 'gallery-'.str()->random(8),
            'prompt' => $prompt,
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/'.str()->uuid().'.png',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
