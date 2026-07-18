<?php

use App\Models\ApiKey;
use App\Services\GeneratedMediaService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public bool $buttonOnly = false;

    #[On('image-usage-updated')]
    #[On('api-key-updated')]
    public function refreshUsage(): void
    {
        unset($this->userImageCount, $this->remainingToday, $this->dailyLimit, $this->apiKey);
    }

    #[Computed]
    public function apiKey(): ?ApiKey
    {
        return ApiKey::query()
            ->disableModelCaching()
            ->where('user_id', auth()->id())
            ->latest()
            ->first();
    }

    #[Computed]
    public function userImageCount(): int
    {
        return app(GeneratedMediaService::class)->guestImageCount(request());
    }

    #[Computed]
    public function remainingToday(): ?int
    {
        return app(GeneratedMediaService::class)->remainingToday(request());
    }

    #[Computed]
    public function dailyLimit(): int
    {
        return app(GeneratedMediaService::class)->dailyLimit();
    }
}; ?>

@auth
    @if ($buttonOnly)
        <flux:sidebar.item :href="route('history.index')" :current="\App\Support\LocalizedRoute::is('history.*')" wire:navigate>
            <x-slot name="icon"><x-iconsax-two-gallery-tick class="size-4" /></x-slot>
            {{ __(':count images created', ['count' => number_format($this->userImageCount)]) }}
        </flux:sidebar.item>
    @else
        @php
            $remainingToday = $this->remainingToday;
            $dailyLimit = $this->dailyLimit;
            $usedToday = $remainingToday === null ? null : max($dailyLimit - $remainingToday, 0);
            $apiKey = $this->apiKey;
            $dailyTooltip = $remainingToday === null
                ? __('Admin accounts are not limited by daily image quota.')
                : __(':remaining/:limit image generations left today.', ['remaining' => $remainingToday, 'limit' => $dailyLimit]);
            $apiTooltip = $apiKey
                ? __('Remaining :count', ['count' => number_format($apiKey->quotaRemaining())])
                : __('No API key yet.');
            $rowClass = 'block w-full space-y-1 rounded-sm p-2 text-start transition hover:bg-zinc-300/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-500 dark:hover:bg-white/10';
        @endphp

        <div class="space-y-1.5 rounded-md bg-zinc-200 p-1 dark:bg-white/10">
            <flux:tooltip :content="$dailyTooltip" position="top">
                <a class="{{ $rowClass }}" href="{{ route('history.index') }}" wire:navigate>
                    <div class="flex items-center justify-between gap-2 text-xs font-medium">
                        <span class="truncate">{{ __('Used today') }}</span>
                        <span class="shrink-0 tabular-nums">{{ $usedToday === null ? '0/∞' : $usedToday . '/' . $dailyLimit }}</span>
                    </div>
                    <flux:progress max="{{ max($dailyLimit, 1) }}" value="{{ $remainingToday ?? $dailyLimit }}" color="yellow" class="h-1!" />
                </a>
            </flux:tooltip>

            <flux:tooltip :content="$apiTooltip" position="top">
                <button class="{{ $rowClass }}" type="button" x-data x-on:click="$dispatch('open-account-modal', { component: 'settings.api-key' })">
                    <div class="flex items-center justify-between gap-2 text-xs font-medium">
                        <span class="truncate">{{ __('API key quota') }}</span>
                        <span class="shrink-0 tabular-nums">
                            @if ($apiKey)
                                {{ $apiKey->quota_used }}/{{ $apiKey->quota_limit }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <flux:progress max="{{ max($apiKey?->quota_limit ?? 1, 1) }}" value="{{ $apiKey?->quotaRemaining() ?? 0 }}" color="amber" class="h-1!" />
                </button>
            </flux:tooltip>
        </div>
    @endif
@endauth