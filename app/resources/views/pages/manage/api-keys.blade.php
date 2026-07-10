<?php

use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage API keys')] class extends Component
{
    public ?string $newToken = null;

    public ?int $newTokenKeyId = null;

    public int $quotaLimit = 100;

    public array $quotaLimits = [];

    public function mount(): void
    {
        abort_unless(Auth::user() instanceof User && Auth::user()->isAdmin(), 403);

        $this->syncQuotaLimits();
    }

    public function createKey(): void
    {
        $this->validate(['quotaLimit' => ['required', 'integer', 'min:0', 'max:1000000000']]);

        $token = AiApiKey::newToken();
        $key = AiApiKey::query()->disableModelCaching()->where('user_id', Auth::id())->latest()->first();

        if ($key) {
            $key->update([
                'token_hash' => $token['hash'],
                'token_prefix' => $token['prefix'],
                'token' => $token['plain'],
                'quota_limit' => $this->quotaLimit,
                'last_used_at' => null,
            ]);
        } else {
            $key = AiApiKey::create([
                'user_id' => Auth::id(),
                'token_hash' => $token['hash'],
                'token_prefix' => $token['prefix'],
                'token' => $token['plain'],
                'quota_limit' => $this->quotaLimit,
                'quota_used' => 0,
                'last_used_at' => null,
            ]);
        }

        $this->newToken = $token['plain'];
        $this->newTokenKeyId = $key->id;
        $this->refreshData();
    }

    public function regenerateKey(int $id): void
    {
        $key = $this->findKey($id);

        if (! $key) {
            return;
        }

        $token = AiApiKey::newToken();
        $key->update([
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
            'token' => $token['plain'],
            'last_used_at' => null,
        ]);

        $this->newToken = $token['plain'];
        $this->newTokenKeyId = $key->id;
        $this->refreshData();
    }

    public function saveQuota(int $id): void
    {
        $this->updateQuota($id, $this->quotaLimits[$id] ?? null);
    }

    public function updateQuota(int $id, int|string|null $quotaLimit): void
    {
        $key = $this->findKey($id);

        if (! $key) {
            return;
        }

        validator(
            ['quota_limit' => $quotaLimit],
            [
                'quota_limit' => ['required', 'integer', 'min:0', 'max:1000000000'],
            ],
        )->validate();

        $this->quotaLimits[$id] = (int) $quotaLimit;
        $key->update(['quota_limit' => $this->quotaLimits[$id]]);
        $key->flushCache();
        $this->refreshData();
    }

    #[Computed]
    public function keys()
    {
        return AiApiKey::query()
            ->disableModelCaching()
            ->where('user_id', Auth::id())
            ->withCount(['requests', 'requests as success_count' => fn ($query) => $query->where('status', 'succeeded'), 'requests as failed_count' => fn ($query) => $query->where('status', '!=', 'succeeded')])
            ->latest()
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        $keyIds = $this->keys->pluck('id');

        if ($keyIds->isEmpty()) {
            return ['total' => 0, 'success' => 0, 'failed' => 0, 'avg_duration' => 0];
        }

        $query = AiApiRequest::query()->whereIn('ai_api_key_id', $keyIds);

        return [
            'total' => (clone $query)->count(),
            'success' => (clone $query)->where('status', 'succeeded')->count(),
            'failed' => (clone $query)->where('status', '!=', 'succeeded')->count(),
            'avg_duration' => (int) round((float) (clone $query)->avg('duration_ms')),
        ];
    }

    #[Computed]
    public function logs()
    {
        $keyIds = $this->keys->pluck('id');

        return $keyIds->isEmpty() ? collect() : AiApiRequest::query()->with('key')->whereIn('ai_api_key_id', $keyIds)->latest()->limit(20)->get();
    }

    private function findKey(int $id): ?AiApiKey
    {
        return AiApiKey::query()->disableModelCaching()->where('user_id', Auth::id())->find($id);
    }

    private function refreshData(): void
    {
        unset($this->keys, $this->stats, $this->logs);
        $this->syncQuotaLimits();
    }

    private function syncQuotaLimits(): void
    {
        foreach ($this->keys as $key) {
            $this->quotaLimits[$key->id] = $key->quota_limit;
        }
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
	<div class="space-y-1">
		<flux:heading size="xl">{{ __('Manage API keys') }}</flux:heading>
		<flux:text variant="subtle">{{ __('Each user has one API key. Generating again replaces the old token while keeping usage stats.') }}
		</flux:text>
	</div>

	@if ($newToken)
		<flux:card class="space-y-3 border-emerald-400/30 bg-emerald-400/10" wire:key="new-token-{{ $newTokenKeyId }}">
			<flux:heading size="lg">{{ __('New API key #:id', ['id' => $newTokenKeyId]) }}</flux:heading>
			<div class="grid gap-2 sm:grid-cols-[1fr_auto_auto]" x-data="{ show: false, copied: false, token: @js($newToken) }">
				<flux:input class="font-mono" readonly x-bind:type="show ? 'text' : 'password'" x-bind:value="token" />
				<flux:button type="button" variant="filled" x-on:click="show = ! show"><span x-text="show ? @js(__('Hide')) : @js(__('Show'))"></span>
				</flux:button>
				<flux:button type="button" variant="primary"
					x-on:click="navigator.clipboard.writeText(token); copied = true; setTimeout(() => copied = false, 1500)"><span
						x-text="copied ? 'Copied' : 'Copy key'"></span></flux:button>
			</div>
			<flux:text class="text-sm" variant="subtle">{{ __('This key can also be shown/copied again in the list below.') }}</flux:text>
		</flux:card>
	@endif

	<div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
		<flux:card class="space-y-4">
			<flux:heading size="lg">{{ __('Generate API key') }}</flux:heading>
			<form class="grid gap-3 sm:grid-cols-[1fr_auto]" wire:submit="createKey">
				<flux:input wire:model="quotaLimit" type="number" min="0" :label="__('Quota lifetime')" />
				<div class="flex items-end">
					<flux:button type="submit" variant="primary">{{ __('Generate API key') }}</flux:button>
				</div>
			</form>
		</flux:card>

		<flux:card class="space-y-4">
			<flux:heading size="lg">{{ __('Overall stats') }}</flux:heading>
			<div class="grid grid-cols-2 gap-3">
				<div class="rounded-xl bg-white/5 p-3">
					<flux:text variant="subtle">Request</flux:text>
					<div class="text-xl font-semibold tabular-nums">{{ number_format($this->stats['total']) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-3">
					<flux:text variant="subtle">Success</flux:text>
					<div class="text-xl font-semibold tabular-nums">{{ number_format($this->stats['success']) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-3">
					<flux:text variant="subtle">Fail</flux:text>
					<div class="text-xl font-semibold tabular-nums">{{ number_format($this->stats['failed']) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-3">
					<flux:text variant="subtle">Avg ms</flux:text>
					<div class="text-xl font-semibold tabular-nums">{{ number_format($this->stats['avg_duration']) }}</div>
				</div>
			</div>
		</flux:card>
	</div>

	<flux:card class="space-y-4">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<flux:heading size="lg">{{ __('API key list') }}</flux:heading>
			<flux:text variant="subtle">{{ number_format($this->keys->count()) }} key</flux:text>
		</div>

		<div class="overflow-x-auto">
			<table class="min-w-4xl w-full text-left text-sm">
				<thead class="text-zinc-400">
					<tr class="border-b border-white/10">
						<th class="px-3 py-2 font-medium">Key</th>
						<th class="px-3 py-2 font-medium">Quota</th>
						<th class="px-3 py-2 font-medium">Request</th>
						<th class="px-3 py-2 font-medium">{{ __('Time') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Actions') }}</th>
					</tr>
				</thead>
				<tbody>
					@forelse ($this->keys as $apiKey)
						<tr class="border-b border-white/10" wire:key="api-key-row-{{ $apiKey->id }}">
							<td class="space-y-2 px-3 py-3 align-top">
								<div>
									<div class="font-medium">#{{ $apiKey->id }}</div>
									<div class="font-mono text-xs text-zinc-400">{{ $apiKey->token_prefix }}...</div>
								</div>
								@if ($apiKey->token)
									<div class="space-y-1" x-data="{ show: false, copied: false, token: @js($apiKey->token) }">
										<div class="flex flex-wrap gap-2">
											<flux:button type="button" size="xs" variant="filled" x-on:click="show = ! show">
												<span x-text="show ? @js(__('Hide')) : @js(__('Show'))"></span>
											</flux:button>
											<flux:button type="button" size="xs" variant="primary"
												x-on:click="navigator.clipboard.writeText(token); copied = true; setTimeout(() => copied = false, 1500)">
												<span x-text="copied ? 'Copied' : 'Copy'"></span>
											</flux:button>
										</div>
										<div class="max-w-72 truncate font-mono text-xs text-zinc-400" x-text="show ? token : '••••••••••••'"></div>
									</div>
								@else
									<flux:text class="text-xs" variant="subtle">{{ __('Old keys do not store plaintext. Regenerate to show/copy.') }}</flux:text>
								@endif
							</td>
							<td class="px-3 py-3 align-top">
								<div class="flex items-center gap-2">
									<div class="tabular-nums">{{ number_format($apiKey->quota_used) }} / {{ number_format($apiKey->quota_limit) }}</div>
									<flux:dropdown position="bottom" align="end">
										<flux:button type="button" size="xs" variant="subtle" aria-label="{{ __('Edit quota') }}">
                                            <x-slot name="icon"><x-iconsax-two-edit-2 class="size-5" /></x-slot>
                                        </flux:button>
										<flux:popover class="w-56 space-y-3">
											<flux:input wire:model="quotaLimits.{{ $apiKey->id }}" type="number" min="0" label="Quota lifetime" />
											<div class="flex justify-end">
												<flux:button type="button" size="sm" variant="primary"
													wire:click="saveQuota({{ $apiKey->id }})">{{ __('Save') }}</flux:button>
											</div>
										</flux:popover>
									</flux:dropdown>
								</div>
								<flux:text class="text-xs" variant="subtle">{{ __('Remaining :count', ['count' => number_format($apiKey->quotaRemaining())]) }}</flux:text>
								<flux:progress class="mt-2" max="{{ max($apiKey->quota_limit, 1) }}"
									value="{{ min($apiKey->quota_used, max($apiKey->quota_limit, 1)) }}" color="amber" />
							</td>
							<td class="px-3 py-3 align-top">
								<div class="tabular-nums">{{ number_format($apiKey->requests_count) }} total</div>
								<flux:text class="text-xs" variant="subtle">{{ number_format($apiKey->success_count) }} success ·
									{{ number_format($apiKey->failed_count) }} fail</flux:text>
							</td>
							<td class="px-3 py-3 align-top">
								<div>{{ __('Created:') }} {{ $apiKey->created_at?->format('Y-m-d H:i') }}</div>
								<flux:text class="text-xs" variant="subtle">{{ __('Last used:') }}
									{{ $apiKey->last_used_at?->format('Y-m-d H:i') ?? __('Never used') }}</flux:text>
							</td>
							<td class="px-3 py-3 align-top">
								<flux:button type="button" size="sm" variant="danger" wire:click="regenerateKey({{ $apiKey->id }})"
									wire:confirm="{{ __('Regenerating will invalidate this key. Continue?') }}">Regenerate</flux:button>
							</td>
						</tr>
					@empty
						<tr>
							<td class="px-3 py-6 text-center text-zinc-400" colspan="5">{{ __('No API keys yet.') }}</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</flux:card>

	<flux:card class="space-y-3">
		<flux:heading size="lg">{{ __('Curl example') }}</flux:heading>
		<pre class="overflow-x-auto rounded-xl bg-black/40 p-4 text-sm text-zinc-100"><code>curl -X POST {{ url('/api/ai/images') }} \
  -H "Authorization: Bearer {{ $newToken ?: 'hai_xxx' }}" \
  -F "prompt=Turn the image into a modern comic while preserving identity and composition" \
  -F "images[]=@/path/to/image.jpg"</code></pre>
	</flux:card>

	<flux:card class="space-y-4">
		<flux:heading size="lg">{{ __('Latest logs') }}</flux:heading>
		<div class="space-y-2">
			@forelse ($this->logs as $log)
				<div class="grid gap-2 rounded-xl bg-white/5 p-3 text-sm sm:grid-cols-[1fr_auto]"
					wire:key="api-log-{{ $log->id }}">
					<div>
						<div class="font-medium">Key #{{ $log->ai_api_key_id }} · {{ $log->status }} · HTTP {{ $log->status_code }}
						</div>
						<flux:text variant="subtle">{{ $log->created_at?->diffForHumans() }} · {{ number_format($log->duration_ms) }}ms
							· quota {{ $log->quota_charged ? 'charged' : 'free' }}</flux:text>
						@if ($log->error)
							<div class="mt-1 text-red-300">{{ $log->error }}</div>
						@endif
					</div>
					<div class="text-zinc-400">#{{ $log->id }}</div>
				</div>
			@empty
				<flux:text variant="subtle">{{ __('No requests yet.') }}</flux:text>
			@endforelse
		</div>
	</flux:card>
</section>
