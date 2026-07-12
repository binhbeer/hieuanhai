<?php

use App\Models\AiApiKey;
use App\Models\AiApiRequest;
use App\Models\User;
use App\Support\AppSettings;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?string $newApiToken = null;

    public ?int $newApiTokenKeyId = null;

    public function generateApiKey(): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

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
                'user_id' => $user->id,
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

        Flux::toast(variant: 'success', text: __('API key generated.'));
    }

    #[Computed]
    public function apiKey(): ?AiApiKey
    {
        return AiApiKey::query()
            ->disableModelCaching()
            ->where('user_id', Auth::id())
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

    #[Computed]
    public function apiKeyLogs()
    {
        $key = $this->apiKey;

        return $key ? $key->requests()->latest()->limit(10)->get() : collect();
    }

    private function refreshApiKeyData(): void
    {
        unset($this->apiKey, $this->apiKeyStats, $this->apiKeyLogs);
    }
}; ?>

<section class="w-full">
    <flux:heading class="sr-only">{{ __('API key settings') }}</flux:heading>

    <x-settings.layout active="api-key" :heading="__('API key')" :subheading="__('Generate one API key for image API requests and track quota usage.')">
        <div class="mb-6">
            <flux:modal.trigger name="api-usage-guide">
                <flux:button type="button" variant="filled">{{ __('Guide') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <div class="space-y-6">
            @if ($newApiToken)
                <flux:card class="space-y-3 border-emerald-400/30 bg-emerald-400/10" wire:key="settings-api-token-{{ $newApiTokenKeyId }}">
                    <flux:heading size="lg">{{ __('New API key #:id', ['id' => $newApiTokenKeyId]) }}</flux:heading>
                    <flux:text variant="subtle">{{ __('This replaces your old API key immediately.') }}</flux:text>
                </flux:card>
            @endif

            @if ($this->apiKey)
                @php
                    $visibleApiToken = $newApiToken ?: $this->apiKey->token;
                @endphp

                <flux:card class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">API key #{{ $this->apiKey->id }}</flux:heading>
                            <flux:text variant="subtle">Prefix: <span class="font-mono">{{ $this->apiKey->token_prefix }}...</span></flux:text>
                            <flux:text variant="subtle">{{ __('Last used:') }} {{ $this->apiKey->last_used_at?->diffForHumans() ?? __('Never used') }}</flux:text>
                        </div>
                        <flux:button type="button" variant="danger" wire:click="generateApiKey" wire:confirm="{{ __('Regenerating will invalidate this key. Continue?') }}">
                            {{ __('Regenerate API key') }}
                        </flux:button>
                    </div>

                    @if ($visibleApiToken)
                        <div class="grid gap-2" x-data="{ show: false, copied: false, token: @js($visibleApiToken) }">
                            <flux:input class="font-mono" readonly x-bind:type="show ? 'text' : 'password'" x-bind:value="token" />
                            <div class="flex flex-wrap gap-2">
                                <flux:button type="button" size="sm" variant="filled" x-on:click="show = ! show"><span x-text="show ? @js(__('Hide')) : @js(__('Show'))"></span></flux:button>
                                <flux:button type="button" size="sm" variant="primary" x-on:click="navigator.clipboard.writeText(token); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy key'))"></span></flux:button>
                            </div>
                            <flux:text class="text-xs" variant="subtle">{{ __('This key is stored encrypted and can be shown/copied again here.') }}</flux:text>
                        </div>
                    @else
                        <flux:text class="text-sm" variant="subtle">{{ __('Old keys do not store plaintext. Regenerate to show/copy.') }}</flux:text>
                    @endif

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-white/5 p-4">
                            <flux:text variant="subtle">{{ __('Limit') }}</flux:text>
                            <div class="text-2xl font-semibold tabular-nums">{{ number_format($this->apiKey->quota_limit) }}</div>
                        </div>
                        <div class="rounded-xl bg-white/5 p-4">
                            <flux:text variant="subtle">{{ __('Used') }}</flux:text>
                            <div class="text-2xl font-semibold tabular-nums">{{ number_format($this->apiKey->quota_used) }}</div>
                        </div>
                        <div class="rounded-xl bg-white/5 p-4">
                            <flux:text variant="subtle">{{ __('Remaining') }}</flux:text>
                            <div class="text-2xl font-semibold tabular-nums">{{ number_format($this->apiKey->quotaRemaining()) }}</div>
                        </div>
                    </div>

                    <flux:progress max="{{ max($this->apiKey->quota_limit, 1) }}" value="{{ min($this->apiKey->quota_used, max($this->apiKey->quota_limit, 1)) }}" color="amber" />
                </flux:card>

                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('Request stats') }}</flux:heading>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-white/5 p-4">
                            <flux:text variant="subtle">{{ __('Total') }}</flux:text>
                            <div class="text-2xl font-semibold tabular-nums">{{ number_format($this->apiKeyStats['total']) }}</div>
                        </div>
                        <div class="rounded-xl bg-white/5 p-4">
                            <flux:text variant="subtle">{{ __('Success') }}</flux:text>
                            <div class="text-2xl font-semibold tabular-nums">{{ number_format($this->apiKeyStats['success']) }}</div>
                        </div>
                        <div class="rounded-xl bg-white/5 p-4">
                            <flux:text variant="subtle">{{ __('Fail') }}</flux:text>
                            <div class="text-2xl font-semibold tabular-nums">{{ number_format($this->apiKeyStats['failed']) }}</div>
                        </div>
                    </div>
                </flux:card>

                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('Latest logs') }}</flux:heading>
                    <div class="space-y-2">
                        @forelse ($this->apiKeyLogs as $log)
                            <div class="rounded-xl bg-white/5 p-3 text-sm" wire:key="settings-api-log-{{ $log->id }}">
                                <div class="font-medium">{{ $log->created_at?->format('Y-m-d H:i:s') }} · {{ $log->status }} · HTTP {{ $log->status_code }}</div>
                                <flux:text variant="subtle">{{ number_format($log->duration_ms) }}ms · quota {{ $log->quota_charged ? 'charged' : 'free' }}</flux:text>
                                @if ($log->error)
                                    <div class="mt-1 text-red-300">{{ Str::limit($log->error, 180) }}</div>
                                @endif
                            </div>
                        @empty
                            <flux:text variant="subtle">{{ __('No requests yet.') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            @else
                <flux:card class="space-y-4">
                    <flux:text variant="subtle">{{ __('No API key yet.') }}</flux:text>
                    <flux:button type="button" variant="primary" wire:click="generateApiKey">{{ __('Generate API key') }}</flux:button>
                </flux:card>
            @endif
        </div>
    </x-settings.layout>

    @php
        $maxImages = AppSettings::maxReferencePhotos();
        $maxUploadMb = (int) ceil(AppSettings::imageUploadMaxKb() / 1024);
    @endphp

    <flux:modal name="api-usage-guide" flyout variant="floating" class="md:w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="xl">{{ __('API usage guide') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Send JSON to create an image from a prompt, or multipart when reference images are included. Each successful request costs 1 quota.') }}</flux:text>
            </div>

            <flux:callout variant="secondary" icon="code-bracket">
                <flux:callout.heading>POST {{ url('/api/ai/images') }}</flux:callout.heading>
                <flux:callout.text><span class="font-mono text-xs">Authorization: Bearer genanh_xxx</span></flux:callout.text>
            </flux:callout>

            <div class="space-y-3">
                <flux:heading size="lg">Body</flux:heading>
                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                    <div class="flex items-start gap-3">
                        <flux:badge color="blue" size="sm">required</flux:badge>
                        <flux:text><code class="font-medium text-zinc-800 dark:text-white">prompt</code> — {{ __('Image generation request, maximum 1200 words.') }}</flux:text>
                    </div>
                    <flux:separator />
                    <div class="flex items-start gap-3">
                        <flux:badge color="zinc" size="sm">optional</flux:badge>
                        <flux:text><code class="font-medium text-zinc-800 dark:text-white">images[]</code> — {{ __('Up to :count reference images, jpg, jpeg, png, webp, or avif, up to :mbMB each.', ['count' => $maxImages, 'mb' => $maxUploadMb]) }}</flux:text>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <flux:heading size="lg">JSON</flux:heading>
                <pre class="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-950 p-4 text-xs leading-5 text-zinc-100 shadow-inner"><code>curl -X POST '{{ url('/api/ai/images') }}' \
  -H 'Authorization: Bearer genanh_xxx' \
  -H 'Content-Type: application/json' \
  -d '{"prompt":"Create a comic-style portrait"}'</code></pre>
            </div>

            <div class="space-y-3">
                <flux:heading size="lg">Multipart</flux:heading>
                <pre class="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-950 p-4 text-xs leading-5 text-zinc-100 shadow-inner"><code>curl -X POST '{{ url('/api/ai/images') }}' \
  -H 'Authorization: Bearer genanh_xxx' \
  -F 'prompt=Turn this image into a comic-style portrait' \
  -F 'images[]=@/path/to/source.jpg'</code></pre>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Successful response') }}</flux:heading>
                    <flux:badge color="green" size="sm">201</flux:badge>
                </div>
                <pre class="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-950 p-4 text-xs leading-5 text-zinc-100 shadow-inner"><code>{
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
        </div>
    </flux:modal>
</section>
