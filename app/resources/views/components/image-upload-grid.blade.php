@props([
    'model',
    'count' => 0,
    'limit' => 5,
    'heading' => null,
    'addLabel' => null,
    'multiple' => true,
    'accept' => 'image/jpeg,image/png,image/webp,image/avif',
])

@php
    $heading ??= __('Reference images');
    $addLabel ??= __('Add image');
@endphp

<div
    {{ $attributes }}
    x-data="{
        pasteError(text) {
            Flux.toast({ text, variant: 'danger' })
        },
        async pasteFromClipboard() {
            if (! navigator.clipboard?.read) {
                this.pasteError(@js(__('Your browser does not support pasting images.')))
                return
            }

            try {
                const items = await navigator.clipboard.read()
                const files = []

                for (const item of items) {
                    const type = item.types.find(type => type.startsWith('image/'))
                    if (! type) continue
                    const blob = await item.getType(type)
                    const ext = (type.split('/')[1] || 'png').replace('jpeg', 'jpg')
                    files.push(new File([blob], `clipboard-${Date.now()}.${ext}`, { type: blob.type }))
                }

                if (files.length === 0) {
                    this.pasteError(@js(__('No image found in clipboard.')))
                    return
                }

                if (@js($multiple)) {
                    $wire.uploadMultiple(@js($model), files.slice(0, {{ max(0, $limit - $count) }}), () => {}, () => {
                        this.pasteError(@js(__('Could not paste image. Please try again.')))
                    })
                } else {
                    $wire.upload(@js($model), files[0], () => {}, () => {
                        this.pasteError(@js(__('Could not paste image. Please try again.')))
                    })
                }
            } catch (error) {
                this.pasteError(@js(__('Could not read clipboard. Please allow clipboard access.')))
            }
        },
    }"
>
    <flux:card class="space-y-3 p-3!">
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-baseline gap-2">
                <flux:text class="shrink-0 tabular-nums" variant="subtle">{{ $count }}/{{ $limit }}</flux:text>
                <flux:heading class="truncate" size="sm">{{ $heading }}</flux:heading>
            </div>
            @if ($count < $limit)
                <flux:button type="button" size="sm" variant="ghost" tooltip="{{ __('Paste image') }}" tooltip:position="top" :aria-label="__('Paste image')" x-on:click="pasteFromClipboard()" wire:loading.attr="disabled" wire:target="{{ $model }}">
                    <x-slot name="icon"><x-iconsax-two-clipboard-import class="size-4" /></x-slot>
                </flux:button>
            @endif
        </div>

        <div class="grid grid-cols-5 gap-2">
            {{ $slot }}

            @if ($count < $limit)
                <flux:file-upload class="min-w-0" wire:model="{{ $model }}" :accept="$accept" :multiple="$multiple" :aria-label="$addLabel">
                    <div class="flex aspect-square size-full cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 text-zinc-500 transition hover:border-amber-300 hover:bg-amber-50/60 hover:text-amber-700 in-data-dragging:border-amber-400 in-data-dragging:bg-amber-50 dark:border-white/15 dark:bg-white/5 dark:text-zinc-400 dark:hover:border-amber-300/40 dark:hover:bg-amber-300/10 dark:hover:text-amber-200">
                        <flux:icon.plus class="size-6" />
                        <span class="hidden text-center text-[11px] font-medium leading-tight sm:block">{{ $addLabel }}</span>
                    </div>
                </flux:file-upload>
            @endif
        </div>
    </flux:card>
</div>
