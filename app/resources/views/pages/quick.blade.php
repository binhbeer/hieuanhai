<?php

use App\Support\QuickEditTools;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quick')] class extends Component {
};
?>

@php
    $tools = QuickEditTools::all();
    $routeTool = request()->route('tool');
    $routeTool = is_string($routeTool) && QuickEditTools::get($routeTool) ? $routeTool : null;
    $toolConfig = $routeTool ? $tools[$routeTool] : null;
    $cover = $toolConfig['thumbnail'] ?? null;
@endphp

<section class="mx-auto w-full max-w-7xl space-y-12 px-3 pb-6 sm:px-6 sm:pb-10 sm:py-5">
    @if ($toolConfig)
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('home')" wire:navigate>{{ __('Home') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('quick.index')" wire:navigate>{{ __('Quick') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __($toolConfig['title']) }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    @endif

    <section @class([
        'grid items-center gap-6 sm:gap-8',
        'md:grid-cols-[160px_minmax(0,1fr)] lg:gap-12' => $cover,
        'lg:grid-cols-5 lg:gap-12' => !$cover,
    ]) aria-labelledby="quick-title">
        @if ($cover)
            <figure class="w-[160px] shrink-0 overflow-hidden rounded-3xl border border-zinc-200/80 bg-[#f4efe8] shadow-sm dark:border-white/10">
                <img src="{{ asset($cover) }}" alt="{{ __($toolConfig['cover_alt'] ?? $toolConfig['title']) }}" width="160" height="100" class="h-auto w-full" fetchpriority="high" decoding="async">
            </figure>
        @endif

        <div @class(['min-w-0' => $cover, 'lg:col-span-3' => !$cover])>
            <flux:badge color="amber" :icon="svg('iconsax-two-flash', 'size-4 me-1.5')">{{ __('Quick Edit') }}</flux:badge>
            <h1 id="quick-title" class="mt-3 text-2xl font-semibold tracking-[-.04em] text-zinc-950 sm:text-3xl lg:text-4xl dark:text-white">{{ $toolConfig ? __($toolConfig['heading'] ?? $toolConfig['title']) : __('In just 3 clicks') }}</h1>
            <p class="mt-3 text-base leading-6 sm:leading-7 text-zinc-600 sm:text-lg dark:text-zinc-300">{{ $toolConfig ? __($toolConfig['description']) : __('Upload an image first. GenAnh analyzes what is visible and suggests three practical edits before you write a request.') }}</p>
            <div class="mt-5 flex flex-wrap gap-3">
                @auth
                    <flux:button type="button" variant="primary" color="amber" x-data x-on:click="$dispatch('open-quick-composer', { tool: @js($routeTool) })">{{ __('Start Quick Edit') }}</flux:button>
                @else
                    <flux:button type="button" variant="primary" color="amber" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Start Quick Edit') }}</flux:button>
                @endauth
                @if (!$toolConfig)
                    <flux:button :href="route('gallery.index')" variant="outline" wire:navigate>{{ __('Browse Gallery') }}</flux:button>
                @endif
            </div>
        </div>

        @if (!$cover)
            <div class="rounded-3xl border border-zinc-200/80 bg-zinc-50/70 p-5 lg:col-span-2 dark:border-white/10 dark:bg-white/5">
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <flux:heading size="sm">{{ __('Quick photo edits') }}</flux:heading>
                        <flux:text class="mt-1" variant="subtle">{{ __('Auto suggestions / smart photo edits') }}</flux:text>
                    </div>
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-300/15 dark:text-amber-200"><x-iconsax-two-hierarchy class="size-5" /></span>
                </div>
                <ol class="mt-5 space-y-4 text-sm">
                    @foreach ([__('Click to upload a photo'), __('Click to pick the right tool'), __('Click to edit and get it now')] as $index => $label)
                        <li class="flex items-start gap-3">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-950 text-xs font-semibold text-white dark:bg-white dark:text-zinc-950">{{ $index + 1 }}</span>
                            <span class="pt-0.5 text-zinc-600 dark:text-zinc-300">{{ $label }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </section>

    @if ($toolConfig)
        <x-quick.landing-content :slug="$routeTool" :tool="$toolConfig" :tools="$tools" />
    @else
    <section class="space-y-6" aria-labelledby="quick-tools">
        <div class="mx-auto max-w-3xl text-center">
            <flux:heading id="quick-tools" size="xl">{{ __('Popular Quick Edit tools') }}</flux:heading>
            <flux:text class="mt-2" variant="subtle">{{ __('Choose a focused workflow. GenAnh keeps advanced model controls out of the way.') }}</flux:text>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-4">
            @foreach ($tools as $slug => $config)
            @php($toolThumbnail = $config['thumbnail'] ?? null)
            <article @class([
                'group overflow-hidden rounded-2xl border border-transparent bg-zinc-50 transition hover:-translate-y-1 hover:border-amber-300 hover:shadow-lg sm:rounded-3xl dark:bg-zinc-900 dark:hover:border-amber-300/30',
                'p-3 sm:p-4' => !$toolThumbnail,
            ])>
                <a href="{{ route('quick.' . $slug) }}" wire:navigate class="block h-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                    @if ($toolThumbnail)
                        <div class="m-2 mb-0 overflow-hidden rounded-xl border border-zinc-200 bg-[#f4efe8] sm:m-3 sm:mb-0 sm:rounded-2xl dark:border-white/10">
                            <img src="{{ asset($toolThumbnail) }}" alt="{{ __($config['title']) }}" width="320" height="200" class="aspect-8/5 w-full object-cover transition duration-300 group-hover:scale-[1.03]" loading="lazy" decoding="async">
                        </div>
                        <div class="p-3 sm:p-4">
                            <h2 class="text-sm font-semibold text-zinc-950 sm:text-base dark:text-white">{{ __($config['title']) }}</h2>
                            <p class="mt-1.5 text-xs leading-tight font-medium text-zinc-500 sm:mt-2 sm:text-sm sm:leading-5 dark:text-zinc-400">{{ __($config['description']) }}</p>
                        </div>
                    @else
                        <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 sm:size-11 sm:rounded-2xl dark:bg-white/10 dark:text-zinc-200"><x-iconsax-two-magic-star class="size-5" /></div>
                        <h2 class="mt-3 text-sm font-semibold text-zinc-950 sm:mt-4 sm:text-base dark:text-white">{{ __($config['title']) }}</h2>
                        <p class="mt-1.5 text-xs leading-tight font-medium text-zinc-500 sm:mt-2 sm:text-sm sm:leading-5 dark:text-zinc-400">{{ __($config['description']) }}</p>
                    @endif
                </a>
            </article>
            @endforeach
        </div>
    </section>
    @endif
</section>