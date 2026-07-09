<?php

use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use App\Support\AppSettings;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quota Check')] class extends Component {
    public string $token = '';

    public ?int $keyId = null;

    public ?string $message = null;

    public function check(): void
    {
        $this->validate(['token' => ['required', 'string', 'max:255']]);

        $key = AiApiKey::query()
            ->where('token_hash', AiApiKey::hashToken($this->token))
            ->first();

        if (!$key) {
            $this->keyId = null;
            $this->message = __('API key was not found or is invalid.');
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
        if (!$this->key) {
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
        return $this->key ? $this->key->requests()->latest()->limit(20)->get() : collect();
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6">
	<div class="space-y-1">
		<flux:heading size="xl">Quota Check</flux:heading>
		<flux:text variant="subtle">{{ __('Enter an API key to view quota and summarized request logs.') }}</flux:text>
	</div>

	<flux:card class="space-y-4">
		<form class="grid gap-3 sm:grid-cols-[1fr_auto]" wire:submit="check">
			<flux:input wire:model="token" type="password" label="API key" placeholder="hai_xxx" required />
			<div class="flex items-end">
				<flux:button type="submit" variant="primary">{{ __('Check') }}</flux:button>
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
					<flux:text variant="subtle">{{ __('Last used:') }} {{ $this->key->last_used_at?->diffForHumans() ?? __('Never used') }}
					</flux:text>
				</div>
			</div>

			<div class="grid gap-3 sm:grid-cols-3">
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">Limit</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->key->quota_limit) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">{{ __('Used') }}</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->key->quota_used) }}</div>
				</div>
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">{{ __('Remaining') }}</flux:text>
					<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->key->quotaRemaining()) }}</div>
				</div>
			</div>
		</flux:card>

		<flux:card class="space-y-4">
			<flux:heading size="lg">{{ __('Request stats') }}</flux:heading>
			<div class="grid gap-3 sm:grid-cols-3">
				<div class="rounded-xl bg-white/5 p-4">
					<flux:text variant="subtle">{{ __('Total') }}</flux:text>
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
			<flux:heading size="lg">{{ __('Latest logs') }}</flux:heading>
			<div class="space-y-2">
				@forelse ($this->logs as $log)
					<div class="rounded-xl bg-white/5 p-3 text-sm" wire:key="quota-log-{{ $log->id }}">
						<div class="font-medium">{{ $log->created_at?->format('Y-m-d H:i:s') }} · {{ $log->status }} · HTTP {{ $log->status_code }}</div>
						<flux:text variant="subtle">{{ number_format($log->duration_ms) }}ms · quota
							{{ $log->quota_charged ? 'charged' : 'free' }}</flux:text>
						@if ($log->error)
							<div class="mt-1 text-red-300">{{ Str::limit($log->error, 180) }}</div>
						@endif
					</div>
				@empty
					<flux:text variant="subtle">{{ __('No requests yet.') }}</flux:text>
				@endforelse
			</div>
		</flux:card>
	@endif

	@php
		$maxImages = min(3, max(1, AppSettings::int('ai.image_max_reference_photos', (int) config('ai.image_max_reference_photos', 1))));
		$maxUploadMb = (int) ceil(AppSettings::int('ai.image_upload_max_kb', (int) config('ai.image_upload_max_kb', 32768)) / 1024);
	@endphp

	<flux:card class="space-y-4">
		<div class="space-y-1">
			<flux:heading size="lg">{{ __('API usage guide') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Send JSON to create an image from a prompt, or multipart when reference images are included. Each successful request costs 1 quota.') }}</flux:text>
		</div>

		<div class="grid gap-3 sm:grid-cols-2">
			<div class="rounded-xl bg-white/5 p-4 text-sm">
				<div class="mb-2 font-medium">Endpoint</div>
				<div class="break-all font-mono text-xs">POST {{ url('/api/ai/images') }}</div>
			</div>
			<div class="rounded-xl bg-white/5 p-4 text-sm">
				<div class="mb-2 font-medium">{{ __('Authentication') }}</div>
				<div class="break-all font-mono text-xs">Authorization: Bearer hai_xxx</div>
			</div>
		</div>

		<div class="space-y-2 text-sm">
			<div class="font-medium">Body</div>
			<ul class="list-disc space-y-1 ps-5 text-zinc-300">
				<li>{!! __('<span class="font-mono">prompt</span>: required image generation request, maximum 1200 words.') !!}</li>
				<li>{!! __('<span class="font-mono">images[]</span>: optional, up to :count reference images, jpg, jpeg, png, webp, or avif, up to :mbMB each.', ['count' => $maxImages, 'mb' => $maxUploadMb]) !!}</li>
			</ul>
		</div>

		<pre class="overflow-x-auto rounded-xl bg-zinc-950 p-4 text-xs text-zinc-100"><code>curl -X POST '{{ url('/api/ai/images') }}' \
  -H 'Authorization: Bearer hai_xxx' \
  -H 'Content-Type: application/json' \
  -d '{"prompt":"Create a comic-style portrait"}'</code></pre>

		<pre class="overflow-x-auto rounded-xl bg-zinc-950 p-4 text-xs text-zinc-100"><code>curl -X POST '{{ url('/api/ai/images') }}' \
  -H 'Authorization: Bearer hai_xxx' \
  -F 'prompt=Turn this image into a comic-style portrait' \
  -F 'images[]=@/path/to/source.jpg'</code></pre>

		<div class="space-y-2 text-sm">
			<div class="font-medium">{{ __('Successful response') }}</div>
			<pre class="overflow-x-auto rounded-xl bg-zinc-950 p-4 text-xs text-zinc-100"><code>{
  "id": 123,
  "url": "https://example.com/storage/ai-images/result.png",
  "download_name": "ai-image-123.png",
  "status": "succeeded",
  "created_at": "2026-07-08T08:00:00.000000Z",
  "quota": {
    "limit": 100,
    "used": 1,
    "remaining": 99
  }
}</code></pre>
		</div>
	</flux:card>

</section>
