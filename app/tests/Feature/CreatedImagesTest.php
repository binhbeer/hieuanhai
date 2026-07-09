<?php

namespace Tests\Feature;

use App\Ai\ImageReviewAgent;
use App\Ai\PromptRewriteAgent;
use App\Events\AiImageCompleted;
use App\Jobs\CreateAiImage;
use App\Models\AiImage;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiImageEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use Tests\TestCase;

class CreatedImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_created_images(): void
    {
        $this->get(route('images.index'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_user_can_view_pending_image_placeholder(): void
    {
        $user = User::factory()->create();
        AiImage::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Pending portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        $this->get(route('images.index'))
            ->assertOk()
            ->assertSee('Pending portrait image')
            ->assertSee(__('Creating'))
            ->assertSee('wire:poll.2s', false);
    }

    public function test_pending_progress_broadcast_refreshes_without_failure_toast(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::images')
            ->call('refreshCompletedImage', ['status' => 'pending', 'progress' => 'generating'])
            ->assertNotDispatched('toast-show');
    }

    public function test_terminal_broadcast_statuses_show_toasts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::images')
            ->call('refreshCompletedImage', ['status' => 'succeeded'])
            ->assertDispatched('toast-show');

        Livewire::test('pages::images')
            ->call('refreshCompletedImage', ['status' => 'failed'])
            ->assertDispatched('toast-show');
    }

    public function test_created_image_detail_opens_failed_images(): void
    {
        $user = User::factory()->create();
        $image = AiImage::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Failed portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'failed',
            'error' => 'Provider exploded',
        ]);

        Livewire::actingAs($user)
            ->test('image-detail')
            ->call('openImage', $image->id)
            ->assertSet('selectedImageId', $image->id)
            ->assertSet('show', true)
            ->assertSee('Failed portrait image')
            ->assertSee('Provider exploded')
            ->assertSee(__('Failed'));
    }

    public function test_images_composer_route_opens_latest_created_image_detail(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $image = AiImage::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Latest failed composer image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'failed',
            'error' => 'Safety review failed',
        ]);

        $this->get(route('images.index', ['composer' => 1]))
            ->assertOk()
            ->assertSee('image-detail-'.$image->id, false)
            ->assertSee('Latest failed composer image')
            ->assertSee('Safety review failed')
            ->assertSee(__('Copy prompt'));
    }

    public function test_create_similar_image_attaches_reference_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ai-images/202607/09/reference.png', 'fake-image');

        $image = AiImage::create([
            'visitor_key' => 'visitor-a',
            'prompt' => 'Reference prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/09/reference.png',
            'is_published' => true,
        ]);
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::image-generator')
            ->call('usePrompt', 'Reference prompt', $image->id)
            ->assertSet('prompt', 'Reference prompt')
            ->assertSet('referenceImageIds', [$image->id])
            ->assertSet('showComposer', true);
    }

    public function test_creating_similar_image_stores_original_as_pending_reference(): void
    {
        Bus::fake();
        Storage::fake('public');
        Storage::disk('public')->put('ai-images/202607/09/reference.png', 'fake-image');

        $image = AiImage::create([
            'visitor_key' => 'visitor-a',
            'prompt' => 'Reference prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/09/reference.png',
            'is_published' => true,
        ]);
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::image-generator')
            ->call('usePrompt', 'Reference prompt', $image->id)
            ->call('createImage')
            ->assertSet('prompt', 'Reference prompt')
            ->assertSet('referenceImageIds', [$image->id])
            ->assertRedirect(route('images.index', ['composer' => 1], absolute: false));

        $pending = AiImage::query()->where('status', 'pending')->firstOrFail();
        $pendingPath = data_get($pending->request_meta, 'pending_uploads.0.path');

        $this->assertSame([$image->id], $pending->request_meta['reference_image_ids']);
        $this->assertIsString($pendingPath);
        Storage::disk('public')->assertExists($pendingPath);
    }

    public function test_user_can_rewrite_current_composer_prompt_with_instruction(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.prompt_rewrite_model', 'gpt-5.5-rewrite');
        PromptRewriteAgent::fake([['prompt' => 'A cinematic product photo of a small cat.']]);

        $this->actingAs(User::factory()->create());

        Livewire::test('pages::image-generator')
            ->set('showComposer', true)
            ->set('prompt', 'small cat')
            ->set('rewriteInstruction', 'make it cinematic')
            ->call('rewritePrompt')
            ->assertSet('prompt', 'A cinematic product photo of a small cat.')
            ->assertSet('rewriteInstruction', '')
            ->assertHasNoErrors();

        PromptRewriteAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'gpt-5.5-rewrite'
            && str_contains($prompt->prompt, 'small cat')
            && str_contains($prompt->prompt, 'make it cinematic'));
    }

    public function test_creating_image_redirects_to_created_images_with_composer_open(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::image-generator')
            ->set('showComposer', true)
            ->set('prompt', 'Create a small cat')
            ->call('createImage')
            ->assertSet('showComposer', true)
            ->assertSet('prompt', 'Create a small cat')
            ->assertRedirect(route('images.index', ['composer' => 1], absolute: false));

        $image = AiImage::query()->latest()->firstOrFail();
        $this->assertSame('pending', $image->status);
        $this->assertSame('Create a small cat', $image->prompt);
        Bus::assertDispatched(CreateAiImage::class, fn (CreateAiImage $job) => $job->imageId === $image->id);
    }

    public function test_unverified_user_after_registration_day_is_redirected_to_email_verification_when_creating_image(): void
    {
        $this->skipUnlessFortifyHas(Features::emailVerification());
        Bus::fake();

        $user = User::factory()->unverified()->create(['id' => 200, 'created_at' => now()->subDay()]);
        $this->actingAs($user);

        Livewire::test('pages::image-generator')
            ->set('showComposer', true)
            ->set('prompt', 'Create a small cat')
            ->call('createImage')
            ->assertRedirect(route('verification.notice', absolute: false));

        $this->assertSame('image-creation-requires-verification', session('status'));
        $this->get(route('verification.notice'))->assertSee(__('Please verify your email to continue receiving daily image generations after your registration day.'));
        $this->assertSame(0, AiImage::query()->count());
        Bus::assertNotDispatched(CreateAiImage::class);
    }

    public function test_create_image_job_broadcasts_completion_to_user(): void
    {
        Event::fake([AiImageCompleted::class]);
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => true, 'category' => 'portraits', 'tags' => ['chân dung', 'studio', 'avatar'], 'reason' => 'An toàn.']]);
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);

        $user = User::factory()->create();
        $image = AiImage::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Created portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
        ]);

        (new CreateAiImage($image->id))->handle(app(AiImageEditor::class));

        Event::assertDispatched(
            AiImageCompleted::class,
            fn (AiImageCompleted $event): bool => $event->image->id === $image->id && $event->image->status === 'succeeded'
        );
    }

    public function test_user_can_view_and_toggle_created_image_publish(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => true, 'category' => 'portraits', 'tags' => ['chân dung', 'studio', 'avatar'], 'reason' => 'An toàn.']]);

        $user = User::factory()->create();
        $image = AiImage::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Created portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/created.png',
        ]);

        $this->actingAs($user);

        $this->get(route('images.index'))
            ->assertOk()
            ->assertSee(__('Created images'))
            ->assertSee('Created portrait image')
            ->assertSee(__('Publish image'));

        Livewire::test('pages::images')
            ->call('togglePublish', $image->id);

        $this->assertTrue($image->fresh()->is_published);

        Livewire::test('pages::images')
            ->call('togglePublish', $image->id);

        $this->assertFalse($image->fresh()->is_published);
    }

    public function test_failed_create_image_job_releases_quota_by_marking_image_failed(): void
    {
        Event::fake([AiImageCompleted::class]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $image = AiImage::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Created portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
        ]);

        $job = new CreateAiImage($image->id, $user->id);
        $job->failed(new \RuntimeException('Queue timeout'));

        $this->assertSame('failed', $image->fresh()->status);
        $this->assertSame(0, app(AiImageEditor::class)->countToday(request()));
        Event::assertDispatched(AiImageCompleted::class);
    }
}
