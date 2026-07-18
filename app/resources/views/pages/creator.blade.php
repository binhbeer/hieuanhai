<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Creator')] class extends Component {};
?>

<section class="mx-auto w-full max-w-7xl space-y-12 px-3 pb-6 sm:px-6 sm:pb-10 sm:py-5">
    <section class="grid items-center gap-8 lg:grid-cols-5 lg:gap-12" aria-labelledby="creator-title">
        <div class="lg:col-span-3">
            <flux:badge color="emerald" :icon="svg('iconsax-two-magicpen', 'size-4 me-1.5')">{{ __('Creator') }}</flux:badge>
            <h1 id="creator-title" class="mt-3 text-2xl font-semibold tracking-[-.04em] text-zinc-950 sm:text-3xl lg:text-4xl dark:text-white">{{ __('Create images from prompts and references') }}</h1>
            <p class="mt-3 text-base leading-6 sm:leading-7 text-zinc-600 sm:text-lg dark:text-zinc-300">{{ __('Control prompt, references, model, aspect ratio, resolution, and image quality in one advanced workspace.') }}</p>
            <div class="mt-5 flex flex-wrap gap-3">
                @auth
                    <flux:modal.trigger name="image-composer">
                        <flux:button type="button" variant="primary" color="emerald" x-data x-on:click="$dispatch('open-image-composer')">{{ __('Open Creator') }}</flux:button>
                    </flux:modal.trigger>
                @else
                    <flux:button type="button" variant="primary" color="emerald" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Log in to create images') }}</flux:button>
                @endauth
                <flux:button :href="route('gallery.index')" variant="outline" wire:navigate>{{ __('Browse Gallery') }}</flux:button>
            </div>
        </div>

        <div class="rounded-3xl border border-zinc-200/80 bg-zinc-50/70 p-5 lg:col-span-2 dark:border-white/10 dark:bg-white/5">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <flux:heading size="sm">{{ __('How it works') }}</flux:heading>
                    <flux:text class="mt-1" variant="subtle">{{ __('Everything needed to move from an idea to a polished image, without leaving the workspace.') }}</flux:text>
                </div>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-300/15 dark:text-emerald-200"><x-iconsax-two-hierarchy class="size-5" /></span>
            </div>
            <ol class="mt-5 space-y-4 text-sm">
                @foreach ([
                    __('Prompt and references'),
                    __('Advanced controls'),
                    __('Edit and retry'),
                ] as $index => $label)
                    <li class="flex items-start gap-3">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-950 text-xs font-semibold text-white dark:bg-white dark:text-zinc-950">{{ $index + 1 }}</span>
                        <span class="pt-0.5 text-zinc-600 dark:text-zinc-300">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <livewire:creator.featured-gallery lazy />
</section>
