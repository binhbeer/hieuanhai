<?php

namespace Tests\Feature;

use App\Ai\QuickEditOptionAgent;
use App\Jobs\CreateAiImage;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\User;
use App\Services\QuickImageService;
use App\Support\AppSettings;
use App\Support\QuickEditTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QuickTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_index_is_a_landing_for_the_persistent_composer(): void
    {
        $this->get(route('quick.index'))
            ->assertOk()
            ->assertSee(__('In just 3 clicks'))
            ->assertSee(__('Start Quick Edit'))
            ->assertSee(__('Click to upload a photo'))
            ->assertSee(__('Click to pick the right tool'))
            ->assertSee(__('Click to edit and get it now'))
            ->assertSee("\$dispatch('open-account-modal', { component: 'auth.login' })", false)
            ->assertSee(route('quick.remove-object'), false)
            ->assertDontSee(__('Start with the image'))
            ->assertDontSee(__('Step :number', ['number' => 1]))
            ->assertSee('<link rel="canonical" href="'.route('quick.index').'">', false);
    }

    public function test_authenticated_quick_index_opens_the_persistent_composer(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('quick.index'))
            ->assertOk()
            ->assertSee("\$dispatch('open-quick-composer', { tool: null })", false);
    }

    public function test_quick_edit_landings_are_public_and_keep_route_seo_content(): void
    {
        foreach (QuickEditTools::slugs() as $slug) {
            $tool = QuickEditTools::get($slug);

            $this->get(route('quick.'.$slug))
                ->assertOk()
                ->assertSee(__($tool['title']))
                ->assertSee(__($tool['overview']))
                ->assertSee(__('Frequently asked questions'))
                ->assertSee('BreadcrumbList')
                ->assertSee('FAQPage')
                ->assertSee('<meta name="robots" content="index,follow,max-image-preview:large">', false)
                ->assertSee('<link rel="canonical" href="'.route('quick.'.$slug).'">', false);
        }
    }

    public function test_quick_landings_have_specific_content_cover_and_seo_metadata(): void
    {
        foreach (QuickEditTools::all() as $slug => $tool) {
            $this->get(route('quick.'.$slug))
                ->assertOk()
                ->assertSee(__($tool['heading']))
                ->assertSee(__($tool['content_heading']))
                ->assertSee(asset($tool['thumbnail']), false)
                ->assertSee('<meta name="description" content="'.__($tool['seo_description']).'">', false)
                ->assertSee('<meta name="keywords" content="'.__($tool['keywords']).'">', false)
                ->assertSee('<meta property="og:image" content="'.asset($tool['thumbnail']).'">', false)
                ->assertDontSee(__('How it works'));
        }
    }

    public function test_guest_opening_quick_composer_requests_login(): void
    {
        Livewire::test('quick.composer')
            ->call('openComposer', 'remove-object')
            ->assertSet('showComposer', false)
            ->assertDispatched('open-account-modal');
    }

    public function test_composer_query_opens_quick_flyout_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams(['composer' => '1'])
            ->test('quick.composer')
            ->assertSet('showComposer', true);
    }

    public function test_composer_query_requests_login_for_guest(): void
    {
        Livewire::withQueryParams(['composer' => '1'])
            ->test('quick.composer')
            ->assertSet('showComposer', false)
            ->assertDispatched('open-account-modal');
    }

    public function test_quick_composer_accepts_only_valid_landing_context(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('quick.composer')
            ->call('openComposer', 'remove-object')
            ->assertSet('showComposer', true)
            ->assertSet('tool', 'remove-object')
            ->call('openComposer', 'invalid-tool')
            ->assertSet('tool', null);
    }

    public function test_ai_options_can_recommend_a_tool_outside_the_landing(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->configureAi();
        QuickEditOptionAgent::fake([[
            'options' => [
                ['tool' => 'replace-background', 'request' => 'Đổi nền thành studio sáng tối giản', 'reason' => 'Ảnh đã rõ nét và chủ thể phù hợp nền studio.'],
                ['tool' => 'remove-object', 'request' => 'Xóa biển hiệu phía sau chủ thể', 'reason' => 'Biển hiệu làm phân tán sự chú ý.'],
                ['tool' => 'change-outfit', 'request' => 'Đổi áo thành blazer màu be', 'reason' => 'Chân dung đủ rõ để thử trang phục khác.'],
            ],
        ]]);

        Livewire::actingAs($user)
            ->test('quick.composer')
            ->call('openComposer', 'restore-old-photo')
            ->set('photos', [UploadedFile::fake()->image('clear-photo.jpg')])
            ->call('analyzeImages')
            ->assertHasNoErrors()
            ->assertSet('suggestions.0.tool', 'replace-background')
            ->assertSet('selectedSuggestion', 0)
            ->assertSet('resolvedTool', 'replace-background')
            ->assertSet('request', 'Đổi nền thành studio sáng tối giản')
            ->assertSet('analyzed', true)
            ->assertSee(__('Choose a suitable edit'))
            ->call('chooseSuggestion', 1)
            ->assertSet('resolvedTool', 'remove-object')
            ->assertSet('request', 'Xóa biển hiệu phía sau chủ thể');

        QuickEditOptionAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Current landing context: restore-old-photo. It is not a constraint.')
            && $prompt->contains('replace-background')
            && $prompt->attachments !== []);
    }

    public function test_ai_options_recover_when_provider_ignores_structured_output(): void
    {
        $this->configureAi();
        $responses = [
            <<<'TEXT'
            1. `remove-object`
               Yêu cầu: “Xóa biển hiệu phía sau chủ thể.”
               Lý do: Biển hiệu làm phân tán sự chú ý.

            2. `replace-background`
               Yêu cầu: “Đổi nền thành studio sáng tối giản.”
               Lý do: Chủ thể phù hợp nền studio.

            3. `change-outfit`
               Yêu cầu: “Đổi áo thành blazer màu be.”
               Lý do: Chân dung đủ rõ để thử trang phục khác.
            TEXT,
            json_encode(['suggestions' => [
                ['tool_slug' => 'replace-background', 'request' => 'Đổi nền thành studio sáng tối giản.', 'reason' => 'Chủ thể phù hợp nền studio.'],
                ['tool_slug' => 'remove-object', 'request' => 'Xóa biển hiệu phía sau chủ thể.', 'reason' => 'Biển hiệu làm phân tán sự chú ý.'],
                ['tool_slug' => 'change-outfit', 'request' => 'Đổi áo thành blazer màu be.', 'reason' => 'Chân dung đủ rõ để thử trang phục khác.'],
            ]]),
        ];
        Http::fake(function () use (&$responses) {
            return Http::response([
                'model' => 'gpt-5.5',
                'choices' => [[
                    'message' => ['content' => array_shift($responses)],
                    'finish_reason' => 'stop',
                ]],
                'usage' => [],
            ]);
        });

        $service = app(QuickImageService::class);
        $markdownOptions = $service->suggestQuickOptions([UploadedFile::fake()->image('source.jpg')]);
        $suggestionsOptions = $service->suggestQuickOptions([UploadedFile::fake()->image('source.jpg')]);

        $this->assertCount(3, $markdownOptions);
        $this->assertSame('remove-object', $markdownOptions[0]['tool']);
        $this->assertSame('Xóa biển hiệu phía sau chủ thể.', $markdownOptions[0]['request']);
        $this->assertSame('Biển hiệu làm phân tán sự chú ý.', $markdownOptions[0]['reason']);
        $this->assertCount(3, $suggestionsOptions);
        $this->assertSame('replace-background', $suggestionsOptions[0]['tool']);
    }

    public function test_changing_uploaded_images_invalidates_previous_analysis(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->configureAi();
        QuickEditOptionAgent::fake([[
            'options' => [
                ['tool' => 'remove-object', 'request' => 'Remove the person behind me', 'reason' => 'A removable person is visible.'],
            ],
        ]]);

        Livewire::actingAs($user)
            ->test('quick.composer')
            ->call('openComposer')
            ->set('photos', [UploadedFile::fake()->image('source.jpg')])
            ->call('analyzeImages')
            ->assertSet('analyzed', true)
            ->set('newPhotos', [UploadedFile::fake()->image('reference.jpg')])
            ->assertSet('analyzed', false)
            ->assertSet('suggestions', [])
            ->assertSet('request', '');
    }

    public function test_quick_composer_requires_analysis_before_creation(): void
    {
        Storage::fake('public');
        Bus::fake();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('quick.composer')
            ->set('photos', [UploadedFile::fake()->image('source.jpg')])
            ->set('request', 'Đổi nền thành văn phòng sáng tự nhiên')
            ->call('createImage')
            ->assertSet('errorMessage', __('Analyze the images before creating.'));

        $this->assertDatabaseCount('generated_media', 0);
        Bus::assertNothingDispatched();
    }

    public function test_one_image_quick_edit_uses_default_role_model_metadata_and_queue(): void
    {
        Storage::fake('public');
        Bus::fake();
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->configureAi();
        QuickEditOptionAgent::fake([[
            'options' => [
                ['tool' => 'remove-object', 'request' => 'Remove the person behind me', 'reason' => 'The source clearly shows a removable person.'],
            ],
        ]]);

        Livewire::actingAs($user)
            ->test('quick.composer')
            ->call('openComposer', 'remove-object')
            ->set('photos', [UploadedFile::fake()->image('source.jpg')])
            ->call('analyzeImages')
            ->call('createImage')
            ->assertHasNoErrors()
            ->assertRedirect();

        $image = GeneratedMedia::query()->sole();
        $this->assertSame(AppSettings::defaultImageModel(), $image->model);
        $this->assertSame('remove-object', $image->preset);
        $this->assertSame('web', $image->source);
        $this->assertSame('quick', data_get($image->request_meta, 'generation_mode'));
        $this->assertSame(['source'], data_get($image->request_meta, 'reference_roles'));
        $this->assertSame('quick-v1', data_get($image->request_meta, 'prompt_contract'));
        $this->assertStringContainsString('Image 1 — SOURCE:', $image->prompt);
        $this->assertStringContainsString('Remove only the person or object specified', $image->prompt);
        Bus::assertDispatched(CreateAiImage::class, 1);
    }

    public function test_quick_edit_limits_references_and_requires_roles_for_multiple_images(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $photos = collect(range(1, AppSettings::maxReferencePhotos()))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image($index.'.jpg'))
            ->all();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please assign a role to every reference image.');

        app(QuickImageService::class)->createQuickPending(
            $request,
            $photos,
            'Place the authorized person beside the motorcycle',
            'add-person',
            ['reference_roles' => ['source']],
        );
    }

    public function test_all_tools_generate_distinct_role_contracts_and_valid_related_links(): void
    {
        foreach (QuickEditTools::all() as $slug => $tool) {
            $contract = QuickEditTools::contract($slug, [$tool['source_role']], 'Test request');

            $this->assertStringContainsString('QUICK EDIT REFERENCE ROLE CONTRACT', $contract);
            $this->assertStringContainsString($tool['instruction'], $contract);
            $this->assertStringContainsString('User request: Test request', $contract);
            $this->assertCount(3, $tool['use_cases']);
            $this->assertCount(3, $tool['content']);
            $this->assertCount(3, $tool['guide']);
            $this->assertFileExists(public_path($tool['thumbnail']));
            $this->assertCount(4, QuickEditTools::faqs($slug));

            foreach ($tool['related'] as $relatedSlug) {
                $this->assertNotNull(QuickEditTools::get($relatedSlug));
                $this->assertNotSame($slug, $relatedSlug);
            }
        }
    }

    private function configureAi(): void
    {
        Setting::putValue('ai.openai_url', 'https://example.test/v1');
        Setting::putValue('ai.openai_api_key', 'test-key');
    }
}
