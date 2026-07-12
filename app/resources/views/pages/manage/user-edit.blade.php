<?php

use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use App\Models\User;
use App\Support\AppSettings;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit user')] class extends Component
{
    public User $user;

    public string $name = '';

    public string $email = '';

    public string $role = 'user';

    public bool $banned = false;

    public bool $verified = false;

    public string $password = '';

    public string $password_confirmation = '';

    public ?int $apiKeyQuotaLimit = null;

    public ?string $newApiToken = null;

    public ?int $newApiTokenKeyId = null;

    public function mount(User $user): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role?->value ?? 'user';
        $this->banned = $user->banned_at !== null;
        $this->verified = $user->email_verified_at !== null;
        $this->syncApiKeyQuotaLimit();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user->id)],
            'role' => ['required', Rule::in(['admin', 'mod', 'user'])],
            'banned' => ['boolean'],
            'verified' => ['boolean'],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ]);

        $this->user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        if ($this->user->id !== auth()->id() && $this->user->id !== 1) {
            $this->user->banned_at = $validated['banned'] ? ($this->user->banned_at ?? now()) : null;
        }

        $this->user->email_verified_at = $validated['verified'] ? ($this->user->email_verified_at ?? now()) : null;

        if ($validated['password']) {
            $this->user->password = $validated['password'];
        }

        $this->user->save();
        $this->banned = $this->user->banned_at !== null;
        $this->verified = $this->user->email_verified_at !== null;
        $this->reset('password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('User saved.'));
    }

    public function generateApiKey(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $token = AiApiKey::newToken();
        $key = $this->apiKey;

        if ($key) {
            $key->update([
                'token_hash' => $token['hash'],
                'token_prefix' => $token['prefix'],
                'token' => $token['plain'],
                'last_used_at' => null,
            ]);
        } else {
            $key = AiApiKey::create([
                'user_id' => $this->user->id,
                'token_hash' => $token['hash'],
                'token_prefix' => $token['prefix'],
                'token' => $token['plain'],
                'quota_limit' => AppSettings::int('auth.member_request_limit', 100),
                'quota_used' => 0,
                'last_used_at' => null,
            ]);
        }

        $this->newApiToken = $token['plain'];
        $this->newApiTokenKeyId = $key->id;
        $this->refreshApiKeyData();
        $this->syncApiKeyQuotaLimit();

        Flux::toast(variant: 'success', text: __('API key generated.'));
    }

    public function saveApiKeyQuota(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $key = $this->apiKey;

        if (! $key) {
            return;
        }

        $validated = $this->validate([
            'apiKeyQuotaLimit' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $key->update(['quota_limit' => $validated['apiKeyQuotaLimit']]);
        $key->flushCache();
        $this->syncApiKeyQuotaLimit();
        $this->refreshApiKeyData();

        Flux::toast(variant: 'success', text: __('Quota saved.'));
    }

    public function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'mod' => 'Mod',
            default => 'User',
        };
    }

    #[Computed]
    public function apiKey(): ?AiApiKey
    {
        return AiApiKey::query()
            ->disableModelCaching()
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();
    }

    #[Computed]
    public function apiKeyStats(): array
    {
        $key = $this->apiKey;

        if (! $key) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        $query = AiApiRequest::query()->where('ai_api_key_id', $key->id);

        return [
            'total' => (clone $query)->count(),
            'success' => (clone $query)->where('status', 'succeeded')->count(),
            'failed' => (clone $query)->where('status', '!=', 'succeeded')->count(),
        ];
    }

    private function refreshApiKeyData(): void
    {
        unset($this->apiKey, $this->apiKeyStats);
    }

    private function syncApiKeyQuotaLimit(): void
    {
        $this->apiKeyQuotaLimit = $this->apiKey?->quota_limit;
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6">
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div class="space-y-1">
			<flux:heading size="xl">Edit user #{{ $user->id }}</flux:heading>
			<flux:text variant="subtle">{{ $user->email }}</flux:text>
		</div>
		<flux:button :href="route('manage.users.index')" variant="filled" wire:navigate>{{ __('User list') }}</flux:button>
	</div>

	<flux:card>
		<form class="space-y-5" wire:submit="save">
			<flux:input wire:model="name" :label="__('Name')" required />
			<flux:input wire:model="email" type="email" label="Email" required />

			<flux:select wire:model="role" label="Role">
				@foreach (['admin', 'mod', 'user'] as $roleOption)
					<flux:select.option value="{{ $roleOption }}">{{ $this->roleLabel($roleOption) }}</flux:select.option>
				@endforeach
			</flux:select>

			<div class="grid gap-4 sm:grid-cols-2">
				<flux:checkbox wire:model="verified" :label="__('Verified')" />
				<flux:checkbox wire:model="banned" :label="__('Ban this user')" :disabled="$user->id === auth()->id() || $user->id === 1" />
			</div>

			<div class="grid gap-4 sm:grid-cols-2">
				<flux:input wire:model="password" type="password" :label="__('New password')" viewable autocomplete="new-password" />
				<flux:input wire:model="password_confirmation" type="password" :label="__('Confirm password')" viewable autocomplete="new-password" />
			</div>
			<flux:text variant="subtle">{{ __('Leave password blank to keep it unchanged.') }}</flux:text>

			<div class="flex gap-3">
				<flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
				<flux:button :href="route('manage.users.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
			</div>
		</form>
	</flux:card>

	<flux:card class="space-y-4">
		<div class="space-y-1">
			<flux:heading size="lg">{{ __('API key quota') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Admin can adjust this user API key limit.') }}</flux:text>
		</div>

		@if ($newApiToken)
			<div class="space-y-2 rounded-xl border border-emerald-400/30 bg-emerald-400/10 p-4" wire:key="admin-api-token-{{ $newApiTokenKeyId }}">
				<flux:heading size="sm">{{ __('New API key #:id', ['id' => $newApiTokenKeyId]) }}</flux:heading>
				<flux:input class:input="font-mono" readonly copyable :value="$newApiToken" />
				<flux:text class="text-xs" variant="subtle">{{ __('Copy this key now. Generating another key invalidates it immediately.') }}</flux:text>
			</div>
		@endif

		@if ($this->apiKey)
			<div class="flex flex-wrap items-center justify-between gap-3">
				<flux:text variant="subtle">Prefix: <span class="font-mono">{{ $this->apiKey->token_prefix }}...</span> · {{ __('Last used:') }} {{ $this->apiKey->last_used_at?->diffForHumans() ?? __('Never used') }}</flux:text>
				<flux:button type="button" variant="danger" wire:click="generateApiKey" wire:confirm="{{ __('Regenerating will invalidate this key. Continue?') }}">{{ __('Regenerate API key') }}</flux:button>
			</div>

			<div class="grid gap-3 sm:grid-cols-3">
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">{{ __('Used') }}</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->apiKey->quota_used) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">{{ __('Remaining') }}</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->apiKey->quotaRemaining()) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">{{ __('Total') }}</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->apiKeyStats['total']) }}</div>
				</div>
			</div>

			<flux:progress max="{{ max($this->apiKey->quota_limit, 1) }}" value="{{ min($this->apiKey->quota_used, max($this->apiKey->quota_limit, 1)) }}" color="amber" />

			<form class="grid gap-3 sm:grid-cols-[1fr_auto]" wire:submit="saveApiKeyQuota">
				<flux:input wire:model="apiKeyQuotaLimit" type="number" min="0" :label="__('Quota lifetime')" />
				<div class="flex items-end">
					<flux:button type="submit" variant="primary">{{ __('Save quota') }}</flux:button>
				</div>
			</form>

		@else
			<flux:text variant="subtle">{{ __('User has no API key yet.') }}</flux:text>
			<flux:button type="button" variant="primary" wire:click="generateApiKey">{{ __('Generate API key') }}</flux:button>
		@endif
	</flux:card>
</section>
