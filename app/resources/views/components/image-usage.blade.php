<?php

use App\Services\AiImageEditor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public bool $buttonOnly = false;

    #[On('image-usage-updated')]
    public function refreshUsage(): void
    {
        unset($this->userImageCount, $this->remainingToday, $this->dailyLimit);
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
        <flux:sidebar.item icon="photo" :href="route('images.index')" :current="request()->routeIs('images.*')" wire:navigate>
            {{ __(':count images created', ['count' => number_format($this->userImageCount)]) }}
        </flux:sidebar.item>
    @else
        @php
            $remainingToday = $this->remainingToday;
            $dailyLimit = $this->dailyLimit;
        @endphp

        <div class="space-y-3 rounded-md bg-zinc-200 p-3 dark:bg-white/10">
            <flux:field>
                <flux:label>
                    {{ __('Remaining today') }}
                    <x-slot name="trailing">
                        <span class="tabular-nums">{{ $remainingToday === null ? '∞' : $remainingToday . '/' . $dailyLimit }}</span>
                    </x-slot>
                </flux:label>
                <flux:progress max="{{ $dailyLimit }}" value="{{ $remainingToday ?? $dailyLimit }}" color="yellow" />
                <flux:text class="text-xs" variant="subtle">
                    {{ $remainingToday === null ? __('Admin accounts are not limited by daily image quota.') : __(':remaining/:limit image generations left today.', ['remaining' => $remainingToday, 'limit' => $dailyLimit]) }}
                </flux:text>
            </flux:field>
        </div>
    @endif
@endauth