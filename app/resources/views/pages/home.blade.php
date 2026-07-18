<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('AI image creation and editing')] class extends Component { };
?>

@php($siteName = \App\Support\AppSettings::string('site.name', config('app.name', 'GenAnh')))

<div class="relative isolate overflow-hidden">
    <main class="mx-auto w-full max-w-7xl space-y-20 px-3 sm:px-6 sm:py-5 lg:space-y-28">
        <section class="grid items-center gap-10 xl:grid-cols-2 xl:gap-12" aria-labelledby="home-title">
            <div>
                <flux:badge color="amber" icon="sparkles">{{ __('AI image workspace') }}</flux:badge>
                <h1 id="home-title" class="mt-3 text-2xl font-semibold tracking-[-.04em] text-zinc-950 sm:text-3xl lg:text-4xl dark:text-white">{{ __('Create, edit, and discover images with GenAnh') }}</h1>
                <p class="mt-3 text-base leading-6 sm:leading-7 text-zinc-600 sm:text-lg dark:text-zinc-300">{{ __('Turn ideas and references into polished visuals with focused AI tools, advanced controls, a practical Studio workflow, and a community Gallery for daily inspiration.') }}</p>

                <div class="mt-5 max-w-lg rounded-2xl bg-zinc-50/70 p-5 sm:p-5 dark:bg-white/5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-0 sm:divide-x sm:divide-zinc-200/80 dark:sm:divide-white/10">
                        <div class="sm:pe-4">
                            <div class="text-base font-semibold text-zinc-950 sm:text-sm dark:text-white">{{ __('Free') }}</div>
                            <div class="mt-1 text-sm leading-5 text-zinc-500 sm:text-xs sm:leading-4 dark:text-zinc-400">{{ __('Just sign up - no extra cost.') }}</div>
                        </div>
                        <div class="sm:px-4">
                            <div class="text-base font-semibold text-zinc-950 sm:text-sm dark:text-white">{{ __('Easy') }}</div>
                            <div class="mt-1 text-sm leading-5 text-zinc-500 sm:text-xs sm:leading-4 dark:text-zinc-400">{{ __('Simple tools, ready to use instantly.') }}</div>
                        </div>
                        <div class="sm:ps-4">
                            <div class="text-base font-semibold text-zinc-950 sm:text-sm dark:text-white">{{ __('Get it now') }}</div>
                            <div class="mt-1 text-sm leading-5 text-zinc-500 sm:text-xs sm:leading-4 dark:text-zinc-400">{{ __('Delivered right away when you ask, any time, 24/7.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative">
                <div class="flex items-center justify-between gap-3 pb-3">
                    <div>
                        <flux:heading size="lg">{{ __('Featured from the Gallery') }}</flux:heading>
                    </div>
                    <a href="{{ route('gallery.index') }}" wire:navigate class="shrink-0">
                        <flux:badge rounded color="amber" icon="sparkles">{{ __('Open Gallery') }}</flux:badge>
                    </a>
                </div>
                <livewire:home.featured-gallery />
            </div>
        </section>

        <section id="tools" class="scroll-mt-8" aria-labelledby="tools-title">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-w-2xl">
                    <flux:heading id="tools-title" class="mt-4" size="xl">{{ __('Four ways to move from idea to image') }}</flux:heading>
                    <flux:text class="mt-2" variant="subtle">{{ __('Start simple, add control when you need it, and keep every visual direction close at hand.') }}</flux:text>
                </div>
            </div>

            <div class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="group flex items-start gap-4 rounded-4xl bg-amber-50/70 p-6 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-amber-950/5 dark:bg-amber-400/10 dark:hover:shadow-black/20">
                    <div class="min-w-0 flex-1">
                        <flux:heading class="text-amber-700! dark:text-amber-300!" size="lg">{{ __('Quick') }}</flux:heading>
                        <flux:text class="mt-2 font-medium text-zinc-700! dark:text-zinc-200!">{{ __('Restore old photos, swap faces, replace backgrounds, remove objects, change outfits, add a person, and ID photos.') }}</flux:text>
                        <flux:button class="mt-5" :href="route('quick.index')" size="sm" variant="filled" color="amber" icon:trailing="arrow-right" wire:navigate>{{ __('Quick photo edit') }}</flux:button>
                    </div>
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-3xl bg-amber-100 text-amber-700 transition group-hover:scale-105 dark:bg-amber-300/15 dark:text-amber-200"><x-iconsax-two-flash class="size-9" /></span>
                </div>

                <div class="group flex items-start gap-4 rounded-4xl bg-emerald-50/70 p-6 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-950/5 dark:bg-emerald-400/10 dark:hover:shadow-black/20">
                    <div class="min-w-0 flex-1">
                        <flux:heading class="text-emerald-700! dark:text-emerald-300!" size="lg">{{ __('Creator') }}</flux:heading>
                        <flux:text class="mt-2 font-medium text-zinc-700! dark:text-zinc-200!">{{ __('Turn a rough idea into a sharp prompt - references, model, ratio, rewrite, and translate in one place.') }}</flux:text>
                        <flux:button class="mt-5" :href="route('creator.index')" size="sm" variant="filled" color="emerald" icon:trailing="arrow-right" wire:navigate>{{ __('Create images') }}</flux:button>
                    </div>
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-3xl bg-emerald-100 text-emerald-700 transition group-hover:scale-105 dark:bg-emerald-300/15 dark:text-emerald-200"><x-iconsax-two-magicpen class="size-9" /></span>
                </div>

                <div class="group flex items-start gap-4 rounded-4xl bg-violet-50/70 p-6 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-violet-950/5 dark:bg-violet-400/10 dark:hover:shadow-black/20">
                    <div class="min-w-0 flex-1">
                        <flux:heading class="text-violet-700! dark:text-violet-300!" size="lg">{{ __('Studio') }}</flux:heading>
                        <flux:text class="mt-2 font-medium text-zinc-700! dark:text-zinc-200!">{{ __('Product sets, campaign posters, menus, and presentation slides - with projects, versions, and batch runs.') }}</flux:text>
                        <flux:button class="mt-5" :href="route('studio.index')" size="sm" variant="filled" color="violet" icon:trailing="arrow-right" wire:navigate>{{ __('Batch edit') }}</flux:button>
                    </div>
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-3xl bg-violet-100 text-violet-700 transition group-hover:scale-105 dark:bg-violet-300/15 dark:text-violet-200"><x-iconsax-two-layer class="size-9" /></span>
                </div>

                <div class="group flex items-start gap-4 rounded-4xl bg-blue-50/70 p-6 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-blue-950/5 dark:bg-blue-400/10 dark:hover:shadow-black/20">
                    <div class="min-w-0 flex-1">
                        <flux:heading class="text-blue-700! dark:text-blue-300!" size="lg">{{ __('Gallery') }}</flux:heading>
                        <flux:text class="mt-2 font-medium text-zinc-700! dark:text-zinc-200!">{{ __('Find fresh looks by category and tag, then open any image for the prompt and a starting point of your own.') }}</flux:text>
                        <flux:button class="mt-5" :href="route('gallery.index')" size="sm" variant="filled" color="blue" icon:trailing="arrow-right" wire:navigate>{{ __('Browse Gallery') }}</flux:button>
                    </div>
                    <span class="flex size-16 shrink-0 items-center justify-center rounded-3xl bg-blue-100 text-blue-700 transition group-hover:scale-105 dark:bg-blue-300/15 dark:text-blue-200"><x-iconsax-two-image class="size-9" /></span>
                </div>
            </div>
        </section>

        <section class="relative overflow-hidden rounded-4xl border border-amber-200/80 bg-amber-50/70 px-6 py-8 shadow-sm sm:px-10 sm:py-10 dark:border-white/10 dark:bg-zinc-950 dark:text-white dark:shadow-xl" aria-labelledby="api-title">
            <div class="pointer-events-none absolute -top-24 right-0 size-72 rounded-full bg-amber-300/25 blur-3xl dark:bg-amber-400/20"></div>
            <div class="relative flex flex-col justify-between gap-8 lg:flex-row lg:items-center">
                <div class="max-w-2xl">
                    <flux:badge color="amber" icon="code-bracket">{{ __('For developers') }}</flux:badge>
                    <flux:heading id="api-title" class="mt-4 text-zinc-950! dark:text-white!" size="xl">{{ __('Automate image creation with the GenAnh API') }}</flux:heading>
                    <flux:text class="mt-3 text-zinc-600! dark:text-zinc-300!">{{ __('Create private or published images from your own product with a secure API key. Successful generations use one quota.') }}</flux:text>
                    <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                        <span class="rounded-full border border-amber-200/90 bg-white/80 px-3 py-1.5 dark:border-white/15 dark:bg-white/5">POST /api/ai/images</span>
                        <span class="rounded-full border border-amber-200/90 bg-white/80 px-3 py-1.5 dark:border-white/15 dark:bg-white/5">{{ __('Bearer token') }}</span>
                        <span class="rounded-full border border-amber-200/90 bg-white/80 px-3 py-1.5 dark:border-white/15 dark:bg-white/5">{{ __('Quota aware') }}</span>
                    </div>
                </div>
                <flux:button class="shrink-0" :href="route('guide.api')" variant="primary" color="amber" icon:trailing="arrow-up-right" wire:navigate>{{ __('Explore the API') }}</flux:button>
            </div>
        </section>

        <footer class="flex flex-col gap-3 border-t border-zinc-200/80 pt-6 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:text-zinc-400">
            <span>{{ __('Built for ideas that deserve a clear visual form.') }}</span>
            <div class="flex items-center gap-4">
                <a href="{{ route('gallery.index') }}" wire:navigate class="hover:text-zinc-900 hover:underline dark:hover:text-white">{{ __('Gallery') }}</a>
                <a href="{{ route('guide.api') }}" wire:navigate class="hover:text-zinc-900 hover:underline dark:hover:text-white">{{ __('API guide') }}</a>
            </div>
        </footer>
    </main>
</div>