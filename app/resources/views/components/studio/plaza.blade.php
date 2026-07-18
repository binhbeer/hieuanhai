@props(['page'])

<div class="grid gap-4 md:grid-cols-2">
    <button type="button" wire:click="openTool('product-detail')" class="group relative isolate min-h-72 overflow-hidden rounded-3xl border border-emerald-200 bg-emerald-950 text-left shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:border-emerald-400/20">
        <img class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105" src="{{ asset('images/skills/product-detail.webp') }}" width="1200" height="675" alt="" aria-hidden="true">
        <div class="absolute inset-0 bg-linear-to-t from-black/85 via-black/20 to-black/5"></div>
        <div class="relative flex min-h-72 flex-col justify-between p-6">
            <div class="flex size-11 items-center justify-center rounded-2xl border border-white/30 bg-white/90 text-emerald-700 shadow-lg backdrop-blur"><x-iconsax-two-gallery class="size-6" /></div>
            <div class="max-w-md text-white drop-shadow-sm">
                <h2 class="text-xl font-semibold">{{ __('Product detail images') }}</h2>
                <p class="mt-1 text-sm text-white/80">{{ __('Turn one product photo into a complete listing image set.') }}</p>
            </div>
        </div>
    </button>

    <button type="button" wire:click="openTool('marketing-poster')" class="group relative isolate min-h-72 overflow-hidden rounded-3xl border border-blue-200 bg-blue-950 text-left shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:border-blue-400/20">
        <img class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105" src="{{ asset('images/skills/marketing-poster.webp') }}" width="1200" height="675" alt="" aria-hidden="true">
        <div class="absolute inset-0 bg-linear-to-t from-black/85 via-black/15 to-black/5"></div>
        <div class="relative flex min-h-72 flex-col justify-between p-6">
            <div class="flex size-11 items-center justify-center rounded-2xl border border-white/30 bg-white/90 text-blue-700 shadow-lg backdrop-blur"><x-iconsax-two-magic-star class="size-6" /></div>
            <div class="max-w-md text-white drop-shadow-sm">
                <h2 class="text-xl font-semibold">{{ __('Marketing poster') }}</h2>
                <p class="mt-1 text-sm text-white/80">{{ __('Create campaign posters from a topic and brand assets.') }}</p>
            </div>
        </div>
    </button>
</div>

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
                        <div class="aspect-[4/3] bg-zinc-100 dark:bg-white/10">
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
