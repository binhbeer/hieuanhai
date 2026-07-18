<?php

namespace Tests\Feature;

use App\Ai\ProjectNameAgent;
use App\Jobs\CreateAiImage;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\StudioProject;
use App\Models\User;
use App\Services\StudioImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_page_is_public_and_navigation_uses_canonical_route(): void
    {
        $this->get(route('studio.index'))
            ->assertOk()
            ->assertSee(__('Studio'))
            ->assertSee(__('Product detail images'))
            ->assertSee(__('Marketing poster'))
            ->assertDontSee('Nền trắng');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('studio.index'), false);
    }

    public function test_guest_opening_tool_requests_login(): void
    {
        Livewire::test('pages::studio')
            ->call('openTool', 'product-detail')
            ->assertDispatched('open-account-modal')
            ->assertSet('showWizard', false);
    }

    public function test_wizard_query_opens_default_studio_flyout_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams(['wizard' => '1'])
            ->test('pages::studio')
            ->assertSet('showWizard', true)
            ->assertSet('tool', 'product-detail');
    }

    public function test_wizard_query_requests_login_for_guest(): void
    {
        Livewire::withQueryParams(['wizard' => '1'])
            ->test('pages::studio')
            ->assertSet('showWizard', false)
            ->assertDispatched('open-account-modal');
    }

    public function test_user_can_save_and_resume_product_detail_draft(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.image_models', ['cx/gpt-5.5-image', 'cx/draft-image']);
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('pages::studio')
            ->call('openTool', 'product-detail')
            ->assertSet('projectName', '')
            ->assertDontSee(__('Project name'))
            ->set('productName', 'Leather handbag')
            ->set('imageModel', 'cx/draft-image')
            ->set('newProductPhoto', UploadedFile::fake()->image('bag.jpg', 800, 800))
            ->set('newLogoPhoto', UploadedFile::fake()->image('logo.png', 300, 200))
            ->set('newModelPhoto', UploadedFile::fake()->image('model.jpg', 600, 900))
            ->set('newAdditionalProductPhotos', [
                UploadedFile::fake()->image('bag-side.jpg', 800, 800),
                UploadedFile::fake()->image('bag-back.jpg', 800, 800),
            ])
            ->call('saveDraft');

        $project = StudioProject::query()->sole();

        $component->assertSet('project', $project->id);
        $this->assertSame($user->id, $project->user_id);
        $this->assertSame('product-detail', $project->tool);
        $this->assertSame(__('Product detail images'), $project->name);
        $this->assertSame('Leather handbag', data_get($project->form_data, 'product_name'));
        $this->assertSame('cx/draft-image', data_get($project->form_data, 'image_model'));
        foreach (['product', 'logo', 'model', 'additional_products.0', 'additional_products.1'] as $path) {
            Storage::disk('public')->assertExists(data_get($project->input_paths, $path));
        }
        $logoPath = data_get($project->input_paths, 'logo');
        $removedAdditionalPath = data_get($project->input_paths, 'additional_products.0');

        Livewire::actingAs($user)
            ->withQueryParams(['view' => 'projects', 'project' => $project->id])
            ->test('pages::studio')
            ->assertSet('showWizard', true)
            ->assertSet('productName', 'Leather handbag')
            ->assertSet('imageModel', 'cx/draft-image')
            ->assertDontSee(__('Project name'))
            ->assertSee('Ảnh sản phẩm chính')
            ->assertSee('Ảnh tham chiếu bổ sung')
            ->assertSee('Người mẫu nhất quán')
            ->call('removeProductInput', 'logo')
            ->call('removeProductInput', 'additional_products', 0);

        $project->refresh();
        $this->assertNull(data_get($project->input_paths, 'logo'));
        $this->assertCount(1, data_get($project->input_paths, 'additional_products'));
        $this->assertFalse(Storage::disk('public')->exists($logoPath));
        $this->assertFalse(Storage::disk('public')->exists($removedAdditionalPath));
    }

    public function test_new_project_submit_generates_name_from_reference_image(): void
    {
        Storage::fake('public');
        Bus::fake();
        ProjectNameAgent::fake([['name' => 'Túi da nâu studio']]);
        Setting::putValue('ai.openai_url', 'https://example.test/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
        Setting::putValue('ai.image_models', ['cx/gpt-5.5-image', 'cx/studio-image']);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::studio')
            ->call('openTool', 'product-detail')
            ->assertSet('projectName', '')
            ->set('productName', 'Leather handbag')
            ->set('imageModel', 'cx/studio-image')
            ->set('newProductPhoto', UploadedFile::fake()->image('bag.jpg', 800, 800))
            ->set('imageTypes', ['hero'])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('projectName', 'Túi da nâu studio');

        $project = StudioProject::query()->sole();
        $this->assertSame('Túi da nâu studio', $project->name);
        $this->assertNotNull($project->submitted_at);
        ProjectNameAgent::assertPrompted(function ($prompt): bool {
            return $prompt->contains('Vietnamese')
                && $prompt->contains('Leather handbag')
                && $prompt->attachments !== [];
        });
        Bus::assertDispatched(CreateAiImage::class, 1);
    }

    public function test_product_detail_requires_primary_product_and_maps_legacy_references(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::studio')
            ->call('openTool', 'product-detail')
            ->set('newLogoPhoto', UploadedFile::fake()->image('logo.png'))
            ->call('nextStep')
            ->assertHasErrors('newProductPhoto');

        Storage::disk('public')->put('studio-projects/legacy-main.jpg', 'main');
        Storage::disk('public')->put('studio-projects/legacy-side.jpg', 'side');
        $project = StudioProject::create([
            'user_id' => $user->id,
            'tool' => 'product-detail',
            'name' => 'Legacy product',
            'input_paths' => ['references' => ['studio-projects/legacy-main.jpg', 'studio-projects/legacy-side.jpg']],
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['project' => $project->id])
            ->test('pages::studio')
            ->assertSee('legacy-main.jpg', false)
            ->assertSee('legacy-side.jpg', false);
    }

    public function test_projects_are_isolated_by_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        StudioProject::create([
            'user_id' => $owner->id,
            'tool' => 'marketing-poster',
            'name' => 'Private campaign',
        ]);

        Livewire::actingAs($other)
            ->withQueryParams(['view' => 'projects'])
            ->test('pages::studio')
            ->assertDontSee('Private campaign');
    }

    public function test_product_detail_batch_is_created_atomically_with_metadata_and_jobs(): void
    {
        Storage::fake('public');
        Bus::fake();
        Setting::putValue('ai.image_models', ['cx/gpt-5.5-image', 'cx/studio-image']);
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['product.jpg', 'logo.png', 'model.jpg', 'side.jpg', 'back.jpg'] as $file) {
            Storage::disk('public')->put('studio-projects/'.$file, UploadedFile::fake()->image($file)->getContent());
        }
        $project = StudioProject::create([
            'user_id' => $user->id,
            'tool' => 'product-detail',
            'name' => 'Product set',
            'form_data' => ['notes' => 'Warm studio light'],
            'input_paths' => [
                'product' => 'studio-projects/product.jpg',
                'logo' => 'studio-projects/logo.png',
                'model' => 'studio-projects/model.jpg',
                'additional_products' => ['studio-projects/side.jpg', 'studio-projects/back.jpg'],
            ],
        ]);

        $images = app(StudioImageService::class)->createBatch(request(), $project, [
            ['prompt' => 'Create hero image', 'title' => 'Hero', 'output_type' => 'hero'],
            ['prompt' => 'Create detail image', 'title' => 'Detail', 'output_type' => 'close-up'],
        ], '4:5', '1k', model: 'cx/studio-image');

        $this->assertCount(2, $images);
        $this->assertNotNull($project->refresh()->submitted_at);
        $this->assertSame(2, GeneratedMedia::query()->where('studio_project_id', $project->id)->count());
        $this->assertSame('web', $images->first()->source);
        $this->assertSame('product-detail', $images->first()->preset);
        $this->assertSame($project->id, $images->first()->studio_project_id);
        $this->assertTrue($images->first()->studioProject->is($project));
        $this->assertSame('studio', data_get($images->first()->request_meta, 'generation_mode'));
        $this->assertSame('product-detail', data_get($images->first()->request_meta, 'tool'));
        $this->assertArrayNotHasKey('skill', $images->first()->request_meta);
        $this->assertSame('cx/studio-image', $images->first()->model);
        $this->assertSame('4:5', data_get($images->first()->request_meta, 'aspect_ratio'));
        $this->assertSame('hero', data_get($images->first()->request_meta, 'output_type'));
        $this->assertSame('product-detail-v2', data_get($images->first()->request_meta, 'prompt_contract'));
        $this->assertSame(['product', 'logo', 'model', 'additional_product', 'additional_product'], data_get($images->first()->request_meta, 'reference_roles'));
        $this->assertSame(5, data_get($images->first()->request_meta, 'upload_count'));
        $this->assertNotSame(
            data_get($images[0]->request_meta, 'pending_uploads.0.path'),
            data_get($images[1]->request_meta, 'pending_uploads.0.path'),
        );
        Bus::assertDispatched(CreateAiImage::class, 2);
    }

    public function test_batch_rejects_insufficient_quota_without_partial_rows(): void
    {
        Storage::fake('public');
        Bus::fake();
        User::factory()->create(['id' => 1]);
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (range(1, 4) as $index) {
            GeneratedMedia::create([
                'user_id' => $user->id,
                'visitor_key' => 'quota-'.$index,
                'prompt' => 'Existing image '.$index,
                'provider' => 'openai',
                'model' => 'cx/gpt-5.5-image',
                'status' => 'succeeded',
            ]);
        }
        Storage::disk('public')->put('studio-projects/product.jpg', UploadedFile::fake()->image('product.jpg')->getContent());
        $project = StudioProject::create([
            'user_id' => $user->id,
            'tool' => 'product-detail',
            'name' => 'Too many outputs',
            'input_paths' => ['references' => ['studio-projects/product.jpg']],
        ]);

        try {
            app(StudioImageService::class)->createBatch(request(), $project, [
                ['prompt' => 'Hero', 'title' => 'Hero', 'output_type' => 'hero'],
                ['prompt' => 'Detail', 'title' => 'Detail', 'output_type' => 'close-up'],
            ], '4:5', '1k');
            $this->fail('Expected quota validation failure.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('đủ lượt', $e->getMessage());
        }

        $this->assertNull($project->refresh()->submitted_at);
        $this->assertSame(0, GeneratedMedia::query()->where('studio_project_id', $project->id)->count());
        Bus::assertNothingDispatched();
    }

    public function test_submitted_project_can_create_multiple_versions(): void
    {
        Storage::fake('public');
        Bus::fake();
        $user = User::factory()->create();
        $this->actingAs($user);
        Storage::disk('public')->put('studio-projects/poster.jpg', UploadedFile::fake()->image('poster.jpg')->getContent());
        $project = StudioProject::create([
            'user_id' => $user->id,
            'tool' => 'marketing-poster',
            'name' => 'Campaign',
            'input_paths' => ['references' => ['studio-projects/poster.jpg']],
        ]);
        $generator = app(StudioImageService::class);

        $first = $generator->createBatch(request(), $project, [
            ['prompt' => 'Create first poster', 'title' => 'Poster v1', 'output_type' => 'poster'],
        ], '4:5', '1k')->sole();
        $first->update(['status' => 'succeeded']);
        $second = $generator->createBatch(request(), $project->refresh(), [
            ['prompt' => 'Create revised poster', 'title' => 'Poster v2', 'output_type' => 'poster'],
        ], '4:5', '1k')->sole();

        $this->assertSame(1, data_get($first->request_meta, 'version'));
        $this->assertSame(2, data_get($second->request_meta, 'version'));
        $this->assertSame(2, $project->media()->count());
        Bus::assertDispatched(CreateAiImage::class, 2);
    }

    public function test_submitted_project_opens_details_before_new_version_flyout(): void
    {
        $user = User::factory()->create();
        $project = StudioProject::create([
            'user_id' => $user->id,
            'tool' => 'marketing-poster',
            'name' => 'Existing campaign',
            'form_data' => [
                'poster_topic' => 'Summer campaign',
                'aspect_ratio' => '9:16',
                'language' => 'vi',
            ],
            'input_paths' => ['references' => []],
            'submitted_at' => now(),
        ]);
        $v1 = GeneratedMedia::create([
            'user_id' => $user->id,
            'studio_project_id' => $project->id,
            'visitor_key' => 'project-version-ui',
            'prompt' => 'Existing poster v1',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/existing-poster-v1.png',
            'request_meta' => ['version' => 1],
        ]);
        $v2 = GeneratedMedia::create([
            'user_id' => $user->id,
            'studio_project_id' => $project->id,
            'visitor_key' => 'project-version-ui',
            'prompt' => 'Existing poster v2',
            'provider' => 'openai',
            'model' => 'cx/gpt-5.5-image',
            'status' => 'succeeded',
            'result_path' => 'ai-images/existing-poster-v2.png',
            'request_meta' => ['version' => 2],
        ]);
        Storage::disk('public')->put('ai-images/existing-poster-v1.png', UploadedFile::fake()->image('existing-poster-v1.png')->getContent());
        Storage::disk('public')->put('ai-images/existing-poster-v2.png', UploadedFile::fake()->image('existing-poster-v2.png')->getContent());

        Livewire::actingAs($user)
            ->withQueryParams(['view' => 'projects', 'project' => $project->id])
            ->test('pages::studio')
            ->assertSet('showWizard', false)
            ->assertSee('Existing campaign')
            ->assertSee(__('Version :version', ['version' => 2]))
            ->assertSee(__('Version :version', ['version' => 1]))
            ->assertSee(__('Marketing poster'))
            ->assertSee(__('Create new version'))
            ->assertSee(__('Back to projects'), false)
            ->assertSee("id: {$v2->id}, preview:", false)
            ->assertSee("id: {$v1->id}, preview:", false)
            ->assertDontSee('ResizeObserver')
            ->call('resumeProject', $project->id)
            ->assertSet('showWizard', true)
            ->assertSet('projectName', 'Existing campaign')
            ->assertSet('posterTopic', 'Summer campaign')
            ->assertSet('aspectRatio', '9:16')
            ->assertSee(__('Project name'))
            ->assertSee('Phiên bản mới 3');
    }
}
