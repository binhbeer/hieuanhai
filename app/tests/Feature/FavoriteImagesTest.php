<?php

namespace Tests\Feature;

use App\Models\GeneratedMedia;
use App\Models\MediaFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class FavoriteImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_media_schema_and_models_use_renamed_tables(): void
    {
        $image = $this->publishedImage('Schema media image');
        $user = User::factory()->create();
        $favorite = MediaFavorite::create(['user_id' => $user->id, 'media_id' => $image->id]);

        $this->assertTrue(Schema::hasTable('generated_media'));
        $this->assertTrue(Schema::hasTable('media_favorites'));
        $this->assertTrue(Schema::hasTable('media_tag'));
        $this->assertTrue(Schema::hasTable('tags'));
        $this->assertTrue(Schema::hasTable('api_keys'));
        $this->assertTrue(Schema::hasTable('api_requests'));

        foreach (['ai_images', 'ai_image_favorites', 'ai_image_tag', 'ai_tags', 'ai_api_keys', 'ai_api_requests'] as $legacyTable) {
            $this->assertFalse(Schema::hasTable($legacyTable));
        }

        $this->assertTrue($favorite->media->is($image));
    }

    public function test_user_can_favorite_gallery_images_and_view_favorites_page(): void
    {
        $user = User::factory()->create();
        $image = $this->publishedImage('Favorite product image');
        $other = $this->publishedImage('Other public image');

        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertSee('Nổi bật')
            ->assertSee('Mới')
            ->assertSee('Ảnh yêu thích');

        $this->actingAs($user)
            ->get(route('images.show', $image))
            ->assertOk()
            ->assertSee('Yêu thích')
            ->assertSee('0')
            ->assertSee('href="'.route('gallery.index').'"', false);

        Livewire::actingAs($user)
            ->test('pages::gallery')
            ->call('toggleFavorite', $image->id);

        $this->assertSame(1, GeneratedMedia::query()->findOrFail($image->id)->favorites_count);

        $this->actingAs($user)
            ->get(route('images.show', $image))
            ->assertOk();

        $this->assertDatabaseHas('media_favorites', [
            'user_id' => $user->id,
            'media_id' => $image->id,
        ]);

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('Ảnh yêu thích')
            ->assertSee('Favorite product image')
            ->assertDontSee('Other public image');

        Livewire::actingAs($user)
            ->test('pages::favorites')
            ->call('removeFavorite', $image->id);

        $this->assertDatabaseMissing('media_favorites', [
            'user_id' => $user->id,
            'media_id' => $image->id,
        ]);
        $this->assertSame(0, GeneratedMedia::query()->findOrFail($image->id)->favorites_count);
    }

    public function test_guest_gallery_actions_open_login_modal(): void
    {
        $image = $this->publishedImage('Guest action image');

        Livewire::test('gallery.detail')
            ->call('useAsPrompt', $image->id)
            ->assertDispatched('open-account-modal')
            ->call('toggleFavorite', $image->id)
            ->assertDispatched('open-account-modal')
            ->assertNoRedirect();
    }

    public function test_image_detail_links_do_not_expose_return_url(): void
    {
        $user = User::factory()->create();
        $image = $this->publishedImage('Favorite product image');

        MediaFavorite::create(['user_id' => $user->id, 'media_id' => $image->id]);

        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertSee('/anh/'.$image->id)
            ->assertDontSee('from=');

        $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('/anh/'.$image->id)
            ->assertDontSee('from=');
    }

    private function publishedImage(string $prompt): GeneratedMedia
    {
        return GeneratedMedia::create([
            'visitor_key' => 'visitor-'.str()->random(8),
            'prompt' => $prompt,
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/'.str()->uuid().'.png',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
