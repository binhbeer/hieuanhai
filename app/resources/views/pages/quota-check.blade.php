<?php

use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quota Check')] class extends Component
{
    public string $token = '';

    public ?int $keyId = null;

    public ?string $message = null;

    public function check(): void
    {
        $this->validate(['token' => ['required', 'string', 'max:255']]);

        $key = AiApiKey::query()
            ->where('token_hash', AiApiKey::hashToken($this->token))
            ->first();

        if (! $key) {
            $this->keyId = null;
            $this->message = 'Không tìm thấy API key hoặc key không hợp lệ.';
            unset($this->key, $this->stats, $this->logs);

            return;
        }

        $this->keyId = $key->id;
        $this->message = null;
        unset($this->key, $this->stats, $this->logs);
    }

    #[Computed]
    public function key(): ?AiApiKey
    {
        return $this->keyId ? AiApiKey::find($this->keyId) : null;
    }

    #[Computed]
    public function stats(): array
    {
        if (! $this->key) {
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        $query = AiApiRequest::query()->where('ai_api_key_id', $this->key->id);

        return [
            'total' => (clone $query)->count(),
            'success' => (clone $query)->where('status', 'succeeded')->count(),
            'failed' => (clone $query)->where('status', '!=', 'succeeded')->count(),
        ];
    }

    #[Computed]
    public function logs()
    {
        return $this->key
            ? $this->key->requests()->latest()->limit(20)->get()
            : collect();
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6">
	<div class="space-y-1">
		<flux:heading size="xl">Quota Check</flux:heading>
		<flux:text variant="subtle">Nhập API key để xem quota và log request tóm tắt.</flux:text>
	</div>

	<flux:card class="space-y-4">
		<form class="grid gap-3 sm:grid-cols-[1fr_auto]" wire:submit="check">
			<flux:input wire:model="token" type="password" label="API key" placeholder="hai_xxx" required />
			<div class="flex items-end">
				<flux:button type="submit" variant="primary">Kiểm tra</flux:button>
			</div>
		</form>

		@if ($message)
			<div class="rounded-xl border border-red-400/30 bg-red-400/10 p-3 text-sm text-red-100">{{ $message }}</div>
		@endif
	</flux:card>

	@if ($this->key)
		<flux:card class="space-y-4">
			<div class="flex flex-wrap items-start justify-between gap-3">
				<div>
					<flux:heading size="lg">Quota</flux:heading>
					<flux:text variant="subtle">Prefix: <span class="font-mono">{{ $this->key->token_prefix }}...</span></flux:text>
					<flux:text variant="subtle">Dùng lần cuối: {{ $this->key->last_used_at?->diffForHumans() ?? 'Chưa dùng' }}</flux:text>
				</div>
			</div>

			<div class="grid gap-3 sm:grid-cols-3">
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">Limit</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->key->quota_limit) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">Đã dùng</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->key->quota_used) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">Còn lại</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->key->quotaRemaining()) }}</div>
				</div>
			</div>
		</flux:card>

		<flux:card class="space-y-4">
			<flux:heading size="lg">Thống kê request</flux:heading>
			<div class="grid gap-3 sm:grid-cols-3">
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">Tổng</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['total']) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">Success</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['success']) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">Fail</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['failed']) }}</div>
				</div>
			</div>
		</flux:card>

		<flux:card class="space-y-4">
			<flux:heading size="lg">Log mới nhất</flux:heading>
			<div class="space-y-2">
				@forelse ($this->logs as $log)
					<div class="rounded-xl bg-white/5 p-3 text-sm" wire:key="quota-log-{{ $log->id }}">
						<div class="font-medium">{{ $log->created_at?->format('Y-m-d H:i:s') }} · {{ $log->status }} · HTTP {{ $log->status_code }}</div>
						<flux:text variant="subtle">{{ number_format($log->duration_ms) }}ms · quota {{ $log->quota_charged ? 'charged' : 'free' }}</flux:text>
						@if ($log->error)
							<div class="mt-1 text-red-300">{{ Str::limit($log->error, 180) }}</div>
						@endif
					</div>
				@empty
					<flux:text variant="subtle">Chưa có request.</flux:text>
				@endforelse
			</div>
		</flux:card>
	@endif
</section>
