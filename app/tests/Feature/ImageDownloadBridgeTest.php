<?php

namespace Tests\Feature;

use App\Models\GeneratedMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageDownloadBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_exchange_session_authorization_for_short_lived_signed_download_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ai-images/result.png', UploadedFile::fake()->image('result.png')->getContent());
        $user = User::factory()->create();
        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'native-download',
            'prompt' => 'Native image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/result.png',
        ]);

        $this->actingAs($user)
            ->getJson(route('images.download', $image))
            ->assertOk()
            ->assertJsonPath('url', fn (mixed $url): bool => is_string($url) && str_contains($url, 'signature=') && str_contains($url, 'expires='));

        $signedUrl = $this->actingAs($user)->getJson(route('images.download', $image))->json('url');
        Auth::logout();

        $this->get($signedUrl)->assertDownload();
        $this->get($signedUrl.'&tampered=1')->assertNotFound();
    }
}
