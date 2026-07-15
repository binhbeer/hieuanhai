<?php

use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\User;
use App\Support\AppSettings;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Edit user')] class extends Component
{
    use WithFileUploads, WithPagination;

    public User $user;

    public string $name = '';

    public string $email = '';

    public string $role = 'user';

    public bool $banned = false;

    public bool $verified = false;

    public string $password = '';

    public string $password_confirmation = '';

    public $avatar;

    public ?int $apiKeyQuotaLimit = null;

    public ?string $newApiToken = null;

    public ?int $newApiTokenKeyId = null;

    public string $logSearch = '';

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

    public function updateAvatar(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldAvatar = $this->user->avatar_path;
        $this->user->avatar_path = $this->avatar->store('avatars', 'public');
        $this->user->save();

        if ($oldAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        $this->reset('avatar');
        Flux::toast(variant: 'success', text: __('Avatar updated.'));
    }

    public function updatedLogSearch(): void
    {
        $this->resetPage();
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

        $token = ApiKey::newToken();
        $key = $this->apiKey;

        if ($key) {
            $key->update([
                'token_hash' => $token['hash'],
                'token_prefix' => $token['prefix'],
                'token' => $token['plain'],
                'last_used_at' => null,
            ]);
        } else {
            $key = ApiKey::create([
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
    public function apiKey(): ?ApiKey
    {
        return ApiKey::query()
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

        $query = ApiRequest::query()->where('api_key_id', $key->id);

        return [
            'total' => (clone $query)->count(),
            'success' => (clone $query)->where('status', 'succeeded')->count(),
            'failed' => (clone $query)->where('status', '!=', 'succeeded')->count(),
        ];
    }

    /**
     * @return list<array{date: \Illuminate\Support\Carbon, total: int, charged: int}>
     */
    #[Computed]
    public function dailyApiUsage(): array
    {
        $key = $this->apiKey;

        if (! $key) {
            return collect(range(0, 29))->map(fn (int $offset): array => [
                'date' => today()->subDays(29)->addDays($offset),
                'total' => 0,
                'charged' => 0,
            ])->all();
        }

        $from = today()->subDays(29);
        $rows = ApiRequest::query()
            ->where('api_key_id', $key->id)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(quota_charged = 1) as charged')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');

        return collect(range(0, 29))->map(function (int $offset) use ($from, $rows): array {
            $date = $from->copy()->addDays($offset);
            $row = $rows->get($date->toDateString());

            return [
                'date' => $date,
                'total' => (int) ($row->total ?? 0),
                'charged' => (int) ($row->charged ?? 0),
            ];
        })->all();
    }

    #[Computed]
    public function apiKeyLogs()
    {
        $key = $this->apiKey;

        if (! $key) {
            return ApiRequest::query()->whereRaw('0 = 1')->paginate(15);
        }

        $search = trim($this->logSearch);

        return ApiRequest::query()
            ->where('api_key_id', $key->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('status', 'like', '%'.$search.'%')
                        ->orWhere('error', 'like', '%'.$search.'%')
                        ->orWhere('ip_address', 'like', '%'.$search.'%')
                        ->orWhere('status_code', 'like', '%'.$search.'%')
                        ->orWhere('id', 'like', '%'.$search.'%');

                    if (in_array(strtolower($search), ['charged', 'free'], true)) {
                        $query->orWhere('quota_charged', strtolower($search) === 'charged');
                    }
                });
            })
            ->latest()
            ->paginate(15);
    }

    private function refreshApiKeyData(): void
    {
        unset($this->apiKey, $this->apiKeyStats, $this->dailyApiUsage, $this->apiKeyLogs);
        $this->resetPage();
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

	<flux:card class="space-y-5">
		<form wire:submit="updateAvatar" class="flex items-center gap-4">
			<flux:file-upload wire:model="avatar" accept="image/jpeg,image/png,image/webp" aria-label="{{ __('Avatar') }}">
				<div class="relative flex size-20 cursor-pointer items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-zinc-100 transition-colors hover:border-zinc-300 hover:bg-zinc-200 in-data-dragging:bg-zinc-200 in-data-loading:opacity-60 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15 dark:in-data-dragging:bg-white/15">
					@if ($avatar)
						<img src="{{ $avatar->temporaryUrl() }}" class="size-full object-cover" alt="{{ __('Avatar preview') }}" />
					@elseif ($user->avatar_path)
						<img src="{{ Storage::url($user->avatar_path) }}" class="size-full object-cover" alt="{{ $user->name }}" />
					@else
						<flux:icon name="user" variant="solid" class="size-8 text-zinc-500 dark:text-zinc-400" />
					@endif

					<div class="absolute bottom-0 right-0 rounded-full bg-white dark:bg-zinc-800">
						<flux:icon name="arrow-up-circle" variant="solid" class="size-6 text-zinc-500 dark:text-zinc-400" />
					</div>
				</div>
			</flux:file-upload>

			<div class="flex-1 space-y-2">
				<flux:heading size="sm">{{ __('Avatar') }}</flux:heading>
				<flux:text size="sm">{{ __('Choose a JPG, PNG, or WebP image up to 2 MB.') }}</flux:text>
				<flux:error name="avatar" />
				<flux:button type="submit" size="sm" variant="primary" wire:loading.attr="disabled" wire:target="avatar,updateAvatar" :disabled="! $avatar">
					{{ __('Save') }}
				</flux:button>
			</div>
		</form>

		<flux:separator />

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

			@php($dailyApiUsage = $this->dailyApiUsage)
			@php($maxDailyApiUsage = max(1, collect($dailyApiUsage)->max('total')))
			@php($periodApiUsage = collect($dailyApiUsage)->sum('total'))
			@php($periodCharged = collect($dailyApiUsage)->sum('charged'))

			<div class="space-y-5 border-t border-zinc-200 pt-5 dark:border-white/10">
				<div class="flex items-start justify-between gap-4">
					<div>
						<flux:text variant="subtle">{{ __('API key usage') }}</flux:text>
						<div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($periodApiUsage) }}</div>
						<flux:text class="mt-1 text-xs!" variant="subtle">{{ __('Last 30 days') }}</flux:text>
					</div>
					<div class="flex size-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300">
						<x-iconsax-two-key class="size-5" />
					</div>
				</div>

				<div class="flex h-40 items-end gap-1 border-b border-zinc-200 dark:border-white/10" role="img" aria-label="{{ __('Daily API key requests for the last 30 days') }}">
					@foreach ($dailyApiUsage as $day)
						@php($requestHeight = $day['total'] / $maxDailyApiUsage * 100)
						@php($chargedHeight = $day['total'] > 0 ? $day['charged'] / $day['total'] * 100 : 0)
						<div class="group relative flex h-full min-w-0 flex-1 items-end" wire:key="daily-api-usage-{{ $day['date']->toDateString() }}">
							<div class="flex w-full flex-col-reverse overflow-hidden rounded-t bg-amber-200 dark:bg-amber-950" style="height: {{ $day['total'] > 0 ? max(4, $requestHeight) : 0 }}%">
								<div class="bg-amber-500" style="height: {{ $chargedHeight }}%"></div>
							</div>
							<div class="pointer-events-none absolute bottom-full inset-s-1/2 z-10 mb-2 hidden w-max max-w-48 -translate-x-1/2 rounded-lg bg-zinc-950 px-2 py-1.5 text-xs text-white shadow-lg group-hover:block group-focus-within:block">
								{{ $day['date']->format('d/m') }} · {{ __(':total requests, :part charged', ['total' => number_format($day['total']), 'part' => number_format($day['charged'])]) }}
							</div>
						</div>
					@endforeach
				</div>

				<div class="flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400">
					<span>{{ $dailyApiUsage[0]['date']->format('d/m') }}</span>
					<span>{{ __('30 days') }}</span>
					<span>{{ $dailyApiUsage[29]['date']->format('d/m') }}</span>
				</div>
				<div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
					<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-amber-500"></span>{{ __('Quota charged') }} · {{ number_format($periodCharged) }}</span>
					<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-amber-200 dark:bg-amber-950"></span>{{ __('Not charged') }} · {{ number_format($periodApiUsage - $periodCharged) }}</span>
				</div>
			</div>

			<div class="space-y-4 border-t border-zinc-200 pt-5 dark:border-white/10">
				<div class="flex flex-wrap items-end justify-between gap-3">
					<div class="space-y-1">
						<flux:heading size="lg">{{ __('Latest logs') }}</flux:heading>
						<flux:text variant="subtle">{{ __('Search by status, HTTP code, IP, error, or log ID.') }}</flux:text>
					</div>
					<div class="w-full sm:w-72">
						<flux:input wire:model.live.debounce.300ms="logSearch" :label="__('Search logs')" :placeholder="__('status, 429, charged...')" />
					</div>
				</div>

				<div class="space-y-2">
					@forelse ($this->apiKeyLogs as $log)
						<div class="grid gap-2 rounded-xl bg-white/5 p-3 text-sm sm:grid-cols-[1fr_auto]" wire:key="user-api-log-{{ $log->id }}">
							<div>
								<div class="font-medium">{{ $log->created_at?->format('Y-m-d H:i:s') }} · {{ $log->status }} · HTTP {{ $log->status_code }}</div>
								<flux:text variant="subtle">
									{{ number_format($log->duration_ms) }}ms
									· quota {{ $log->quota_charged ? 'charged' : 'free' }}
									@if ($log->ip_address)
										· {{ $log->ip_address }}
									@endif
									@if ($log->media_id)
										· image #{{ $log->media_id }}
									@endif
								</flux:text>
								@if ($log->error)
									<div class="mt-1 text-red-300">{{ \Illuminate\Support\Str::limit($log->error, 220) }}</div>
								@endif
							</div>
							<div class="text-zinc-400">#{{ $log->id }}</div>
						</div>
					@empty
						<flux:text variant="subtle">{{ $logSearch !== '' ? __('No matching logs.') : __('No requests yet.') }}</flux:text>
					@endforelse
				</div>

				<div>{{ $this->apiKeyLogs->links() }}</div>
			</div>

		@else
			<flux:text variant="subtle">{{ __('User has no API key yet.') }}</flux:text>
			<flux:button type="button" variant="primary" wire:click="generateApiKey">{{ __('Generate API key') }}</flux:button>
		@endif
	</flux:card>
</section>
