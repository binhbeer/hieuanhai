<?php

namespace Tests\Feature;

use App\Models\AiImage;
use App\Models\User;
use App\Services\AiImageEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiImageEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_visit_the_ai_image_editor(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Chọn phong cách')
            ->assertSee('Comic');
    }

    public function test_guest_is_limited_to_three_images_per_day(): void
    {
        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        foreach (range(1, 3) as $i) {
            AiImage::create([
                'visitor_key' => $editor->visitorKey($request),
                'prompt' => 'Prompt '.$i,
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => 'succeeded',
            ]);
        }

        $this->assertSame(0, $editor->remainingToday($request));
        $this->assertTrue($editor->isLimitExceeded($request));
    }

    public function test_logged_in_users_have_no_daily_image_limit(): void
    {
        $this->actingAs(User::factory()->create());

        $editor = app(AiImageEditor::class);
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        foreach (range(1, 3) as $i) {
            AiImage::create([
                'visitor_key' => $editor->visitorKey($request),
                'prompt' => 'Prompt '.$i,
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => 'succeeded',
            ]);
        }

        $this->assertNull($editor->remainingToday($request));
        $this->assertFalse($editor->isLimitExceeded($request));
    }

    public function test_image_creation_uses_provider_generation_endpoint_with_reference_image(): void
    {
        Storage::fake('public');
        config([
            'ai.default_for_images' => 'openai',
            'ai.image_model' => 'cx/gpt-5.5-image',
            'ai.image_reference_field' => 'image',
            'ai.providers.openai.url' => 'http://42.112.31.227:22150/v1',
            'ai.providers.openai.key' => 'test-key',
        ]);

        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);

        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        $photo = UploadedFile::fake()->image('source.png', 2000, 1000);

        $image = app(AiImageEditor::class)->create(
            $request,
            [$photo],
            'A cute cat wearing a hat',
        );

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'http://42.112.31.227:22150/v1/images/generations'
            && $request['model'] === 'cx/gpt-5.5-image'
            && str_contains($request['prompt'], 'Edit that image according to the instructions')
            && str_contains($request['prompt'], 'A cute cat wearing a hat')
            && str_starts_with($request['image'], 'data:image/jpeg;base64,')
            && $this->encodedImageSizeIs($request['image'], 1024, 512)
            && $request['quality'] === 'auto'
            && $request['image_detail'] === 'high'
            && $request['output_format'] === 'png');

        Storage::disk('public')->assertExists($image->result_path);
        $this->assertSame('succeeded', $image->status);
    }

    public function test_image_creation_sends_multiple_reference_images(): void
    {
        Storage::fake('public');
        config([
            'ai.default_for_images' => 'openai',
            'ai.image_model' => 'cx/gpt-5.5-image',
            'ai.providers.openai.url' => 'http://42.112.31.227:22150/v1',
            'ai.providers.openai.key' => 'test-key',
        ]);

        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);

        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $photos = [
            UploadedFile::fake()->image('one.png'),
            UploadedFile::fake()->image('two.png'),
            UploadedFile::fake()->image('three.png'),
        ];

        $image = app(AiImageEditor::class)->create($request, $photos, 'Merge these references');

        Http::assertSent(fn (HttpRequest $request) => is_array($request['images'])
            && count($request['images']) === 3
            && ! isset($request['image'])
            && str_starts_with($request['images'][0], 'data:image/jpeg;base64,')
            && str_starts_with($request['images'][2], 'data:image/jpeg;base64,'));

        $this->assertSame('succeeded', $image->status);
        $this->assertCount(3, $image->response_meta['source_paths']);
    }

    private function encodedImageSizeIs(string $image, int $width, int $height): bool
    {
        $content = base64_decode(substr($image, strlen('data:image/jpeg;base64,')), true);
        $size = is_string($content) ? getimagesizefromstring($content) : false;

        return is_array($size) && $size[0] === $width && $size[1] === $height;
    }
}
