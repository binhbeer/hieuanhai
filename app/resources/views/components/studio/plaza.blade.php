@props(['page'])

@php($samples = \App\Support\StudioSamples::all())

<div class="grid gap-4 md:grid-cols-2">
    <button type="button" wire:click="openTool('product-detail')" class="group relative flex min-h-36 items-stretch gap-4 overflow-hidden rounded-2xl bg-[#e0e6dc] p-5 text-left transition duration-300 hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:bg-emerald-950/40 sm:min-h-40 sm:p-6">
        <div class="relative z-10 flex min-w-0 flex-1 flex-col justify-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-full bg-white text-emerald-700 ring-1 ring-emerald-200/80 dark:bg-white/10 dark:text-emerald-300 dark:ring-white/10">
                <x-iconsax-two-gallery class="size-5" />
            </div>
            <div class="min-w-0">
                <h2 class="text-base font-semibold tracking-tight text-zinc-900 dark:text-white sm:text-lg">{{ __('Product detail images') }}</h2>
                <p class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ __('Turn one product photo into a complete listing image set.') }}</p>
            </div>
        </div>
        <div class="pointer-events-none relative h-24 w-28 shrink-0 self-center sm:h-28 sm:w-36" aria-hidden="true">
            <div class="absolute bottom-1 inset-s-0 w-[46%] -rotate-8 overflow-hidden rounded-xl bg-white p-1.5 shadow-md ring-1 ring-black/5 transition duration-300 group-hover:-rotate-12 dark:bg-zinc-800 dark:ring-white/10">
                <img class="aspect-4/3 w-full rounded-lg object-cover" src="{{ asset('images/studio-samples/wireless-headphones/input.webp') }}" width="800" height="800" alt="">
            </div>
            <div class="absolute inset-e-0 top-0 w-[72%] rotate-6 overflow-hidden rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-black/5 transition duration-300 group-hover:rotate-3 dark:bg-zinc-800 dark:ring-white/10">
                <img class="aspect-square w-full rounded-lg object-cover" src="{{ asset('images/skills/product-detail.webp') }}?v={{ filemtime(public_path('images/skills/product-detail.webp')) }}" width="1254" height="1254" alt="">
            </div>
        </div>
    </button>

    <button type="button" wire:click="openTool('marketing-poster')" class="group relative flex min-h-36 items-stretch gap-4 overflow-hidden rounded-2xl bg-[#d0ddf0] p-5 text-left transition duration-300 hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500 dark:bg-sky-950/40 sm:min-h-40 sm:p-6">
        <div class="relative z-10 flex min-w-0 flex-1 flex-col justify-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-full bg-white text-sky-700 ring-1 ring-sky-200/80 dark:bg-white/10 dark:text-sky-300 dark:ring-white/10">
                <x-iconsax-two-magic-star class="size-5" />
            </div>
            <div class="min-w-0">
                <h2 class="text-base font-semibold tracking-tight text-zinc-900 dark:text-white sm:text-lg">{{ __('Marketing poster') }}</h2>
                <p class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ __('Create campaign posters from a topic and brand assets.') }}</p>
            </div>
        </div>
        <div class="pointer-events-none relative h-24 w-28 shrink-0 self-center sm:h-28 sm:w-36" aria-hidden="true">
            <div class="absolute bottom-1 inset-s-0 w-[46%] -rotate-8 overflow-hidden rounded-xl bg-white p-1.5 shadow-md ring-1 ring-black/5 transition duration-300 group-hover:-rotate-12 dark:bg-zinc-800 dark:ring-white/10">
                <img class="aspect-4/3 w-full rounded-lg object-cover" src="{{ asset('images/studio-samples/coffee-combo-menu/coffee.webp') }}" width="800" height="800" alt="">
            </div>
            <div class="absolute inset-e-0 top-0 w-[58%] rotate-6 overflow-hidden rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-black/5 transition duration-300 group-hover:rotate-3 dark:bg-zinc-800 dark:ring-white/10">
                <img class="aspect-2/3 w-full rounded-lg object-cover" src="{{ asset('images/skills/marketing-poster.webp') }}?v={{ filemtime(public_path('images/skills/marketing-poster.webp')) }}" width="1024" height="1536" alt="">
            </div>
        </div>
    </button>
</div>

<section class="space-y-4" aria-labelledby="studio-examples">
    <div>
        <flux:heading id="studio-examples" size="xl">{{ __('Design examples') }}</flux:heading>
        <flux:text class="mt-1">{{ __('See how one guided workflow turns product references into a complete visual set.') }}</flux:text>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($samples as $slug => $sample)
            <a class="group overflow-hidden rounded-2xl bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-500 dark:bg-white/5" href="{{ route('studio.sample', ['sample' => $slug]) }}" wire:navigate>
                <img class="aspect-square w-full object-cover transition duration-300 group-hover:scale-[1.02]" src="{{ asset($sample['results'][0]['image']) }}" alt="{{ __($sample['title']) }}" width="800" height="800" loading="lazy">
                <div class="p-4">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">{{ __($sample['title']) }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ $sample['tool'] === 'marketing-poster' ? __('Marketing poster') : __('Product detail images') }}</p>
                    <span class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-violet-600 dark:text-violet-300">{{ __('View example') }} <flux:icon.arrow-right class="size-4 transition group-hover:translate-x-0.5" /></span>
                </div>
            </a>
        @endforeach
    </div>
</section>

@auth
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Recent projects') }}</flux:heading>
            <flux:button size="sm" variant="ghost" :href="route('studio.index', ['view' => 'projects'])" wire:navigate>{{ __('View all') }}</flux:button>
        </div>

        @if ($page->recentProjects->isEmpty())
            <div class="rounded-3xl border border-dashed border-zinc-300 p-10 text-center dark:border-white/15"><flux:text>{{ __('Your projects will appear here.') }}</flux:text></div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($page->recentProjects as $item)
                    @php($cover = $item->media->firstWhere('status', 'succeeded'))
                    <a @if ($item->submitted_at) href="{{ route('studio.index', ['view' => 'projects', 'project' => $item->id]) }}" wire:navigate @else href="#" wire:click.prevent="resumeProject({{ $item->id }})" @endif class="overflow-hidden rounded-2xl border border-zinc-200 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5" aria-label="{{ __('Edit project :name', ['name' => $item->name]) }}">
                        <div class="aspect-4/3 bg-zinc-100 dark:bg-white/10">
                            @if ($cover && $page->imageUrl($cover, 'sm'))
                                <img class="size-full object-cover" src="{{ $page->imageUrl($cover, 'sm') }}" alt="{{ $item->name }}" loading="lazy">
                            @else
                                <div class="flex size-full items-center justify-center"><x-iconsax-two-magic-star class="size-7 text-zinc-400" /></div>
                            @endif
                        </div>
                        <div class="p-3"><div class="truncate text-sm font-medium">{{ $item->name }}</div><div class="mt-1 text-xs text-zinc-500">{{ $page->projectProgress($item) }}</div></div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endauth
