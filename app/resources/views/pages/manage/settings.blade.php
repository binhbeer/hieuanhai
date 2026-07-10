<?php

use App\Models\Setting;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Settings')] class extends Component
{
    public string $siteName = '';

    public string $homeTitle = '';

    public string $siteDescription = '';

    public string $siteKeywords = '';

    public string $googleMeasurementId = '';

    public bool $registrationEnabled = true;

    public bool $emailVerificationRequired = true;

    public string $aiProvider = 'openai';

    public string $aiModel = '';

    public string $aiReviewModel = '';

    public string $promptRewriteModel = '';

    public int $aiTimeout = 600;

    public string $imageSize = 'auto';

    public string $imageQuality = 'auto';

    public string $imageDetail = 'high';

    public string $imageReferenceField = 'image';

    public int $maxReferencePhotos = 1;

    public int $uploadMaxKb = 32768;

    public string $openaiUrl = '';

    public string $openaiApiKey = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->siteName = (string) Setting::getValue('site.name');
        $this->homeTitle = (string) Setting::getValue('site.home_title');
        $this->siteDescription = (string) Setting::getValue('site.description');
        $this->siteKeywords = (string) Setting::getValue('site.keywords');
        $this->googleMeasurementId = (string) Setting::getValue('analytics.google_measurement_id');
        $this->registrationEnabled = (bool) Setting::getValue('auth.registration_enabled');
        $this->emailVerificationRequired = (bool) Setting::getValue('auth.email_verification_required');
        $this->aiProvider = (string) Setting::getValue('ai.image_provider');
        $this->aiModel = (string) Setting::getValue('ai.image_model');
        $this->aiReviewModel = (string) Setting::getValue('ai.image_review_model');
        $this->promptRewriteModel = (string) Setting::getValue('ai.prompt_rewrite_model');
        $this->aiTimeout = (int) Setting::getValue('ai.image_timeout');
        $this->imageSize = (string) Setting::getValue('ai.image_size');
        $this->imageQuality = (string) Setting::getValue('ai.image_quality');
        $this->imageDetail = (string) Setting::getValue('ai.image_detail');
        $this->imageReferenceField = (string) Setting::getValue('ai.image_reference_field');
        $this->maxReferencePhotos = (int) Setting::getValue('ai.image_max_reference_photos');
        $this->uploadMaxKb = (int) Setting::getValue('ai.image_upload_max_kb');
        $this->openaiUrl = (string) Setting::getValue('ai.openai_url');
        $this->openaiApiKey = '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'siteName' => ['required', 'string', 'max:120'],
            'homeTitle' => ['nullable', 'string', 'max:120'],
            'siteDescription' => ['nullable', 'string', 'max:500'],
            'siteKeywords' => ['nullable', 'string', 'max:500'],
            'googleMeasurementId' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'registrationEnabled' => ['boolean'],
            'emailVerificationRequired' => ['boolean'],
            'aiProvider' => ['required', 'string', 'in:openai'],
            'aiModel' => ['required', 'string', 'max:120'],
            'aiReviewModel' => ['required', 'string', 'max:120'],
            'promptRewriteModel' => ['required', 'string', 'max:120'],
            'aiTimeout' => ['required', 'integer', 'min:10', 'max:1200'],
            'imageSize' => ['required', 'string', 'in:auto,1024x1024,1024x1536,1536x1024,1024x1792,1792x1024'],
            'imageQuality' => ['required', 'string', 'in:auto,low,medium,high,standard,hd'],
            'imageDetail' => ['required', 'string', 'in:auto,low,high,original'],
            'imageReferenceField' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'maxReferencePhotos' => ['required', 'integer', 'min:1', 'max:3'],
            'uploadMaxKb' => ['required', 'integer', 'min:1', 'max:102400'],
            'openaiUrl' => ['required', 'url', 'max:255'],
            'openaiApiKey' => ['nullable', 'string', 'max:2000'],
        ]);

        $pairs = [
            'site.name' => $validated['siteName'],
            'site.home_title' => $validated['homeTitle'] ?? '',
            'site.description' => $validated['siteDescription'] ?? '',
            'site.keywords' => $validated['siteKeywords'] ?? '',
            'analytics.google_measurement_id' => $validated['googleMeasurementId'] ?? '',
            'auth.registration_enabled' => (bool) $validated['registrationEnabled'],
            'auth.email_verification_required' => (bool) $validated['emailVerificationRequired'],
            'ai.image_provider' => $validated['aiProvider'],
            'ai.image_model' => $validated['aiModel'],
            'ai.image_review_model' => $validated['aiReviewModel'],
            'ai.prompt_rewrite_model' => $validated['promptRewriteModel'],
            'ai.image_timeout' => (int) $validated['aiTimeout'],
            'ai.image_size' => $validated['imageSize'],
            'ai.image_quality' => $validated['imageQuality'],
            'ai.image_detail' => $validated['imageDetail'],
            'ai.image_reference_field' => $validated['imageReferenceField'],
            'ai.image_max_reference_photos' => (int) $validated['maxReferencePhotos'],
            'ai.image_upload_max_kb' => (int) $validated['uploadMaxKb'],
            'ai.openai_url' => rtrim($validated['openaiUrl'], '/'),
        ];

        foreach ($pairs as $key => $value) {
            Setting::putValue($key, $value);
        }

        if (filled($validated['openaiApiKey'] ?? null)) {
            Setting::putValue('ai.openai_api_key', $validated['openaiApiKey']);
            $this->openaiApiKey = '';
        }

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    public function hasOpenAiKey(): bool
    {
        return filled(Setting::getValue('ai.openai_api_key'));
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
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
		</flux:card>

		<flux:card class="space-y-4">
			<div>
				<flux:heading size="lg">{{ __('Registration') }}</flux:heading>
				<flux:text variant="subtle">{{ __('Turn off to disable the registration page and action.') }}</flux:text>
			</div>
			<flux:checkbox wire:model="registrationEnabled" :label="__('Allow new user registration')" />
			<flux:checkbox wire:model="emailVerificationRequired" :label="__('Require email verification after registration')"
				:description="__('Turn off to let new users log in immediately after registration.')" />
		</flux:card>

		<flux:card class="space-y-4">
			<div>
				<flux:heading size="lg">API AI</flux:heading>
				<flux:text variant="subtle">{{ __('Only the OpenAI-compatible provider used for image generation is enabled.') }}</flux:text>
			</div>
			<div class="grid gap-4 sm:grid-cols-2">
				<flux:select wire:model="aiProvider" variant="listbox" label="Provider" required>
					<flux:select.option value="openai">openai</flux:select.option>
				</flux:select>
				<flux:input wire:model="aiModel" :label="__('Image model')" required />
				<flux:input wire:model="aiReviewModel" :label="__('Review model')" required />
				<flux:input wire:model="promptRewriteModel" :label="__('Prompt rewrite model')" required />
				<flux:input wire:model="aiTimeout" type="number" min="10" max="1200" :label="__('Timeout seconds')" required />
				<flux:select wire:model="imageSize" variant="listbox" :label="__('Size')" required>
					<flux:select.option value="auto">auto</flux:select.option>
					<flux:select.option value="1024x1024">1024x1024</flux:select.option>
					<flux:select.option value="1024x1536">1024x1536</flux:select.option>
					<flux:select.option value="1536x1024">1536x1024</flux:select.option>
					<flux:select.option value="1024x1792">1024x1792</flux:select.option>
					<flux:select.option value="1792x1024">1792x1024</flux:select.option>
				</flux:select>
				<flux:select wire:model="imageQuality" variant="listbox" :label="__('Quality')" required>
					<flux:select.option value="auto">auto</flux:select.option>
					<flux:select.option value="low">low</flux:select.option>
					<flux:select.option value="medium">medium</flux:select.option>
					<flux:select.option value="high">high</flux:select.option>
					<flux:select.option value="standard">standard</flux:select.option>
					<flux:select.option value="hd">hd</flux:select.option>
				</flux:select>
				<flux:select wire:model="imageDetail" variant="listbox" :label="__('Image detail')" required>
					<flux:select.option value="auto">auto</flux:select.option>
					<flux:select.option value="low">low</flux:select.option>
					<flux:select.option value="high">high</flux:select.option>
					<flux:select.option value="original">original</flux:select.option>
				</flux:select>
				<flux:input wire:model="imageReferenceField" :label="__('Reference image field')" required />
				<flux:input wire:model="maxReferencePhotos" type="number" min="1" max="3"
					:label="__('Maximum reference images')" required />
				<flux:input wire:model="uploadMaxKb" type="number" min="1" max="102400" :label="__('Maximum upload KB')"
					required />
			</div>
			<flux:input wire:model="openaiUrl" type="url" label="OpenAI-compatible URL" required />
			<flux:input wire:model="openaiApiKey" type="password" label="OpenAI API key"
				placeholder="{{ $this->hasOpenAiKey() ? __('Saved; enter a new key to change it') : __('No key saved') }}" viewable />
		</flux:card>

		<div class="flex gap-3">
			<flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
			<flux:button :href="route('manage.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
		</div>
	</form>
</section>
