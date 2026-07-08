<?php

use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quản lý API key')] class extends Component
{
    public ?string $newToken = null;

    public ?int $newTokenKeyId = null;

    public int $quotaLimit = 100;

    public array $quotaLimits = [];

    public function mount(): void
    {
        abort_unless(Auth::id() === 1, 403);

        $this->syncQuotaLimits();
    }

    public function createKey(): void
    {
        $this->validate(['quotaLimit' => ['required', 'integer', 'min:0', 'max:1000000000']]);

        $token = AiApiKey::newToken();
        $key = AiApiKey::create([
            'user_id' => Auth::id(),
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
            'quota_limit' => $this->quotaLimit,
            'quota_used' => 0,
            'last_used_at' => null,
        ]);

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
            'last_used_at' => null,
        ]);

        $this->newToken = $token['plain'];
        $this->newTokenKeyId = $key->id;
        $this->refreshData();
    }

    public function updateQuota(int $id): void
    {
        $key = $this->findKey($id);

        if (! $key) {
            return;
        }

        $this->validate([
            "quotaLimits.$id" => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $key->update(['quota_limit' => (int) $this->quotaLimits[$id]]);
        $this->refreshData();
    }

    #[Computed]
    public function keys()
    {
        return AiApiKey::query()
            ->where('user_id', Auth::id())
            ->withCount([
                'requests',
                'requests as success_count' => fn ($query) => $query->where('status', 'succeeded'),
                'requests as failed_count' => fn ($query) => $query->where('status', '!=', 'succeeded'),
            ])
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

        return $keyIds->isEmpty()
            ? collect()
            : AiApiRequest::query()->with('key')->whereIn('ai_api_key_id', $keyIds)->latest()->limit(20)->get();
    }

    private function findKey(int $id): ?AiApiKey
    {
        return AiApiKey::query()
            ->where('user_id', Auth::id())
            ->find($id);
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
		<flux:heading size="xl">Quản lý API key</flux:heading>
		<flux:text variant="subtle">Tạo nhiều key với quota riêng. Key plaintext chỉ hiện một lần sau khi tạo hoặc regenerate.</flux:text>
	</div>

	@if ($newToken)
		<flux:card class="space-y-3 border-emerald-400/30 bg-emerald-400/10" wire:key="new-token-{{ $newTokenKeyId }}">
			<flux:heading size="lg">API key mới #{{ $newTokenKeyId }}</flux:heading>
			<div class="grid gap-2 sm:grid-cols-[1fr_auto_auto]" x-data="{ show: false, copied: false, token: @js($newToken) }">
				<input
					class="w-full rounded-lg border border-white/10 bg-white/10 px-3 py-2 font-mono text-sm text-white outline-none"
					readonly :type="show ? 'text' : 'password'" :value="token">
				<flux:button type="button" variant="filled" x-on:click="show = ! show"><span x-text="show ? 'Ẩn' : 'Show'"></span></flux:button>
				<flux:button type="button" variant="primary" x-on:click="navigator.clipboard.writeText(token); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? 'Copied' : 'Copy key'"></span></flux:button>
			</div>
			<flux:text class="text-sm" variant="subtle">Hãy copy ngay. Sau khi rời trang, key plaintext không thể xem lại.</flux:text>
		</flux:card>
	@endif

	<div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
		<flux:card class="space-y-4">
			<flux:heading size="lg">Tạo key mới</flux:heading>
			<form class="grid gap-3 sm:grid-cols-[1fr_auto]" wire:submit="createKey">
				<flux:input wire:model="quotaLimit" type="number" min="0" label="Quota lifetime" />
				<div class="flex items-end">
					<flux:button type="submit" variant="primary">Tạo key</flux:button>
				</div>
			</form>
		</flux:card>

		<flux:card class="space-y-4">
			<flux:heading size="lg">Thống kê tổng</flux:heading>
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
			<flux:heading size="lg">Danh sách API key</flux:heading>
			<flux:text variant="subtle">{{ number_format($this->keys->count()) }} key</flux:text>
		</div>

		<div class="overflow-x-auto">
			<table class="w-full min-w-5xl text-left text-sm">
				<thead class="text-zinc-400">
					<tr class="border-b border-white/10">
						<th class="px-3 py-2 font-medium">Key</th>
						<th class="px-3 py-2 font-medium">Trạng thái</th>
						<th class="px-3 py-2 font-medium">Quota</th>
						<th class="px-3 py-2 font-medium">Request</th>
						<th class="px-3 py-2 font-medium">Thời gian</th>
						<th class="px-3 py-2 font-medium">Thao tác</th>
					</tr>
				</thead>
				<tbody>
					@forelse ($this->keys as $apiKey)
						<tr class="border-b border-white/10" wire:key="api-key-row-{{ $apiKey->id }}">
							<td class="px-3 py-3 align-top">
								<div class="font-medium">#{{ $apiKey->id }}</div>
								<div class="font-mono text-xs text-zinc-400">{{ $apiKey->token_prefix }}...</div>
							</td>
							<td class="px-3 py-3 align-top">
								<span class="rounded-full bg-white/10 px-2 py-1 text-xs">{{ $apiKey->statusLabel() }}</span>
							</td>
							<td class="px-3 py-3 align-top">
								<div class="tabular-nums">{{ number_format($apiKey->quota_used) }} / {{ number_format($apiKey->quota_limit) }}</div>
								<flux:text class="text-xs" variant="subtle">Còn {{ number_format($apiKey->quotaRemaining()) }}</flux:text>
								<div class="mt-2 flex gap-2">
									<flux:input class="max-w-28" wire:model="quotaLimits.{{ $apiKey->id }}" type="number" min="0" />
									<flux:button type="button" size="sm" variant="filled" wire:click="updateQuota({{ $apiKey->id }})">Lưu</flux:button>
								</div>
							</td>
							<td class="px-3 py-3 align-top">
								<div class="tabular-nums">{{ number_format($apiKey->requests_count) }} total</div>
								<flux:text class="text-xs" variant="subtle">{{ number_format($apiKey->success_count) }} success · {{ number_format($apiKey->failed_count) }} fail</flux:text>
							</td>
							<td class="px-3 py-3 align-top">
								<div>Tạo: {{ $apiKey->created_at?->format('Y-m-d H:i') }}</div>
								<flux:text class="text-xs" variant="subtle">Dùng cuối: {{ $apiKey->last_used_at?->format('Y-m-d H:i') ?? 'Chưa dùng' }}</flux:text>
							</td>
							<td class="space-y-2 px-3 py-3 align-top">
								@if ($newToken && $newTokenKeyId === $apiKey->id)
									<div class="space-y-1" x-data="{ show: false, copied: false, token: @js($newToken) }">
										<div class="flex gap-2">
											<flux:button type="button" size="sm" variant="filled" x-on:click="show = ! show"><span x-text="show ? 'Ẩn' : 'Show'"></span></flux:button>
											<flux:button type="button" size="sm" variant="primary" x-on:click="navigator.clipboard.writeText(token); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? 'Copied' : 'Copy'"></span></flux:button>
										</div>
										<div class="max-w-48 truncate font-mono text-xs text-zinc-400" x-text="show ? token : 'Key mới đang hiện phía trên'"></div>
									</div>
								@endif
								<flux:button type="button" size="sm" variant="danger" wire:click="regenerateKey({{ $apiKey->id }})" wire:confirm="Regenerate sẽ làm key này hết hiệu lực. Tiếp tục?">Regenerate</flux:button>
							</td>
						</tr>
					@empty
						<tr>
							<td class="px-3 py-6 text-center text-zinc-400" colspan="6">Chưa có API key.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</flux:card>

	<flux:card class="space-y-3">
		<flux:heading size="lg">Curl mẫu</flux:heading>
		<pre class="overflow-x-auto rounded-xl bg-black/40 p-4 text-sm text-zinc-100"><code>curl -X POST {{ url('/api/ai/images') }} \
  -H "Authorization: Bearer {{ $newToken ?: 'hai_xxx' }}" \
  -F "prompt=Biến ảnh thành comic hiện đại, giữ nhận diện và bố cục" \
  -F "images[]=@/path/to/image.jpg"</code></pre>
	</flux:card>

	<flux:card class="space-y-4">
		<flux:heading size="lg">Log mới nhất</flux:heading>
		<div class="space-y-2">
			@forelse ($this->logs as $log)
				<div class="grid gap-2 rounded-xl bg-white/5 p-3 text-sm sm:grid-cols-[1fr_auto]" wire:key="api-log-{{ $log->id }}">
					<div>
						<div class="font-medium">Key #{{ $log->ai_api_key_id }} · {{ $log->status }} · HTTP {{ $log->status_code }}</div>
						<flux:text variant="subtle">{{ $log->created_at?->diffForHumans() }} · {{ number_format($log->duration_ms) }}ms · quota {{ $log->quota_charged ? 'charged' : 'free' }}</flux:text>
						@if ($log->error)
							<div class="mt-1 text-red-300">{{ $log->error }}</div>
						@endif
					</div>
					<div class="text-zinc-400">#{{ $log->id }}</div>
				</div>
			@empty
				<flux:text variant="subtle">Chưa có request.</flux:text>
			@endforelse
		</div>
	</flux:card>
</section>
