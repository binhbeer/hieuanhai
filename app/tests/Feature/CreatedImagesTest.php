<?php

namespace Tests\Feature;

use App\Ai\ImageMetadataAgent;
use App\Ai\ImageReviewAgent;
use App\Ai\ImageToPromptAgent;
use App\Ai\PromptRewriteAgent;
use App\Ai\PromptTranslationAgent;
use App\Events\AiImageCompleted;
use App\Jobs\CreateAiImage;
use App\Models\ApiKey;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiImageEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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
        $this->get(route('history.index'))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_usage_card_navigates_to_created_images_and_opens_api_key_settings(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('gallery.usage')
            ->assertSee(route('history.index'), false)
            ->assertSee('wire:navigate', false)
            ->assertSee(__('API key quota'))
            ->assertSee(__('No API key yet.'))
            ->assertSee("component: 'settings.api-key'", false);

        ApiKey::create([
            'user_id' => $user->id,
            'token_hash' => ApiKey::hashToken('test-key'),
            'token_prefix' => 'test-key',
            'quota_limit' => 100,
            'quota_used' => 25,
        ]);

        $component
            ->dispatch('api-key-updated')
            ->assertSee('25/100')
            ->assertSee(__('Remaining :count', ['count' => 75]));
    }

    public function test_created_images_show_thirty_day_usage_for_current_user(): void
    {
        User::factory()->create(['id' => 1]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        Setting::putValue('contact.zalo_url', 'https://zalo.me/0123456789');

        foreach ([
            [$user->id, 'succeeded', now()],
            [$user->id, 'pending', now()->subDay()],
            [$user->id, 'failed', now()->subDay()],
            [$user->id, 'succeeded', now()->subDays(30)],
            [$other->id, 'succeeded', now()],
        ] as [$userId, $status, $createdAt]) {
            $image = GeneratedMedia::create([
                'user_id' => $userId,
                'visitor_key' => 'usage-'.$userId.'-'.$status.'-'.$createdAt->timestamp,
                'prompt' => 'Usage chart image',
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => $status,
                'result_path' => $status === 'succeeded' ? 'ai-images/usage.png' : null,
            ]);
            $image->forceFill(['created_at' => $createdAt])->save();
        }

        $component = Livewire::actingAs($user)->test('pages::history');
        $dailyUsage = $component->get('dailyUsage');

        $this->assertCount(30, $dailyUsage);
        $this->assertSame(2, collect($dailyUsage)->sum('total'));
        $this->assertSame(0, $dailyUsage[0]['total']);
        $this->assertSame(1, $dailyUsage[28]['total']);
        $this->assertSame(1, $dailyUsage[29]['total']);
        $component
            ->assertSee(__('Image usage'))
            ->assertSee('https://zalo.me/0123456789', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee(__('Upgrade'));
    }

    public function test_guest_create_image_action_opens_login_modal(): void
    {
        Livewire::test('gallery.generator')
            ->call('openComposer')
            ->assertDispatched('open-account-modal')
            ->assertNoRedirect();
    }

    public function test_user_can_view_pending_image_placeholder(): void
    {
        $user = User::factory()->create();
        GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Pending portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        $this->get(route('history.index'))
            ->assertOk()
            ->assertSee('Pending portrait image')
            ->assertSee(__('Creating'))
            ->assertSee('wire:poll.2s', false);
    }

    public function test_stale_pending_image_shows_interrupted_instead_of_queued(): void
    {
        $user = User::factory()->create();
        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Interrupted portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
            'request_meta' => ['progress' => 'queued'],
        ]);
        DB::table('generated_media')->where('id', $image->id)->update([
            'updated_at' => now()->subMinutes(CreateAiImage::STALE_AFTER_MINUTES + 1),
        ]);

        $this->actingAs($user)
            ->get(route('history.index', ['image' => $image->id]))
            ->assertOk()
            ->assertSee(__('Task interrupted. Please try again.'));
    }

    public function test_pending_progress_broadcast_refreshes_without_failure_toast(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::history')
            ->call('refreshCompletedImage', ['status' => 'pending', 'progress' => 'generating'])
            ->assertNotDispatched('toast-show');
    }

    public function test_terminal_broadcast_statuses_show_toasts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::history')
            ->call('refreshCompletedImage', ['status' => 'succeeded'])
            ->assertDispatched('toast-show');

        Livewire::test('pages::history')
            ->call('refreshCompletedImage', ['status' => 'failed'])
            ->assertDispatched('toast-show');
    }

    public function test_created_image_detail_opens_failed_images(): void
    {
        User::factory()->create(); // id 1 is always admin
        $user = User::factory()->create();
        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Failed portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'failed',
            'error' => 'Provider exploded',
        ]);

        Livewire::actingAs($user)
            ->test('gallery.detail')
            ->call('openImage', $image->id)
            ->assertSet('selectedImageId', $image->id)
            ->assertSet('show', true)
            ->assertSee('Failed portrait image')
            ->assertSee(__('Could not create this image.'))
            ->assertDontSee('Provider exploded')
            ->assertSee(__('Failed'));
    }

    public function test_admin_sees_technical_image_error_in_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $image = GeneratedMedia::create([
            'user_id' => $admin->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Admin failed portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'failed',
            'error' => 'Provider exploded',
        ]);

        Livewire::actingAs($admin)
            ->test('gallery.detail')
            ->call('openImage', $image->id)
            ->assertSee('Provider exploded');
    }

    public function test_images_composer_route_opens_latest_created_image_detail(): void
    {
        User::factory()->create(); // id 1 is always admin
        $user = User::factory()->create();
        $this->actingAs($user);

        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Latest failed composer image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'failed',
            'error' => 'Safety review failed',
        ]);

        $this->get(route('history.index', ['composer' => 1]))
            ->assertOk()
            ->assertSee('image-detail-'.$image->id, false)
            ->assertSee('Latest failed composer image')
            ->assertSee(__('Could not create this image.'))
            ->assertDontSee('Safety review failed')
            ->assertSee(__('Copy prompt'));
    }

    public function test_create_similar_image_loads_only_prompt(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('gallery.generator')
            ->set('parentId', 999)
            ->set('parentPrompt', 'Old parent prompt')
            ->set('parentReferenceIndexes', [0])
            ->call('usePrompt', 'Reference prompt')
            ->assertSet('prompt', 'Reference prompt')
            ->assertSet('parentId', null)
            ->assertSet('parentPrompt', '')
            ->assertSet('parentReferenceIndexes', [])
            ->assertSet('referenceImageIds', [])
            ->assertSet('showComposer', true);
    }

    public function test_edit_image_loads_parent_prompt_and_original_references(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ai-image-sources/202607/09/source.jpg', 'fake-image');
        $user = User::factory()->create();
        $parent = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Original parent prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/09/result.png',
            'response_meta' => ['source_paths' => ['ai-image-sources/202607/09/source.jpg']],
        ]);
        $this->actingAs($user);

        Livewire::test('gallery.generator')
            ->call('editImage', $parent->id)
            ->assertSet('parentId', $parent->id)
            ->assertSet('parentPrompt', 'Original parent prompt')
            ->assertSet('prompt', '')
            ->assertSet('parentReferenceIndexes', [0])
            ->assertSet('showComposer', true)
            ->assertSee('Original parent prompt')
            ->assertSee('/storage/ai-image-sources/202607/09/source.jpg');
    }

    public function test_edit_button_is_only_visible_to_image_owner(): void
    {
        $owner = User::factory()->create();
        $image = GeneratedMedia::create([
            'user_id' => $owner->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Owned image prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/09/result.png',
        ]);

        Livewire::actingAs($owner)
            ->test('gallery.detail')
            ->call('openImage', $image->id)
            ->assertSee(__('Edit image'));

        Livewire::actingAs(User::factory()->create())
            ->test('gallery.detail')
            ->call('openImage', $image->id)
            ->assertDontSee(__('Edit image'));
    }

    public function test_non_owner_cannot_load_image_for_editing(): void
    {
        $parent = GeneratedMedia::create([
            'user_id' => User::factory()->create()->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Private parent prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
        ]);
        $this->actingAs(User::factory()->create());

        Livewire::test('gallery.generator')
            ->call('editImage', $parent->id)
            ->assertSet('parentId', null)
            ->assertSet('showComposer', false)
            ->assertDontSee('Private parent prompt');
    }

    public function test_edit_creates_child_with_copied_reference_and_new_prompt(): void
    {
        Bus::fake();
        Storage::fake('public');
        Storage::disk('public')->put('ai-image-sources/202607/09/source.jpg', 'fake-image');
        $user = User::factory()->create();
        $parent = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Original parent prompt',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/09/result.png',
            'response_meta' => ['source_paths' => ['ai-image-sources/202607/09/source.jpg']],
        ]);
        $this->actingAs($user);

        $component = Livewire::test('gallery.generator')
            ->call('editImage', $parent->id)
            ->set('prompt', 'Make the lighting warmer')
            ->call('createImage')
            ->assertSet('showComposer', false);

        $child = GeneratedMedia::query()->where('parent_id', $parent->id)->firstOrFail();
        $pendingPath = data_get($child->request_meta, 'pending_uploads.0.path');

        $component->assertRedirect(route('history.index', ['image' => $child->id], absolute: false));
        $this->assertSame('Make the lighting warmer', $child->prompt);
        $this->assertIsString($pendingPath);
        Storage::disk('public')->assertExists($pendingPath);
    }

    public function test_edit_without_references_keeps_empty_reference_list(): void
    {
        $user = User::factory()->create();
        $parent = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Prompt-only parent',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
        ]);
        $this->actingAs($user);

        Livewire::test('gallery.generator')
            ->call('editImage', $parent->id)
            ->assertSet('parentId', $parent->id)
            ->assertSet('parentPrompt', 'Prompt-only parent')
            ->assertSet('parentReferenceIndexes', []);
    }

    public function test_composer_rejects_prompt_over_1200_words(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('gallery.generator')
            ->set('showComposer', true)
            ->set('prompt', str_repeat('mèo ', 1201))
            ->call('createImage')
            ->assertHasErrors('prompt')
            ->assertNoRedirect();
    }

    public function test_user_can_create_prompt_from_uploaded_image(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_to_prompt_model', 'gpt-5.5-vision');
        ImageToPromptAgent::fake([['prompt' => 'A cinematic product photo with dramatic studio lighting.']]);

        $this->actingAs(User::factory()->create());

        Livewire::test('gallery.generator')
            ->set('showComposer', true)
            ->assertSee(__('Uploading image...'))
            ->assertSee(__('Analyzing image...'))
            ->set('promptSourcePhoto', UploadedFile::fake()->image('source.png', 800, 600))
            ->assertDispatched('prompt-source-uploaded')
            ->call('analyzePromptSourcePhoto')
            ->assertSet('prompt', 'A cinematic product photo with dramatic studio lighting.')
            ->assertSet('promptSourcePhoto', null)
            ->assertHasNoErrors();

        ImageToPromptAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'gpt-5.5-vision'
            && $prompt->attachments->count() === 1);
    }

    public function test_user_can_rewrite_current_composer_prompt_with_instruction(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.prompt_rewrite_model', 'gpt-5.5-rewrite');
        PromptRewriteAgent::fake([['prompt' => 'A cinematic product photo of a small cat.']]);

        $this->actingAs(User::factory()->create());

        Livewire::test('gallery.generator')
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

    public function test_user_can_translate_current_composer_prompt_to_vietnamese(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        PromptTranslationAgent::fake([['prompt' => 'Một bức ảnh sản phẩm điện ảnh về một chú mèo nhỏ.']]);

        $this->actingAs(User::factory()->create());

        Livewire::test('gallery.generator')
            ->set('showComposer', true)
            ->set('prompt', 'A cinematic product photo of a small cat.')
            ->call('translatePrompt')
            ->assertSet('prompt', 'Một bức ảnh sản phẩm điện ảnh về một chú mèo nhỏ.')
            ->assertHasNoErrors();

        PromptTranslationAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'A cinematic product photo of a small cat.'));
    }

    public function test_disabled_prompt_tools_are_hidden_and_do_not_call_agents(): void
    {
        Setting::putValue('ai.prompt_translation_enabled', false);
        Setting::putValue('ai.prompt_rewrite_enabled', false);
        Setting::putValue('ai.image_to_prompt_enabled', false);
        PromptTranslationAgent::fake();
        PromptRewriteAgent::fake();
        ImageToPromptAgent::fake();

        $this->actingAs(User::factory()->create());

        Livewire::test('gallery.generator')
            ->set('showComposer', true)
            ->assertDontSee(__('Translate prompt to Vietnamese'))
            ->assertDontSee(__('Rewrite prompt'))
            ->assertDontSee(__('Image to prompt'))
            ->set('prompt', 'A cinematic cat.')
            ->set('rewriteInstruction', 'Make it dramatic')
            ->set('promptSourcePhoto', UploadedFile::fake()->image('source.png'))
            ->call('translatePrompt')
            ->call('rewritePrompt')
            ->call('analyzePromptSourcePhoto')
            ->assertSet('prompt', 'A cinematic cat.')
            ->assertSet('promptSourcePhoto', null);

        PromptTranslationAgent::assertNeverPrompted();
        PromptRewriteAgent::assertNeverPrompted();
        ImageToPromptAgent::assertNeverPrompted();
    }

    public function test_user_can_write_prompt_from_instruction_without_current_prompt(): void
    {
        Setting::putValue('ai.openai_api_key', 'test-key');
        PromptRewriteAgent::fake([['prompt' => 'A cinematic product photo of a small cat.']]);

        $this->actingAs(User::factory()->create());

        Livewire::test('gallery.generator')
            ->set('showComposer', true)
            ->set('rewriteInstruction', 'Create a cinematic product photo of a small cat')
            ->call('rewritePrompt')
            ->assertSet('prompt', 'A cinematic product photo of a small cat.')
            ->assertSet('rewriteInstruction', '')
            ->assertHasNoErrors();
    }

    public function test_creating_image_closes_composer_and_opens_pending_detail(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test('gallery.generator')
            ->set('showComposer', true)
            ->set('prompt', 'Create a small cat')
            ->call('createImage')
            ->assertSet('showComposer', false)
            ->assertSet('prompt', 'Create a small cat');

        $image = GeneratedMedia::query()->latest()->firstOrFail();
        $component->assertRedirect(route('history.index', ['image' => $image->id], absolute: false));
        $this->assertSame('pending', $image->status);
        $this->assertSame('Create a small cat', $image->prompt);
        Bus::assertDispatched(CreateAiImage::class, fn (CreateAiImage $job) => $job->imageId === $image->id);

        $this->get(route('history.index', ['image' => $image->id]))
            ->assertOk()
            ->assertSee('image-detail-'.$image->id, false)
            ->assertSee('Create a small cat');
    }

    public function test_unverified_user_after_registration_day_is_redirected_to_email_verification_when_creating_image(): void
    {
        $this->skipUnlessFortifyHas(Features::emailVerification());
        Bus::fake();

        $user = User::factory()->unverified()->create(['id' => 200, 'created_at' => now()->subDay()]);
        $this->actingAs($user);

        Livewire::test('gallery.generator')
            ->set('showComposer', true)
            ->set('prompt', 'Create a small cat')
            ->call('createImage')
            ->assertRedirect(route('verification.notice', absolute: false));

        $this->assertSame('image-creation-requires-verification', session('status'));
        $this->get(route('verification.notice'))->assertSee(__('Please verify your email to continue receiving daily image generations after your registration day.'));
        $this->assertSame(0, GeneratedMedia::query()->count());
        Bus::assertNotDispatched(CreateAiImage::class);
    }

    public function test_metadata_backfill_only_updates_missing_published_images_up_to_limit(): void
    {
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageMetadataAgent::fake([[
            'title' => 'Ảnh cũ',
            'description' => 'Ảnh chân dung cũ được mô tả rõ chủ thể, ánh sáng và bối cảnh để tối ưu hiển thị trên kết quả tìm kiếm.',
            'category' => 'portraits',
            'tags' => ['chân dung', 'studio', 'ánh sáng', 'avatar'],
        ]]);

        $first = GeneratedMedia::create([
            'visitor_key' => 'visitor-a',
            'prompt' => 'Old published portrait',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/old-1.png',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $second = GeneratedMedia::create([
            'visitor_key' => 'visitor-b',
            'prompt' => 'Second old published portrait',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/old-2.png',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $existing = GeneratedMedia::create([
            'visitor_key' => 'visitor-c',
            'prompt' => 'Already described portrait',
            'description' => 'Keep this description.',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/old-3.png',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->artisan('ai-images:backfill-metadata', ['--limit' => 1])
            ->expectsOutput('Processed 1: 1 metadata backfilled, 0 failed.')
            ->assertSuccessful();

        $first->refresh()->load('category', 'tags');
        $this->assertSame('Ảnh cũ', $first->title);
        $this->assertNotNull($first->description);
        $this->assertSame('portraits', $first->category?->slug);
        $this->assertSame(['chân dung', 'studio', 'ánh sáng', 'avatar'], $first->tags->pluck('name')->all());
        $this->assertNull($second->fresh()->description);
        $this->assertSame('Keep this description.', $existing->fresh()->description);
    }

    public function test_metadata_backfill_rejects_negative_limit(): void
    {
        $this->artisan('ai-images:backfill-metadata', ['--limit' => -1])
            ->expectsOutput('--limit must be zero or greater.')
            ->assertExitCode(2);
    }

    public function test_stale_pending_images_are_failed_and_release_quota(): void
    {
        Storage::fake('public');
        Event::fake([AiImageCompleted::class]);

        $user = User::factory()->create();
        $pendingPath = 'ai-image-pending/stale.jpg';
        Storage::disk('public')->put($pendingPath, 'pending');
        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Stale portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
            'request_meta' => [
                'progress' => 'generating',
                'pending_uploads' => [['path' => $pendingPath]],
            ],
        ]);
        DB::table('generated_media')->where('id', $image->id)->update([
            'updated_at' => now()->subMinutes(CreateAiImage::STALE_AFTER_MINUTES + 1),
        ]);

        $this->artisan('ai-images:recover-stale')->assertSuccessful();

        $image->refresh();
        $this->assertSame('failed', $image->status);
        $this->assertSame('failed', data_get($image->request_meta, 'progress'));
        $this->assertSame('Tác vụ tạo ảnh bị gián đoạn. Vui lòng thử lại.', $image->error);
        $this->actingAs($user);
        $this->assertSame(0, app(AiImageEditor::class)->countToday(request()));
        Storage::disk('public')->assertMissing($pendingPath);
        Event::assertDispatched(AiImageCompleted::class);
    }

    public function test_recovery_leaves_recent_pending_images_queued(): void
    {
        $user = User::factory()->create();
        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Recent portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'pending',
            'request_meta' => ['progress' => 'queued'],
        ]);

        $this->artisan('ai-images:recover-stale')->assertSuccessful();

        $this->assertSame('pending', $image->fresh()->status);
    }

    public function test_create_image_job_broadcasts_completion_to_user(): void
    {
        Event::fake([AiImageCompleted::class]);
        Storage::fake('public');
        Setting::putValue('ai.openai_url', 'http://42.112.31.227:22150/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        ImageReviewAgent::fake([['allowed' => true, 'blocked_policy' => 'none', 'reason' => 'An toàn.', 'matches_prompt' => true]]);
        ImageMetadataAgent::fake([['title' => 'Chân dung studio', 'description' => 'Chân dung studio chuyên nghiệp, ánh sáng mềm, nền sạch, phù hợp avatar và hồ sơ công khai.', 'category' => 'portraits', 'tags' => ['chân dung', 'studio', 'avatar', 'chuyên nghiệp']]]);
        Http::fake([
            '42.112.31.227:22150/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-png')]],
            ]),
        ]);

        $user = User::factory()->create();
        $image = GeneratedMedia::create([
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
        ImageReviewAgent::fake([['allowed' => true, 'blocked_policy' => 'none', 'reason' => 'An toàn.', 'matches_prompt' => true]]);
        ImageMetadataAgent::fake([['title' => 'Chân dung studio', 'description' => 'Chân dung studio chuyên nghiệp, ánh sáng mềm, nền sạch, phù hợp avatar và hồ sơ công khai.', 'category' => 'portraits', 'tags' => ['chân dung', 'studio', 'avatar', 'chuyên nghiệp']]]);

        $user = User::factory()->create();
        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Created portrait image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/202607/08/created.png',
        ]);

        $this->actingAs($user);

        $this->get(route('history.index'))
            ->assertOk()
            ->assertSee(__('Created images'))
            ->assertSee('/thumb_x320x/storage/ai-images/202607/08/created.png')
            ->assertSee('/storage/ai-images/202607/08/created.png')
            ->assertSee('Created portrait image')
            ->assertSee(__('Publish'));

        $component = Livewire::test('pages::history')
            ->call('togglePublish', $image->id)
            ->assertSee(__('Published'))
            ->assertSee(__('Unpublish'));

        $this->assertTrue($image->fresh()->is_published);

        $component
            ->call('togglePublish', $image->id)
            ->assertSee(__('Unpublished'))
            ->assertSee(__('Publish'));

        $this->assertFalse($image->fresh()->is_published);
    }

    public function test_rejected_publish_is_disabled_with_error_tooltip_for_non_admin(): void
    {
        User::factory()->create(['id' => 1]);
        $user = User::factory()->create();
        $image = GeneratedMedia::create([
            'user_id' => $user->id,
            'visitor_key' => 'visitor-a',
            'prompt' => 'Rejected image',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/rejected.png',
            'response_meta' => ['publish_error' => 'Prompt không phù hợp để tạo hoặc publish ảnh.'],
        ]);

        $this->actingAs($user);

        Livewire::test('pages::history')
            ->assertSee('Prompt không phù hợp để tạo hoặc publish ảnh.')
            ->assertSeeHtml('disabled');

        $this->assertFalse($image->fresh()->is_published);
    }

    public function test_failed_create_image_job_releases_quota_by_marking_image_failed(): void
    {
        Event::fake([AiImageCompleted::class]);

        $user = User::factory()->create();
        $this->actingAs($user);
        $image = GeneratedMedia::create([
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
