@props(['page', 'selectedProject'])

@guest
    <div class="rounded-3xl border border-dashed border-zinc-300 p-12 text-center dark:border-white/15">
        <flux:heading>{{ __('Log in to view your projects') }}</flux:heading>
        <flux:button class="mt-4" variant="primary" type="button" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Log in') }}</flux:button>
    </div>
@else
    @if ($selectedProject && $selectedProject->submitted_at)
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><flux:heading size="xl">{{ $selectedProject->name }}</flux:heading><flux:text class="mt-1">{{ $page->projectProgress($selectedProject) }} · {{ __(':count versions', ['count' => $page->latestVersion($selectedProject)]) }}</flux:text></div>
                <div class="flex gap-2">
                    <flux:button type="button" variant="primary" wire:click="resumeProject({{ $selectedProject->id }})">{{ __('Create new version') }}</flux:button>
                    <flux:button :href="route('skills.index', ['view' => 'projects'])" wire:navigate>{{ __('Back to projects') }}</flux:button>
                </div>
            </div>
            <x-gallery.list :images="$selectedProject->media" class="grid-cols-1! sm:grid-cols-2! lg:grid-cols-3! 2xl:grid-cols-4!">
                @foreach ($selectedProject->media as $image)
                    <article class="relative overflow-hidden rounded-3xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-white/5">
                        <div class="absolute top-3 left-3 z-10"><flux:badge variant="solid">{{ __('Version :version', ['version' => $page->mediaVersion($image)]) }}</flux:badge></div>
                        @if ($image->status === 'succeeded' && $page->imageUrl($image, 'md'))
                            <button type="button" class="block w-full bg-zinc-100 text-left dark:bg-white/10" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }}, preview: @js($page->imageUrl($image, 'md')) })" aria-label="{{ __('View image details') }}">
                                <img class="h-auto w-full" src="{{ $page->imageUrl($image, 'md') }}" alt="{{ $image->title ?: $image->prompt }}">
                            </button>
                        @elseif ($image->status === 'failed')
                            <button type="button" class="flex min-h-64 w-full items-center justify-center bg-zinc-100 p-8 text-center text-sm text-red-600 dark:bg-white/10" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }} })" aria-label="{{ __('View image details') }}">{{ $image->displayError() }}</button>
                        @else
                            <button type="button" class="flex min-h-64 w-full flex-col items-center justify-center gap-3 bg-zinc-100 dark:bg-white/10" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }} })" aria-label="{{ __('View image details') }}"><flux:icon.loading class="size-7" /><flux:text>{{ __('Creating image...') }}</flux:text></button>
                        @endif
                        <div class="p-4"><h3 class="line-clamp-2 font-medium">{{ $image->title ?: $image->prompt }}</h3></div>
                    </article>
                @endforeach
            </x-gallery.list>
        </div>
    @elseif ($page->projects->isEmpty())
        <div class="rounded-3xl border border-dashed border-zinc-300 p-12 text-center dark:border-white/15"><flux:heading>{{ __('No projects yet') }}</flux:heading><flux:text class="mt-2">{{ __('Start with an AI tool and your draft will be saved automatically.') }}</flux:text></div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($page->projects as $item)
                @php($cover = $item->media->firstWhere('status', 'succeeded'))
                <article class="overflow-hidden rounded-3xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-white/5">
                    <a @if ($item->submitted_at) href="{{ route('skills.index', ['view' => 'projects', 'project' => $item->id]) }}" wire:navigate @else href="#" wire:click.prevent="resumeProject({{ $item->id }})" @endif class="block w-full text-left" aria-label="{{ __('Edit project :name', ['name' => $item->name]) }}">
                        <div class="aspect-[16/9] bg-zinc-100 dark:bg-white/10">
                            @if ($cover && $page->imageUrl($cover, 'sm'))
                                <img class="size-full object-cover" src="{{ $page->imageUrl($cover, 'sm') }}" alt="{{ $item->name }}" loading="lazy">
                            @else
                                <div class="flex size-full items-center justify-center"><x-iconsax-two-magic-star class="size-8 text-zinc-400" /></div>
                            @endif
                        </div>
                        <div class="p-4"><h2 class="font-semibold">{{ $item->name }}</h2><p class="mt-1 text-sm text-zinc-500">{{ $page->projectProgress($item) }}</p></div>
                    </a>
                    @if (! $item->submitted_at)
                        <div class="border-t border-zinc-200 p-3 text-right dark:border-white/10"><flux:button size="sm" variant="ghost" type="button" wire:click="deleteDraft({{ $item->id }})" wire:confirm="{{ __('Delete this draft?') }}">{{ __('Delete') }}</flux:button></div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endguest
