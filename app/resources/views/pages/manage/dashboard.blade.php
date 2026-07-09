<?php

use App\Models\AiApiKey;
use App\Models\AiImage;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage')] class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'users' => User::query()->count(),
            'banned_users' => User::query()->whereNotNull('banned_at')->count(),
            'api_keys' => AiApiKey::query()->count(),
            'categories' => Category::query()->count(),
            'published_images' => AiImage::query()->where('is_published', true)->count(),
            'unpublished_images' => AiImage::query()->where('is_published', false)->where('status', 'succeeded')->whereNotNull('result_path')->count(),
            'settings' => Setting::query()->count(),
        ];
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
	<div class="space-y-1">
		<flux:heading size="xl">{{ __('Manage') }}</flux:heading>
		<flux:text variant="subtle">{{ __('Users, API keys, and system settings.') }}</flux:text>
	</div>

	<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">User</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['users']) }}</div>
		</div>
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">{{ __('Banned') }}</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['banned_users']) }}</div>
		</div>
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">API key</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['api_keys']) }}</div>
		</div>
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">{{ __('Categories') }}</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['categories']) }}</div>
		</div>
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">Published</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['published_images']) }}</div>
		</div>
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">Unpublish</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['unpublished_images']) }}</div>
		</div>
	</div>

	<div class="grid gap-4 md:grid-cols-5">
		<flux:card class="space-y-3">
			<flux:heading size="lg">User</flux:heading>
			<flux:text variant="subtle">{{ __('List, edit roles, and ban accounts.') }}</flux:text>
			<flux:button :href="route('manage.users.index')" variant="primary" wire:navigate>{{ __('Manage users') }}</flux:button>
		</flux:card>
		<flux:card class="space-y-3">
			<flux:heading size="lg">API key</flux:heading>
			<flux:text variant="subtle">{{ __('Create keys, change quotas, and regenerate.') }}</flux:text>
			<flux:button :href="route('manage.api-keys.index')" variant="primary" wire:navigate>{{ __('Manage API keys') }}</flux:button>
		</flux:card>
		<flux:card class="space-y-3">
			<flux:heading size="lg">{{ __('Created images') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Filter, categorize, publish, or unpublish.') }}</flux:text>
			<flux:button :href="route('manage.images.index')" variant="primary" wire:navigate>{{ __('Manage images') }}</flux:button>
		</flux:card>
		<flux:card class="space-y-3">
			<flux:heading size="lg">{{ __('Categories') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Sort, edit, and hide gallery categories.') }}</flux:text>
			<flux:button :href="route('manage.categories.index')" variant="primary" wire:navigate>{{ __('Manage categories') }}</flux:button>
		</flux:card>
		<flux:card class="space-y-3">
			<flux:heading size="lg">Settings</flux:heading>
			<flux:text variant="subtle">{{ __('Site metadata, AI API, and registration.') }}</flux:text>
			<flux:button :href="route('manage.settings.index')" variant="primary" wire:navigate>{{ __('Settings') }}</flux:button>
		</flux:card>
	</div>
</section>
