<?php

use App\Models\ApiKey;
use App\Models\ApiRequest;
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
        $this->dispatch('api-key-updated');

        Flux::toast(variant: 'success', text: __('API key generated.'));
    }

    #[Computed]
    public function apiKey(): ?ApiKey
    {
        return ApiKey::query()
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

        $query = ApiRequest::query()->where('api_key_id', $key->id);

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

        return $key ? $key->requests()->latest()->limit(5)->get() : collect();
    }

    #[Computed]
    public function upgradeUrl(): string
    {
        return trim(AppSettings::string('contact.zalo_url'));
    }

    private function refreshApiKeyData(): void
    {
        unset($this->apiKey, $this->apiKeyStats, $this->apiKeyLogs);
    }
}; ?>

<section class="w-full">
    <flux:heading class="sr-only">{{ __('API key settings') }}</flux:heading>

    <x-settings.layout active="api-key">
        <div class="space-y-3">
            @if ($this->apiKey)
                @php
                    $visibleApiToken = $newApiToken ?: $this->apiKey->token;
                    $quotaTooltip = __('Remaining :count', ['count' => number_format($this->apiKey->quotaRemaining())]);
                @endphp

                <flux:card class="space-y-3 p-3!">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            @if ($this->upgradeUrl !== '')
                                <flux:button :href="$this->upgradeUrl" target="_blank" rel="noopener noreferrer" size="sm" variant="primary" icon="arrow-up-right">
                                    {{ __('Upgrade quota') }}
                                </flux:button>
                            @endif
                        </div>
                        <flux:modal.trigger name="api-usage-guide">
                            <flux:button type="button" size="sm" variant="filled" icon="book-open">
                                {{ __('Guide') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>

                    @if ($newApiToken)
                        <flux:callout variant="success" icon="check-circle" class="py-2!">
                            <flux:callout.text>{{ __('This replaces your old API key immediately.') }}</flux:callout.text>
                        </flux:callout>
                    @endif

                    @if ($visibleApiToken)
                        <div class="space-y-1.5" wire:key="api-token-{{ $this->apiKey->id }}-{{ $this->apiKey->token_prefix }}-{{ crc32($visibleApiToken) }}" x-data="{ show: false, copied: false, token: @js($visibleApiToken) }">
                            <div class="flex items-center gap-1.5">
                                <flux:input class="min-w-0 flex-1 font-mono text-xs" size="sm" readonly x-bind:type="show ? 'text' : 'password'" x-bind:value="token" />
                                <flux:tooltip position="top">
                                    <flux:button type="button" size="sm" variant="ghost" icon="eye" x-on:click="show = ! show" :aria-label="__('Show')" />
                                    <flux:tooltip.content>
                                        <span x-text="show ? @js(__('Hide')) : @js(__('Show'))"></span>
                                    </flux:tooltip.content>
                                </flux:tooltip>
                                <flux:tooltip position="top">
                                    <flux:button type="button" size="sm" variant="primary" icon="clipboard-document" x-on:click="navigator.clipboard.writeText(token); copied = true; setTimeout(() => copied = false, 1500)" :aria-label="__('Copy key')" />
                                    <flux:tooltip.content>
                                        <span x-text="copied ? @js(__('Copied')) : @js(__('Copy key'))"></span>
                                    </flux:tooltip.content>
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('Regenerate API key') }}" position="top">
                                    <flux:button type="button" size="sm" variant="danger" icon="arrow-path" wire:click="generateApiKey" wire:confirm="{{ __('Regenerating will invalidate this key. Continue?') }}" :aria-label="__('Regenerate API key')" />
                                </flux:tooltip>
                            </div>
                            <flux:text class="truncate text-xs" variant="subtle" :title="__('This key is stored encrypted and can be shown/copied again here.')">
                                #{{ $this->apiKey->id }} · {{ $this->apiKey->token_prefix }}… · {{ __('Last used:') }} {{ $this->apiKey->last_used_at?->diffForHumans() ?? __('Never used') }}
                            </flux:text>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <flux:text class="text-sm" variant="subtle">{{ __('Old keys do not store plaintext. Regenerate to show/copy.') }}</flux:text>
                            <flux:button type="button" size="sm" variant="danger" icon="arrow-path" wire:click="generateApiKey" wire:confirm="{{ __('Regenerating will invalidate this key. Continue?') }}">
                                {{ __('Regenerate API key') }}
                            </flux:button>
                        </div>
                    @endif

                    <flux:tooltip :content="$quotaTooltip" position="top">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between gap-2 text-xs font-medium">
                                <span>{{ __('API key quota') }}</span>
                                <span class="tabular-nums">{{ $this->apiKey->quota_used }}/{{ $this->apiKey->quota_limit }}</span>
                            </div>
                            <flux:progress max="{{ max($this->apiKey->quota_limit, 1) }}" value="{{ min($this->apiKey->quota_used, max($this->apiKey->quota_limit, 1)) }}" color="amber" />
                            <div class="flex items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ __('Remaining') }}: <span class="tabular-nums font-medium text-zinc-800 dark:text-zinc-100">{{ number_format($this->apiKey->quotaRemaining()) }}</span></span>
                                <span>{{ __('Total') }} {{ number_format($this->apiKeyStats['total']) }} · {{ __('Success') }} {{ number_format($this->apiKeyStats['success']) }} · {{ __('Fail') }} {{ number_format($this->apiKeyStats['failed']) }}</span>
                            </div>
                        </div>
                    </flux:tooltip>
                </flux:card>

                <flux:card class="space-y-2 p-3!">
                    <flux:heading size="sm">{{ __('Latest logs') }}</flux:heading>
                    <div class="max-h-40 space-y-1 overflow-y-auto">
                        @forelse ($this->apiKeyLogs as $log)
                            <div class="rounded-md bg-zinc-100 px-2.5 py-1.5 text-xs dark:bg-white/5" wire:key="settings-api-log-{{ $log->id }}">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 font-medium">
                                    <span class="tabular-nums text-zinc-500 dark:text-zinc-400">{{ $log->created_at?->format('m-d H:i') }}</span>
                                    <span>{{ $log->status }}</span>
                                    <span>HTTP {{ $log->status_code }}</span>
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ number_format($log->duration_ms) }}ms</span>
                                </div>
                                @if ($log->error)
                                    <flux:tooltip :content="$log->error" position="top">
                                        <div class="mt-0.5 truncate text-red-600 dark:text-red-300">{{ Str::limit($log->error, 80) }}</div>
                                    </flux:tooltip>
                                @endif
                            </div>
                        @empty
                            <flux:text class="text-xs" variant="subtle">{{ __('No requests yet.') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            @else
                <flux:card class="space-y-3 p-3!">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            @if ($this->upgradeUrl !== '')
                                <flux:button :href="$this->upgradeUrl" target="_blank" rel="noopener noreferrer" size="sm" variant="primary" icon="arrow-up-right">
                                    {{ __('Upgrade quota') }}
                                </flux:button>
                            @endif
                        </div>
                        <flux:modal.trigger name="api-usage-guide">
                            <flux:button type="button" size="sm" variant="filled" icon="book-open">
                                {{ __('Guide') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                    <div class="space-y-3 text-center">
                        <flux:text variant="subtle">{{ __('No API key yet.') }}</flux:text>
                        <flux:button type="button" size="sm" variant="primary" wire:click="generateApiKey">{{ __('Generate API key') }}</flux:button>
                    </div>
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
                <flux:text class="mt-2">{{ __('Send JSON to create an image from a prompt, or multipart when reference images are included. Requests are synchronous and accepted only through the API subdomain. Concurrent create and publish requests share your account limit. Each successful request costs 1 quota; HTTP 409 responses do not consume quota.') }}</flux:text>
            </div>

            <flux:callout variant="secondary" icon="bolt">
                <flux:callout.text>{{ __('Concurrent request limit: :count', ['count' => auth()->user()->api_image_concurrency_limit ?? 1]) }}</flux:callout.text>
            </flux:callout>

            <flux:callout variant="secondary" icon="code-bracket">
                <flux:callout.heading>POST https://api.{{ parse_url((string) config('app.url'), PHP_URL_HOST) }}/api/ai/images</flux:callout.heading>
                <flux:callout.text><span class="font-mono text-xs">Authorization: Bearer genanh_xxx</span></flux:callout.text>
            </flux:callout>

            <div class="space-y-3">
                <flux:heading size="lg">Body</flux:heading>
                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                    <div class="flex items-start gap-3">
                        <flux:badge color="blue" size="sm">required</flux:badge>
                        <flux:text><code class="font-medium text-zinc-800 dark:text-white">prompt</code> — {{ __('Image generation request, maximum 2,000 characters.') }}</flux:text>
                    </div>
                    <flux:separator />
                    <div class="flex items-start gap-3">
                        <flux:badge color="zinc" size="sm">optional</flux:badge>
                        <flux:text><code class="font-medium text-zinc-800 dark:text-white">model</code> — {{ __('Enabled image model ID. Uses the default image model when omitted.') }}</flux:text>
                    </div>
                    <div class="flex items-start gap-3">
                        <flux:badge color="zinc" size="sm">optional</flux:badge>
                        <flux:text><code class="font-medium text-zinc-800 dark:text-white">images[]</code> — {{ __('Up to :count reference images, jpg, jpeg, png, webp, or avif, up to :mbMB each.', ['count' => $maxImages, 'mb' => $maxUploadMb]) }}</flux:text>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <flux:heading size="lg">JSON</flux:heading>
                <pre class="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-950 p-4 text-xs leading-5 text-zinc-100 shadow-inner"><code>curl -X POST 'https://api.{{ parse_url((string) config('app.url'), PHP_URL_HOST) }}/api/ai/images' \
  -H 'Authorization: Bearer genanh_xxx' \
  -H 'Content-Type: application/json' \
  -d '{"prompt":"Create a comic-style portrait","model":"cx/gpt-5.5-image"}'</code></pre>
            </div>

            <div class="space-y-3">
                <flux:heading size="lg">Multipart</flux:heading>
                <pre class="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-950 p-4 text-xs leading-5 text-zinc-100 shadow-inner"><code>curl -X POST 'https://api.{{ parse_url((string) config('app.url'), PHP_URL_HOST) }}/api/ai/images' \
  -H 'Authorization: Bearer genanh_xxx' \
  -F 'prompt=Turn this image into a comic-style portrait' \
  -F 'model=cx/gpt-5.5-image' \
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
