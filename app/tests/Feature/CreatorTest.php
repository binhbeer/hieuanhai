<?php

namespace Tests\Feature;

use App\Models\GeneratedMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_creator_and_browse_gallery(): void
    {
        $this->get(route('creator.index'))
            ->assertOk()
            ->assertSee(__('Create images from prompts and references'))
            ->assertSee(__('Browse Gallery'))
            ->assertSee('animate-pulse', false);
    }

    public function test_authenticated_creator_does_not_open_composer_automatically(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('creator.index'))
            ->assertOk();

        Livewire::actingAs($user)
            ->test('gallery.creator')
            ->assertSet('showComposer', false);

        Livewire::actingAs($user)
            ->withQueryParams(['composer' => '1'])
            ->test('gallery.creator')
            ->assertSet('showComposer', true);
    }

    public function test_guest_composer_query_requests_login_without_opening(): void
    {
        Livewire::withQueryParams(['composer' => '1'])
            ->test('gallery.creator')
            ->assertSet('showComposer', false)
            ->assertDispatched('open-account-modal');
    }

    public function test_featured_gallery_loads_thirty_six_images_and_links_to_last_image(): void
    {
        collect(range(1, 36))->each(fn (int $number): GeneratedMedia => $this->publishedImage('Featured image '.$number, $number === 36));
        $this->publishedImage('Regular image');

        $component = Livewire::test('creator.featured-gallery')
            ->assertSee('Featured image 1')
            ->assertSee('Featured image 36')
            ->assertDontSee('Regular image');
        $images = $component->get('images');

        $component->assertSee(route('gallery.index', ['sort' => 'featured']).'#image-'.$images->last()->id, false);
        $this->assertCount(36, $images);
    }

    public function test_gallery_cards_have_image_anchors(): void
    {
        $image = $this->publishedImage('Anchor image');

        $this->get(route('gallery.index', ['sort' => 'featured']))
            ->assertSee('id="image-'.$image->id.'"', false);
    }

    private function publishedImage(string $prompt, bool $featured = false): GeneratedMedia
    {
        return GeneratedMedia::create([
            'visitor_key' => 'creator-'.str()->random(12),
            'prompt' => $prompt,
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/creator/'.str()->uuid().'.png',
            'is_published' => true,
            'is_featured' => $featured,
            'published_at' => now(),
        ]);
    }
}
