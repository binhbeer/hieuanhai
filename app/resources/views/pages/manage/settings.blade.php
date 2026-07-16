<?php

use App\Models\Setting;
use App\Support\AppSettings;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Settings')] class extends Component
{
    public string $siteName = '';
    public string $homeTitle = '';
    public string $siteDescription = '';
    public string $siteKeywords = '';
    public string $googleMeasurementId = '';
    public string $zaloUrl = '';
    public bool $registrationEnabled = true;
    public bool $emailVerificationRequired = true;
    public bool $autoVerifyEmail = false;
    public int $memberRequestLimit = 100;
    public int $verifiedDailyImageLimit = 5;
    public string $aiProvider = 'openai';

    /** @var list<string> */
    public array $imageModels = [];

    /** @var list<string> */
    public array $textModels = [];

    public string $aiModel = '';
    public string $textModel = '';
    public string $aiReviewModel = '';
    public string $tagModel = '';
    public bool $promptTranslationEnabled = true;
    public bool $promptRewriteEnabled = true;
    public bool $imageToPromptEnabled = true;
    public string $promptTranslationModel = '';
    public string $promptRewriteModel = '';
    public string $imageToPromptModel = '';
    public int $aiTimeout = 600;
    public string $imageSize = 'auto';
    public string $imageQuality = 'auto';
    public string $imageDetail = 'high';
    public string $imageReferenceField = 'image';
    public int $maxReferencePhotos = 5;
    public int $uploadMaxKb = 32768;
    public string $openaiUrl = '';
    public string $openaiApiKey = '';
    public bool $showModelsModal = false;
    public string $modelsModalType = 'image';
    public ?string $modelsModalTarget = null;
    public string $newModelId = '';
    public string $modelSearch = '';
    public bool $useCustomModelId = false;

    /** @var list<string> */
    public array $availableModels = [];

    public ?string $modelCatalogError = null;

    /** @var array<string, 'success'|'error'> */
    public array $modelTestStatuses = [];

    public ?string $modelTestError = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $settings = Setting::allValues();
        $this->siteName = (string) $settings['site.name'];
        $this->homeTitle = (string) $settings['site.home_title'];
        $this->siteDescription = (string) $settings['site.description'];
        $this->siteKeywords = (string) $settings['site.keywords'];
        $this->googleMeasurementId = (string) $settings['analytics.google_measurement_id'];
        $this->zaloUrl = (string) $settings['contact.zalo_url'];
        $this->registrationEnabled = (bool) $settings['auth.registration_enabled'];
        $this->emailVerificationRequired = (bool) $settings['auth.email_verification_required'];
        $this->autoVerifyEmail = (bool) $settings['auth.auto_verify_email'];
        $this->memberRequestLimit = (int) $settings['auth.member_request_limit'];
        $this->verifiedDailyImageLimit = (int) $settings['auth.verified_daily_image_limit'];
        $this->aiProvider = (string) $settings['ai.image_provider'];
        $this->aiModel = (string) $settings['ai.image_model'];
        $this->textModel = (string) $settings['ai.text_model'];
        $this->aiReviewModel = (string) $settings['ai.image_review_model'];
        $this->tagModel = (string) $settings['ai.tag_model'];
        $this->promptTranslationEnabled = (bool) $settings['ai.prompt_translation_enabled'];
        $this->promptRewriteEnabled = (bool) $settings['ai.prompt_rewrite_enabled'];
        $this->imageToPromptEnabled = (bool) $settings['ai.image_to_prompt_enabled'];
        $this->promptTranslationModel = (string) $settings['ai.prompt_translation_model'];
        $this->promptRewriteModel = (string) $settings['ai.prompt_rewrite_model'];
        $this->imageToPromptModel = (string) $settings['ai.image_to_prompt_model'];
        $this->imageModels = $this->normalizedModels($settings['ai.image_models'] ?? [], [$this->aiModel]);
        $this->textModels = $this->normalizedModels($settings['ai.text_models'] ?? [], [
            $this->textModel,
            $this->aiReviewModel,
            $this->tagModel,
            $this->promptTranslationModel,
            $this->promptRewriteModel,
            $this->imageToPromptModel,
        ]);
        $this->aiTimeout = (int) $settings['ai.image_timeout'];
        $this->imageSize = (string) $settings['ai.image_size'];
        $this->imageQuality = (string) $settings['ai.image_quality'];
        $this->imageDetail = (string) $settings['ai.image_detail'];
        $this->imageReferenceField = (string) $settings['ai.image_reference_field'];
        $this->maxReferencePhotos = (int) $settings['ai.image_max_reference_photos'];
        $this->uploadMaxKb = (int) $settings['ai.image_upload_max_kb'];
        $this->openaiUrl = (string) $settings['ai.openai_url'];
    }

    public function openModelsModal(string $type, ?string $target = null): void
    {
        abort_unless(in_array($type, ['image', 'text'], true), 404);
        abort_unless($target === null || in_array($target, ['image', 'text_default', 'review', 'tag', 'translation', 'rewrite', 'image_to_prompt'], true), 404);

        $this->modelsModalType = $type;
        $this->modelsModalTarget = $target;
        $this->newModelId = '';
        $this->modelSearch = '';
        $this->useCustomModelId = false;
        $this->modelCatalogError = null;
        $this->resetValidation('newModelId');
        $this->showModelsModal = true;
        $this->loadAvailableModels();
    }

    public function loadAvailableModels(): void
    {
        $this->availableModels = [];
        $this->modelCatalogError = null;
        $url = rtrim($this->openaiUrl, '/');
        $apiKey = filled($this->openaiApiKey) ? $this->openaiApiKey : AppSettings::string('ai.openai_api_key');

        if ($url === '' || $apiKey === '') {
            $this->modelCatalogError = __('OpenAI-compatible URL and API key are required to load models.');

            return;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(min($this->aiTimeout, 30))
                ->get($url.'/models');

            if (! $response->successful()) {
                $message = data_get($response->json(), 'error.message');
                $this->modelCatalogError = __('Could not load models: :error', [
                    'error' => Str::limit(is_string($message) ? $message : 'HTTP '.$response->status(), 500),
                ]);

                return;
            }

            $models = collect($response->json('data', []))
                ->map(fn (mixed $item): mixed => is_array($item) ? ($item['id'] ?? null) : null)
                ->filter(fn (mixed $id): bool => is_string($id) && trim($id) !== '')
                ->map(fn (string $id): string => trim($id))
                ->unique()
                ->sort(SORT_NATURAL)
                ->values()
                ->all();

            $this->availableModels = $models;

            if ($models === []) {
                $this->modelCatalogError = __('The endpoint returned no models.');
            }
        } catch (\Throwable $e) {
            report($e);
            $this->modelCatalogError = __('Could not connect to the AI endpoint. Check the URL, API key, and server logs.');
        }
    }

    #[Computed]
    public function filteredAvailableModels(): array
    {
        $search = Str::lower(trim($this->modelSearch));
        $models = $this->availableModels;

        if ($search !== '') {
            $models = array_values(array_filter($models, fn (string $model): bool => str_contains(Str::lower($model), $search)));
        }

        return array_slice($models, 0, 100);
    }

    public function addModel(): void
    {
        $validated = $this->validateOnly('newModelId', [
            'newModelId' => ['required', 'string', 'max:120', 'regex:/^[^\p{C}]+$/u'],
        ]);
        $model = trim($validated['newModelId']);
        $models = $this->modelsModalType === 'image' ? $this->imageModels : $this->textModels;

        if (in_array($model, $models, true)) {
            throw ValidationException::withMessages(['newModelId' => __('This model already exists.')]);
        }

        $models[] = $model;
        sort($models, SORT_NATURAL);

        if ($this->modelsModalType === 'image') {
            $this->imageModels = $models;
        } else {
            $this->textModels = $models;
        }

        $this->selectModelForTarget($model);
        $this->newModelId = '';
        $this->resetValidation('newModelId');
    }

    public function removeModel(string $model): void
    {
        $models = $this->modelsModalType === 'image' ? $this->imageModels : $this->textModels;

        if (count($models) <= 1 || $this->modelUsages($model) !== []) {
            return;
        }

        $models = array_values(array_filter($models, fn (string $item): bool => $item !== $model));

        if ($this->modelsModalType === 'image') {
            $this->imageModels = $models;
        } else {
            $this->textModels = $models;
        }

        unset($this->modelTestStatuses[$this->modelTestKey($this->modelsModalType, $model)]);
    }

    public function testModel(string $type, string $model): void
    {
        abort_unless(in_array($type, ['image', 'text'], true), 404);
        $models = $type === 'image' ? $this->imageModels : $this->textModels;
        abort_unless(in_array($model, $models, true), 404);

        $key = $this->modelTestKey($type, $model);
        $this->modelTestError = null;
        unset($this->modelTestStatuses[$key]);

        $url = rtrim($this->openaiUrl, '/');
        $apiKey = filled($this->openaiApiKey) ? $this->openaiApiKey : AppSettings::string('ai.openai_api_key');

        if ($url === '' || $apiKey === '') {
            $this->modelTestStatuses[$key] = 'error';
            $this->modelTestError = __('OpenAI-compatible URL and API key are required to test a model.');

            return;
        }

        try {
            $request = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($this->aiTimeout);

            $response = $type === 'image'
                ? $request->post($url.'/images/generations', [
                    'model' => $model,
                    'prompt' => 'A simple solid blue square.',
                    'n' => 1,
                    'size' => $this->imageSize,
                    'quality' => $this->imageQuality,
                    'output_format' => 'png',
                ])
                : $request->post($url.'/chat/completions', [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => 'Reply with OK.']],
                    'max_tokens' => 8,
                ]);

            if ($response->successful()) {
                $this->modelTestStatuses[$key] = 'success';

                return;
            }

            $this->modelTestStatuses[$key] = 'error';
            $message = data_get($response->json(), 'error.message');
            $this->modelTestError = __('Model test failed: :error', [
                'error' => Str::limit(is_string($message) ? $message : 'HTTP '.$response->status(), 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->modelTestStatuses[$key] = 'error';
            $this->modelTestError = __('Could not connect to the AI endpoint. Check the URL, API key, and server logs.');
        }
    }

    public function closeModelsModal(): void
    {
        $this->showModelsModal = false;
        $this->newModelId = '';
        $this->modelSearch = '';
        $this->useCustomModelId = false;
        $this->availableModels = [];
        $this->modelCatalogError = null;
        $this->modelsModalTarget = null;
        unset($this->filteredAvailableModels);
        $this->resetValidation('newModelId');
    }

    /** @return list<string> */
    public function modelUsages(string $model): array
    {
        if ($this->modelsModalType === 'image') {
            return $model === $this->aiModel ? [__('Default')] : [];
        }

        return array_values(array_filter([
            $model === $this->textModel ? __('Default') : null,
            $model === $this->aiReviewModel ? __('Review') : null,
            $model === $this->tagModel ? __('Metadata and tags') : null,
            $model === $this->promptTranslationModel ? __('Prompt translation') : null,
            $model === $this->promptRewriteModel ? __('Prompt rewrite') : null,
            $model === $this->imageToPromptModel ? __('Image to prompt') : null,
        ]));
    }

    public function save(): void
    {
        $validated = $this->validate([
            'siteName' => ['required', 'string', 'max:120'],
            'homeTitle' => ['nullable', 'string', 'max:120'],
            'siteDescription' => ['nullable', 'string', 'max:500'],
            'siteKeywords' => ['nullable', 'string', 'max:500'],
            'googleMeasurementId' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'zaloUrl' => ['nullable', 'url:http,https', 'max:255'],
            'registrationEnabled' => ['boolean'],
            'emailVerificationRequired' => ['boolean'],
            'autoVerifyEmail' => ['boolean'],
            'memberRequestLimit' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'verifiedDailyImageLimit' => ['required', 'integer', 'min:0', 'max:1000'],
            'aiProvider' => ['required', 'string', 'in:openai'],
            'imageModels' => ['required', 'array', 'min:1'],
            'imageModels.*' => ['required', 'string', 'max:120', 'distinct', 'regex:/^[^\p{C}]+$/u'],
            'textModels' => ['required', 'array', 'min:1'],
            'textModels.*' => ['required', 'string', 'max:120', 'distinct', 'regex:/^[^\p{C}]+$/u'],
            'aiModel' => ['required', 'string', 'max:120'],
            'textModel' => ['required', 'string', 'max:120'],
            'aiReviewModel' => ['nullable', 'string', 'max:120'],
            'tagModel' => ['nullable', 'string', 'max:120'],
            'promptTranslationEnabled' => ['boolean'],
            'promptRewriteEnabled' => ['boolean'],
            'imageToPromptEnabled' => ['boolean'],
            'promptTranslationModel' => ['nullable', 'string', 'max:120'],
            'promptRewriteModel' => ['nullable', 'string', 'max:120'],
            'imageToPromptModel' => ['nullable', 'string', 'max:120'],
            'aiTimeout' => ['required', 'integer', 'min:10', 'max:1200'],
            'imageSize' => ['required', 'string', 'in:auto,1024x1024,1024x1536,1536x1024,1024x1792,1792x1024'],
            'imageQuality' => ['required', 'string', 'in:auto,low,medium,high,standard,hd'],
            'imageDetail' => ['required', 'string', 'in:auto,low,high,original'],
            'imageReferenceField' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'maxReferencePhotos' => ['required', 'integer', 'min:1', 'max:5'],
            'uploadMaxKb' => ['required', 'integer', 'min:1', 'max:102400'],
            'openaiUrl' => ['required', 'url:http,https', 'max:255'],
            'openaiApiKey' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->validateModelSelections($validated);

        $pairs = [
            'site.name' => $validated['siteName'],
            'site.home_title' => $validated['homeTitle'] ?? '',
            'site.description' => $validated['siteDescription'] ?? '',
            'site.keywords' => $validated['siteKeywords'] ?? '',
            'analytics.google_measurement_id' => $validated['googleMeasurementId'] ?? '',
            'contact.zalo_url' => filled($validated['zaloUrl'] ?? null) ? $validated['zaloUrl'] : false,
            'auth.registration_enabled' => (bool) $validated['registrationEnabled'],
            'auth.email_verification_required' => (bool) $validated['emailVerificationRequired'],
            'auth.auto_verify_email' => (bool) $validated['autoVerifyEmail'],
            'auth.member_request_limit' => (int) $validated['memberRequestLimit'],
            'auth.verified_daily_image_limit' => (int) $validated['verifiedDailyImageLimit'],
            'ai.image_provider' => $validated['aiProvider'],
            'ai.image_models' => array_values($validated['imageModels']),
            'ai.text_models' => array_values($validated['textModels']),
            'ai.image_model' => $validated['aiModel'],
            'ai.text_model' => $validated['textModel'],
            'ai.image_review_model' => $validated['aiReviewModel'] ?? '',
            'ai.tag_model' => $validated['tagModel'] ?? '',
            'ai.prompt_translation_enabled' => (bool) $validated['promptTranslationEnabled'],
            'ai.prompt_rewrite_enabled' => (bool) $validated['promptRewriteEnabled'],
            'ai.image_to_prompt_enabled' => (bool) $validated['imageToPromptEnabled'],
            'ai.prompt_translation_model' => $validated['promptTranslationModel'] ?? '',
            'ai.prompt_rewrite_model' => $validated['promptRewriteModel'] ?? '',
            'ai.image_to_prompt_model' => $validated['imageToPromptModel'] ?? '',
            'ai.image_timeout' => (int) $validated['aiTimeout'],
            'ai.image_size' => $validated['imageSize'],
            'ai.image_quality' => $validated['imageQuality'],
            'ai.image_detail' => $validated['imageDetail'],
            'ai.image_reference_field' => $validated['imageReferenceField'],
            'ai.image_max_reference_photos' => (int) $validated['maxReferencePhotos'],
            'ai.image_upload_max_kb' => (int) $validated['uploadMaxKb'],
            'ai.openai_url' => rtrim($validated['openaiUrl'], '/'),
        ];

        DB::transaction(function () use ($pairs, $validated): void {
            foreach ($pairs as $key => $value) {
                Setting::putValue($key, $value);
            }

            if (filled($validated['openaiApiKey'] ?? null)) {
                Setting::putValue('ai.openai_api_key', $validated['openaiApiKey']);
            }
        });
        $this->openaiApiKey = '';
        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    public function hasOpenAiKey(): bool
    {
        return filled(AppSettings::string('ai.openai_api_key'));
    }

    /** @param array<int, mixed> $models @param list<string> $extra @return list<string> */
    private function normalizedModels(mixed $models, array $extra): array
    {
        $models = is_array($models) ? $models : [];
        $models = array_filter([...$models, ...$extra], fn (mixed $model): bool => is_string($model) && trim($model) !== '');
        $models = array_values(array_unique(array_map(fn (string $model): string => trim($model), $models)));
        sort($models, SORT_NATURAL);

        return $models;
    }

    private function modelTestKey(string $type, string $model): string
    {
        return $type.':'.$model;
    }

    private function selectModelForTarget(string $model): void
    {
        match ($this->modelsModalTarget) {
            'image' => $this->aiModel = $model,
            'text_default' => $this->textModel = $model,
            'review' => $this->aiReviewModel = $model,
            'tag' => $this->tagModel = $model,
            'translation' => $this->promptTranslationModel = $model,
            'rewrite' => $this->promptRewriteModel = $model,
            'image_to_prompt' => $this->imageToPromptModel = $model,
            default => null,
        };
    }

    /** @param array<string, mixed> $validated */
    private function validateModelSelections(array $validated): void
    {
        $errors = [];

        if (! in_array($validated['aiModel'], $validated['imageModels'], true)) {
            $errors['aiModel'] = __('Select an image model from the managed list.');
        }

        if (! in_array($validated['textModel'], $validated['textModels'], true)) {
            $errors['textModel'] = __('Select a text model from the managed list.');
        }

        foreach (['aiReviewModel', 'tagModel', 'promptTranslationModel', 'promptRewriteModel', 'imageToPromptModel'] as $field) {
            if (filled($validated[$field] ?? null) && ! in_array($validated[$field], $validated['textModels'], true)) {
                $errors[$field] = __('Select a text model from the managed list or inherit the default.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}; ?>

<section class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div class="space-y-1">
			<flux:heading size="xl">{{ __('Settings') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Site metadata, AI API, and registration.') }}</flux:text>
		</div>
		<flux:button :href="route('manage.index')" variant="filled" wire:navigate>{{ __('Manage') }}</flux:button>
	</div>

	<form class="space-y-6" wire:submit="save">
		<flux:card class="space-y-4">
			<div>
				<flux:heading size="lg">Metadata site</flux:heading>
				<flux:text variant="subtle">{{ __('Used for basic title/meta tags.') }}</flux:text>
			</div>
			<flux:input wire:model="siteName" :label="__('Site name')" required />
			<flux:input wire:model="homeTitle" :label="__('Home title')" />
			<flux:textarea wire:model="siteDescription" rows="3" label="Description" />
			<flux:textarea wire:model="siteKeywords" rows="2" label="Keywords" />
			<flux:input wire:model="googleMeasurementId" :label="__('Google Analytics measurement ID')" placeholder="G-SZ9BZEKLZ1" />
			<flux:input wire:model="zaloUrl" type="url" :label="__('Upgrade Zalo URL')" :description="__('Leave blank to hide upgrade buttons.')" placeholder="http://zalo.me/0963559309" />
		</flux:card>

		<flux:card class="space-y-4">
			<div>
				<flux:heading size="lg">{{ __('Registration') }}</flux:heading>
				<flux:text variant="subtle">{{ __('Turn off to disable the registration page and action.') }}</flux:text>
			</div>
			<div class="grid gap-4 md:grid-cols-2 md:items-start">
				<div class="space-y-4">
					<flux:checkbox wire:model="registrationEnabled" :label="__('Allow new user registration')" />
					<flux:checkbox wire:model="emailVerificationRequired" :label="__('Require email verification after registration')" :description="__('Turn off to let new users log in immediately after registration.')" />
					<flux:checkbox wire:model="autoVerifyEmail" :label="__('Automatically verify email on registration')" :description="__('Mark new accounts as verified immediately without sending a verification email.')" />
				</div>
				<div class="space-y-4">
					<flux:input wire:model="memberRequestLimit" type="number" min="0" max="1000000000" :label="__('Requests per member')" :description="__('Default lifetime quota for newly created member API keys. Existing keys keep their own quota.')" required />
					<flux:input wire:model="verifiedDailyImageLimit" type="number" min="0" max="1000" :label="__('Free images per day')" :description="__('Daily free web image generations for logged-in members (verified, or on registration day). Admins are unlimited.')" required />
				</div>
			</div>
		</flux:card>

		<flux:card class="space-y-6">
			<div>
				<flux:heading size="lg">API AI</flux:heading>
				<flux:text variant="subtle">{{ __('One OpenAI-compatible connection shared by all image and text models.') }}</flux:text>
			</div>

			<div class="space-y-4">
				<flux:heading size="sm">{{ __('AI connection') }}</flux:heading>
				<div class="grid gap-4 sm:grid-cols-2">
					<flux:input wire:model="openaiUrl" type="url" label="OpenAI-compatible URL" required />
					<flux:input wire:model="openaiApiKey" type="password" label="OpenAI API key" placeholder="{{ $this->hasOpenAiKey() ? __('Saved; enter a new key to change it') : __('No key saved') }}" viewable />
				</div>
			</div>

			<div class="space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
				<flux:heading size="sm">{{ __('Request limits') }}</flux:heading>
				<div class="grid gap-4 sm:grid-cols-3">
					<flux:input wire:model="aiTimeout" type="number" min="10" max="1200" :label="__('Timeout seconds')" required />
					<flux:input wire:model="maxReferencePhotos" type="number" min="1" max="5" :label="__('Maximum reference images')" required />
					<flux:input wire:model="uploadMaxKb" type="number" min="1" max="102400" :label="__('Maximum upload KB')" required />
				</div>
			</div>

			<div class="space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
				<div>
					<flux:heading size="sm">{{ __('Image generation') }}</flux:heading>
					<flux:text variant="subtle">{{ __('Image models are managed separately from text models.') }}</flux:text>
				</div>
				<div class="flex items-end gap-2">
					<flux:select class="flex-1" wire:model="aiModel" variant="listbox" :label="__('Default image model')" required>
						@foreach ($imageModels as $model)
							<flux:select.option :value="$model">{{ $model }}</flux:select.option>
						@endforeach
					</flux:select>
					<flux:button type="button" icon="cog-6-tooth" variant="ghost" wire:click="openModelsModal('image', 'image')" :aria-label="__('Manage image models')" />
				</div>
				<flux:heading size="sm">{{ __('Image request options') }}</flux:heading>
				<div class="grid gap-4 sm:grid-cols-2">
					<flux:select wire:model="imageSize" variant="listbox" :label="__('Size')" required>
						@foreach (['auto', '1024x1024', '1024x1536', '1536x1024', '1024x1792', '1792x1024'] as $size)
							<flux:select.option :value="$size">{{ $size }}</flux:select.option>
						@endforeach
					</flux:select>
					<flux:select wire:model="imageQuality" variant="listbox" :label="__('Quality')" required>
						@foreach (['auto', 'low', 'medium', 'high', 'standard', 'hd'] as $quality)
							<flux:select.option :value="$quality">{{ $quality }}</flux:select.option>
						@endforeach
					</flux:select>
					<flux:select wire:model="imageDetail" variant="listbox" :label="__('Image detail')" required>
						@foreach (['auto', 'low', 'high', 'original'] as $detail)
							<flux:select.option :value="$detail">{{ $detail }}</flux:select.option>
						@endforeach
					</flux:select>
					<flux:input wire:model="imageReferenceField" :label="__('Reference image field')" required />
				</div>
			</div>

			<div class="space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
				<div>
					<flux:heading size="sm">{{ __('Text AI') }}</flux:heading>
					<flux:text variant="subtle">{{ __('Text tasks inherit the default model unless overridden.') }}</flux:text>
				</div>
				@foreach ([
					['field' => 'textModel', 'label' => __('Default text model'), 'target' => 'text_default', 'inherit' => false],
					['field' => 'aiReviewModel', 'label' => __('Review model'), 'target' => 'review', 'inherit' => true],
					['field' => 'tagModel', 'label' => __('Metadata and tags model'), 'target' => 'tag', 'inherit' => true],
				] as $row)
					<div class="flex items-end gap-2" wire:key="text-model-{{ $row['target'] }}">
						<flux:select class="flex-1" wire:model="{{ $row['field'] }}" variant="listbox" :label="$row['label']" :required="! $row['inherit']">
							@if ($row['inherit'])
								<flux:select.option value="">{{ __('Inherit default text model') }}</flux:select.option>
							@endif
							@foreach ($textModels as $model)
								<flux:select.option :value="$model">{{ $model }}</flux:select.option>
							@endforeach
						</flux:select>
						<flux:button type="button" icon="plus" variant="ghost" wire:click="openModelsModal('text', '{{ $row['target'] }}')" :aria-label="__('Manage text models')" />
					</div>
				@endforeach
			</div>
		</flux:card>

		<flux:card class="space-y-4">
			<div>
				<flux:heading size="lg">{{ __('Image creation tools') }}</flux:heading>
				<flux:text variant="subtle">{{ __('Enable prompt tools and choose a text model for each one.') }}</flux:text>
			</div>
			<div class="grid gap-4 md:grid-cols-2">
				@foreach ([
					['enabled' => 'promptTranslationEnabled', 'field' => 'promptTranslationModel', 'label' => __('Prompt translation'), 'description' => __('Translate the current prompt to Vietnamese.'), 'modelLabel' => __('Prompt translation model'), 'target' => 'translation'],
					['enabled' => 'promptRewriteEnabled', 'field' => 'promptRewriteModel', 'label' => __('Prompt rewrite'), 'description' => __('Rewrite the current prompt with AI instructions.'), 'modelLabel' => __('Prompt rewrite model'), 'target' => 'rewrite'],
					['enabled' => 'imageToPromptEnabled', 'field' => 'imageToPromptModel', 'label' => __('Image to prompt'), 'description' => __('Create a prompt by analyzing an uploaded image.'), 'modelLabel' => __('Image to prompt model'), 'target' => 'image_to_prompt'],
				] as $tool)
					<div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="image-tool-{{ $tool['target'] }}">
						<flux:checkbox wire:model="{{ $tool['enabled'] }}" :label="$tool['label']" :description="$tool['description']" />
						<div class="flex items-end gap-2">
							<flux:select class="flex-1" wire:model="{{ $tool['field'] }}" variant="listbox" :label="$tool['modelLabel']">
								<flux:select.option value="">{{ __('Inherit default text model') }}</flux:select.option>
								@foreach ($textModels as $model)
									<flux:select.option :value="$model">{{ $model }}</flux:select.option>
								@endforeach
							</flux:select>
							<flux:button type="button" icon="plus" variant="ghost" wire:click="openModelsModal('text', '{{ $tool['target'] }}')" :aria-label="__('Manage text models')" />
						</div>
					</div>
				@endforeach
			</div>
		</flux:card>

		<div class="flex gap-3">
			<flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
			<flux:button :href="route('manage.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
		</div>
	</form>

	<flux:modal name="manage-ai-models" wire:model.self="showModelsModal" class="w-full max-w-xl" @close="closeModelsModal">
		<div class="space-y-6">
			<div>
				<flux:heading size="lg">{{ $modelsModalType === 'image' ? __('Manage image models') : __('Manage text models') }}</flux:heading>
				<flux:text class="mt-2">{{ __('Add or remove model IDs available from the shared OpenAI-compatible endpoint.') }}</flux:text>
			</div>

			<div class="space-y-3">
				<flux:switch wire:model.live="useCustomModelId" :label="__('Custom model ID')" :description="__('Enable when the model is not returned by the endpoint catalog.')" />
				<div class="flex items-end gap-2">
					@if ($useCustomModelId)
						<flux:input class="flex-1" wire:model="newModelId" wire:keydown.enter.prevent="addModel" :label="__('Model ID')" :placeholder="__('Enter a custom model ID...')" />
					@else
						<flux:select class="flex-1" wire:model="newModelId" variant="combobox" :filter="false" :label="__('Model ID')" :placeholder="__('Search and select a model...')">
							<x-slot name="input">
								<flux:select.input wire:model.live.debounce.250ms="modelSearch" :placeholder="__('Search models...')" />
							</x-slot>
							@foreach ($this->filteredAvailableModels as $model)
								<flux:select.option :value="$model">{{ $model }}</flux:select.option>
							@endforeach
							<x-slot name="empty">
								<flux:select.option.empty>{{ __('No matching models.') }}</flux:select.option.empty>
							</x-slot>
						</flux:select>
					@endif
					<flux:button type="button" variant="primary" wire:click="addModel" :disabled="$newModelId === ''">{{ __('Add') }}</flux:button>
					@if (! $useCustomModelId)
						<flux:button type="button" variant="ghost" icon="arrow-path" wire:click="loadAvailableModels" :aria-label="__('Reload models')" />
					@endif
				</div>
				@if (! $useCustomModelId && $modelCatalogError)
					<flux:callout variant="warning" icon="exclamation-triangle">{{ $modelCatalogError }}</flux:callout>
				@endif
			</div>

			@if ($modelTestError)
				<flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Model test failed')">
					{{ $modelTestError }}
				</flux:callout>
			@endif

			<div class="max-h-80 space-y-2 overflow-y-auto">
				@php($models = $modelsModalType === 'image' ? $imageModels : $textModels)
				@forelse ($models as $model)
					@php($usages = $this->modelUsages($model))
					@php($testStatus = $modelTestStatuses[$modelsModalType.':'.$model] ?? null)
					<div class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="managed-model-{{ $modelsModalType }}-{{ md5($model) }}">
						<code class="min-w-0 flex-1 truncate text-sm">{{ $model }}</code>
						@foreach ($usages as $usage)
							<flux:badge size="sm">{{ $usage }}</flux:badge>
						@endforeach
						@if ($testStatus === 'success')
							<flux:icon.check-circle class="size-5 text-green-500" :aria-label="__('Model test passed')" />
						@elseif ($testStatus === 'error')
							<flux:icon.x-circle class="size-5 text-red-500" :aria-label="__('Model test failed')" />
						@endif
						<flux:button type="button" size="sm" variant="ghost" icon="beaker" wire:click="testModel(@js($modelsModalType), @js($model))" wire:loading.attr="disabled" wire:target="testModel" :aria-label="__('Test model')" />
						<flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeModel(@js($model))" :disabled="count($models) <= 1 || $usages !== []" :aria-label="__('Delete model')" />
					</div>
				@empty
					<flux:text variant="subtle">{{ __('No models yet.') }}</flux:text>
				@endforelse
			</div>

			<div class="flex justify-end">
				<flux:button type="button" variant="filled" wire:click="closeModelsModal">{{ __('Done') }}</flux:button>
			</div>
		</div>
	</flux:modal>
</section>
