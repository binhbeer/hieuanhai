@props(['page', 'selectedProject'])

@guest
    <div class="rounded-3xl border border-dashed border-zinc-300 p-12 text-center dark:border-white/15">
        <flux:heading>{{ __('Log in to view your projects') }}</flux:heading>
        <flux:button class="mt-4" variant="primary" color="violet" type="button" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Log in') }}</flux:button>
    </div>
@else
    @if ($selectedProject && $selectedProject->submitted_at)
        <div class="space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <flux:button
                        class="shrink-0"
                        :href="route('studio.index', ['view' => 'projects'])"
                        variant="ghost"
                        wire:navigate
                        :aria-label="__('Back to projects')"
                    >
                        <x-slot name="icon"><x-iconsax-two-arrow-left class="size-5" /></x-slot>
                    </flux:button>
                    <div class="min-w-0">
                        <flux:heading size="xl">{{ $selectedProject->name }}</flux:heading>
                        <flux:text class="mt-1">
                            {{ $page->toolLabel($selectedProject->tool) }}
                            · {{ $page->projectProgress($selectedProject) }}
                            · {{ __(':count versions', ['count' => $page->latestVersion($selectedProject)]) }}
                        </flux:text>
                    </div>
                </div>
                <flux:button type="button" variant="primary" color="violet" wire:click="resumeProject({{ $selectedProject->id }})">
                    <x-slot name="icon"><x-iconsax-two-add class="size-5" /></x-slot>
                    {{ __('Create new version') }}
                </flux:button>
            </div>
            @php
                $mediaByVersion = $selectedProject->media
                    ->groupBy(fn ($image) => $page->mediaVersion($image))
                    ->sortKeysDesc();
            @endphp
            @if ($selectedProject->media->isEmpty())
                <div class="rounded-3xl border border-dashed border-zinc-300 p-12 text-center dark:border-white/15">
                    <flux:heading>{{ __('No images yet') }}</flux:heading>
                </div>
            @else
                <div class="space-y-8">
                    @foreach ($mediaByVersion as $version => $images)
                        @php($versionCreatedAt = $images->sortBy('created_at')->first()?->created_at)
                        <section class="space-y-3">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <flux:heading size="lg">{{ __('Version :version', ['version' => $version]) }}</flux:heading>
                                <flux:text>
                                    {{ $page->toolLabel($selectedProject->tool) }}
                                    · {{ $versionCreatedAt?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    · {{ __(':count images', ['count' => $images->count()]) }}
                                </flux:text>
                            </div>
                            <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                                @foreach ($images as $image)
                                    <article class="overflow-hidden rounded-3xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-white/5">
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
                            </div>
                        </section>
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($page->projects->isEmpty())
        <div class="rounded-3xl border border-dashed border-zinc-300 p-12 text-center dark:border-white/15"><flux:heading>{{ __('No projects yet') }}</flux:heading><flux:text class="mt-2">{{ __('Start with an AI tool and your draft will be saved automatically.') }}</flux:text></div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($page->projects as $item)
                @php($cover = $item->media->firstWhere('status', 'succeeded'))
                <article class="overflow-hidden rounded-3xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-white/5">
                    <a @if ($item->submitted_at) href="{{ route('studio.index', ['view' => 'projects', 'project' => $item->id]) }}" wire:navigate @else href="#" wire:click.prevent="resumeProject({{ $item->id }})" @endif class="block w-full text-left" aria-label="{{ __('Edit project :name', ['name' => $item->name]) }}">
                        <div class="aspect-[16/9] bg-zinc-100 dark:bg-white/10">
                            @if ($cover && $page->imageUrl($cover, 'sm'))
                                <img class="size-full object-cover" src="{{ $page->imageUrl($cover, 'sm') }}" alt="{{ $item->name }}" loading="lazy">
                            @else
                                <div class="flex size-full items-center justify-center"><x-iconsax-two-magic-star class="size-8 text-zinc-400" /></div>
                            @endif
                        </div>
                        <div class="p-4">
                            <h2 class="font-semibold">{{ $item->name }}</h2>
                            <p class="mt-1 text-sm text-zinc-500">
                                {{ $page->toolLabel($item->tool) }}
                                · {{ $page->projectProgress($item) }}
                                @if ($item->created_at)
                                    · {{ $item->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        </div>
                    </a>
                    @if (! $item->submitted_at)
                        <div class="border-t border-zinc-200 p-3 text-right dark:border-white/10"><flux:button size="sm" variant="ghost" type="button" wire:click="deleteDraft({{ $item->id }})" wire:confirm="{{ __('Delete this draft?') }}">{{ __('Delete') }}</flux:button></div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endguest
