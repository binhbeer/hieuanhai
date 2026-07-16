<?php

use App\Models\ApiKey;
use App\Services\AiImageEditor;
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
        return app(AiImageEditor::class)->guestImageCount(request());
    }

    #[Computed]
    public function remainingToday(): ?int
    {
        return app(AiImageEditor::class)->remainingToday(request());
    }

    #[Computed]
    public function dailyLimit(): int
    {
        return app(AiImageEditor::class)->dailyLimit();
    }
}; ?>

@auth
    @if ($buttonOnly)
        <flux:sidebar.item :href="route('history.index')" :current="\App\Support\LocalizedRoute::is('history.*')" wire:navigate>
            <x-slot name="icon"><x-iconsax-bul-gallery class="size-4" /></x-slot>
            {{ __(':count images created', ['count' => number_format($this->userImageCount)]) }}
        </flux:sidebar.item>
    @else
        @php
            $remainingToday = $this->remainingToday;
            $dailyLimit = $this->dailyLimit;
            $apiKey = $this->apiKey;
            $dailyTooltip = $remainingToday === null
                ? __('Admin accounts are not limited by daily image quota.')
                : __(':remaining/:limit image generations left today.', ['remaining' => $remainingToday, 'limit' => $dailyLimit]);
            $apiTooltip = $apiKey
                ? __('Remaining :count', ['count' => number_format($apiKey->quotaRemaining())])
                : __('No API key yet.');
            $rowClass = 'block w-full space-y-1 rounded-sm p-1 text-start transition hover:bg-zinc-300/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-500 dark:hover:bg-white/10';
        @endphp

        <div class="space-y-1.5 rounded-md bg-zinc-200 p-2 dark:bg-white/10">
            <flux:tooltip :content="$dailyTooltip" position="top">
                <a class="{{ $rowClass }}" href="{{ route('history.index') }}" wire:navigate>
                    <div class="flex items-center justify-between gap-2 text-xs font-medium">
                        <span class="truncate">{{ __('Remaining today') }}</span>
                        <span class="shrink-0 tabular-nums">{{ $remainingToday === null ? '∞' : $remainingToday . '/' . $dailyLimit }}</span>
                    </div>
                    <flux:progress max="{{ max($dailyLimit, 1) }}" value="{{ $remainingToday ?? $dailyLimit }}" color="yellow" />
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
                    <flux:progress
                        max="{{ max($apiKey?->quota_limit ?? 1, 1) }}"
                        value="{{ $apiKey ? min($apiKey->quota_used, max($apiKey->quota_limit, 1)) : 0 }}"
                        color="amber"
                    />
                </button>
            </flux:tooltip>
        </div>
    @endif
@endauth
