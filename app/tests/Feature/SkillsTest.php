<?php

namespace Tests\Feature;

use App\Ai\ProjectNameAgent;
use App\Jobs\CreateAiImage;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\SkillProject;
use App\Models\User;
use App\Services\SkillProjectGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SkillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_skills_page_is_public_and_navigation_uses_canonical_route(): void
    {
        $this->get(route('skills.index'))
            ->assertOk()
            ->assertSee(__('AI tools'))
            ->assertSee(__('Product detail images'))
            ->assertSee(__('Marketing poster'))
            ->assertDontSee('Nền trắng');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('skills.index'), false);
    }

    public function test_guest_opening_tool_requests_login(): void
    {
        Livewire::test('pages::skills')
            ->call('openTool', 'product-detail')
            ->assertDispatched('open-account-modal')
            ->assertSet('showWizard', false);
    }

    public function test_user_can_save_and_resume_product_detail_draft(): void
    {
        Storage::fake('public');
        Setting::putValue('ai.image_models', ['cx/gpt-5.5-image', 'cx/draft-image']);
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('pages::skills')
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

        $project = SkillProject::query()->sole();

        $component->assertSet('project', $project->id);
        $this->assertSame($user->id, $project->user_id);
        $this->assertSame('product-detail', $project->skill);
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
            ->test('pages::skills')
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
        Storage::disk('public')->assertMissing($logoPath);
        Storage::disk('public')->assertMissing($removedAdditionalPath);
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
            ->test('pages::skills')
            ->call('openTool', 'product-detail')
            ->assertSet('projectName', '')
            ->set('productName', 'Leather handbag')
            ->set('imageModel', 'cx/studio-image')
            ->set('newProductPhoto', UploadedFile::fake()->image('bag.jpg', 800, 800))
            ->set('imageTypes', ['hero'])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('projectName', 'Túi da nâu studio');

        $project = SkillProject::query()->sole();
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
            ->test('pages::skills')
            ->call('openTool', 'product-detail')
            ->set('newLogoPhoto', UploadedFile::fake()->image('logo.png'))
            ->call('nextStep')
            ->assertHasErrors('newProductPhoto');

        Storage::disk('public')->put('skill-projects/legacy-main.jpg', 'main');
        Storage::disk('public')->put('skill-projects/legacy-side.jpg', 'side');
        $project = SkillProject::create([
            'user_id' => $user->id,
            'skill' => 'product-detail',
            'name' => 'Legacy product',
            'input_paths' => ['references' => ['skill-projects/legacy-main.jpg', 'skill-projects/legacy-side.jpg']],
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['project' => $project->id])
            ->test('pages::skills')
            ->assertSee('legacy-main.jpg', false)
            ->assertSee('legacy-side.jpg', false);
    }

    public function test_projects_are_isolated_by_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        SkillProject::create([
            'user_id' => $owner->id,
            'skill' => 'marketing-poster',
            'name' => 'Private campaign',
        ]);

        Livewire::actingAs($other)
            ->withQueryParams(['view' => 'projects'])
            ->test('pages::skills')
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
            Storage::disk('public')->put('skill-projects/'.$file, UploadedFile::fake()->image($file)->getContent());
        }
        $project = SkillProject::create([
            'user_id' => $user->id,
            'skill' => 'product-detail',
            'name' => 'Product set',
            'form_data' => ['notes' => 'Warm studio light'],
            'input_paths' => [
                'product' => 'skill-projects/product.jpg',
                'logo' => 'skill-projects/logo.png',
                'model' => 'skill-projects/model.jpg',
                'additional_products' => ['skill-projects/side.jpg', 'skill-projects/back.jpg'],
            ],
        ]);

        $images = app(SkillProjectGenerator::class)->create(request(), $project, [
            ['prompt' => 'Create hero image', 'title' => 'Hero', 'output_type' => 'hero'],
            ['prompt' => 'Create detail image', 'title' => 'Detail', 'output_type' => 'close-up'],
        ], '4:5', '1k', model: 'cx/studio-image');

        $this->assertCount(2, $images);
        $this->assertNotNull($project->refresh()->submitted_at);
        $this->assertSame(2, GeneratedMedia::query()->where('skill_project_id', $project->id)->count());
        $this->assertSame('skills', $images->first()->source);
        $this->assertSame('product-detail', $images->first()->preset);
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
        Storage::disk('public')->put('skill-projects/product.jpg', UploadedFile::fake()->image('product.jpg')->getContent());
        $project = SkillProject::create([
            'user_id' => $user->id,
            'skill' => 'product-detail',
            'name' => 'Too many outputs',
            'input_paths' => ['references' => ['skill-projects/product.jpg']],
        ]);

        try {
            app(SkillProjectGenerator::class)->create(request(), $project, [
                ['prompt' => 'Hero', 'title' => 'Hero', 'output_type' => 'hero'],
                ['prompt' => 'Detail', 'title' => 'Detail', 'output_type' => 'close-up'],
            ], '4:5', '1k');
            $this->fail('Expected quota validation failure.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('đủ lượt', $e->getMessage());
        }

        $this->assertNull($project->refresh()->submitted_at);
        $this->assertSame(0, GeneratedMedia::query()->where('skill_project_id', $project->id)->count());
        Bus::assertNothingDispatched();
    }

    public function test_submitted_project_can_create_multiple_versions(): void
    {
        Storage::fake('public');
        Bus::fake();
        $user = User::factory()->create();
        $this->actingAs($user);
        Storage::disk('public')->put('skill-projects/poster.jpg', UploadedFile::fake()->image('poster.jpg')->getContent());
        $project = SkillProject::create([
            'user_id' => $user->id,
            'skill' => 'marketing-poster',
            'name' => 'Campaign',
            'input_paths' => ['references' => ['skill-projects/poster.jpg']],
        ]);
        $generator = app(SkillProjectGenerator::class);

        $first = $generator->create(request(), $project, [
            ['prompt' => 'Create first poster', 'title' => 'Poster v1', 'output_type' => 'poster'],
        ], '4:5', '1k')->sole();
        $first->update(['status' => 'succeeded']);
        $second = $generator->create(request(), $project->refresh(), [
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
        $project = SkillProject::create([
            'user_id' => $user->id,
            'skill' => 'marketing-poster',
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
            'skill_project_id' => $project->id,
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
            'skill_project_id' => $project->id,
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
            ->test('pages::skills')
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
