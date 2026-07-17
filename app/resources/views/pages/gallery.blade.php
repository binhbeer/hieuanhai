<?php

use App\Models\GeneratedMedia;
use App\Models\MediaFavorite;
use App\Models\Tag;
use App\Models\Category;
use App\Services\AiImageEditor;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('AI Gallery')] class extends Component {
	public ?Category $category = null;

	public ?Tag $tag = null;

	public string $search = '';

	public string $sort = 'new';

	public int $perPage = 36;

	public function mount(?Category $category = null, ?Tag $tag = null): void
	{
		$search = \App\Support\LocalizedRoute::is('search.*') ? request()->query('q') : null;
		$sort = request()->query('sort');
		$tagSlug = $category ? request()->query('tag') : null;

		$this->category = $category;
		$this->tag = $tag ?? (is_string($tagSlug) ? Tag::query()->where(app()->getLocale() === 'en' ? 'slug_en' : 'slug', $tagSlug)->first() : null);
		$this->search = is_string($search) ? trim($search) : '';
		$this->sort = is_string($sort) && in_array($sort, ['featured', 'new', 'popular'], true) ? $sort : 'new';
	}

	#[On('gallery-updated')]
	public function refreshGallery(): void
	{
		unset($this->images, $this->categories, $this->favoriteIds);
	}

	public function loadMore(): void
	{
		$this->perPage += 36;

		unset($this->images);
	}

	public function clearTag(): void
	{
		if ($this->category) {
			$this->redirectRoute('categories.show', ['category' => $this->category], navigate: true);
		}
	}

	public function selectImage(int $id): void
	{
		$image = $this->publishedImage($id);

		if ($image) {
			$this->redirectRoute('images.show', ['image' => $image], navigate: true);
		}
	}

	public function useAsPrompt(int $id): void
	{
		if (!Auth::check()) {
			$this->dispatch('open-account-modal', component: 'auth.login');

			return;
		}

		$image = $this->publishedImage($id);

		if (!$image) {
			return;
		}

		$this->dispatch('use-prompt', prompt: $image->prompt);
	}

	#[Computed]
	public function images()
	{
		return app(AiImageEditor::class)->publishedGallery(category: $this->category, limit: $this->perPage + 1, search: $this->search, sort: $this->sort, tag: $this->tag);
	}

	public function visibleImages()
	{
		return $this->images->take($this->perPage);
	}

	public function hasMoreImages(): bool
	{
		return $this->images->count() > $this->perPage;
	}

	#[Computed]
	public function favoriteIds(): array
	{
		if (!Auth::check()) {
			return [];
		}

		return MediaFavorite::query()
			->where('user_id', (int) Auth::id())
			->pluck('media_id')
			->all();
	}

	public function isFavorite(GeneratedMedia $image): bool
	{
		return in_array($image->id, $this->favoriteIds, true);
	}

	public function favoriteCount(GeneratedMedia $image): int
	{
		return (int) ($image->favorites_count ?? 0);
	}

	public function toggleFavorite(int $id): void
	{
		if (!Auth::check()) {
			$this->dispatch('open-account-modal', component: 'auth.login');

			return;
		}

		$image = GeneratedMedia::query()
			->publiclyVisible()
			->find($id);

		if (!$image) {
			return;
		}

		$userId = (int) Auth::id();
		$favorite = MediaFavorite::query()->where('user_id', $userId)->where('media_id', $image->id)->first();

		$wasFavorite = $favorite !== null;

		$wasFavorite
			? $favorite->delete()
			: MediaFavorite::query()->create(['user_id' => $userId, 'media_id' => $image->id]);

		unset($this->images, $this->favoriteIds);

		Flux::toast(variant: 'success', text: $wasFavorite ? __('Remove favorite') : __('Favorite image'));
	}

	public function imageUrl(GeneratedMedia $image, string $size = 'original'): ?string
	{
		return app(AiImageEditor::class)->imageUrl($image, $size);
	}

	public function imageSize(GeneratedMedia $image, string $size = 'original'): ?array
	{
		return app(AiImageEditor::class)->imageSize($image, $size);
	}

	public function detailUrl(GeneratedMedia $image): string
	{
		return route('images.show', $image);
	}

	public function creatorName(GeneratedMedia $image): string
	{
		return $image->user?->name ?: __('Guest');
	}

	private function publishedImage(int $id): ?GeneratedMedia
	{
		return GeneratedMedia::query()
			->publiclyVisible()
			->when(app()->getLocale() === 'en', fn ($query) => $query->englishReady())
			->whereKey($id)
			->first();
	}
}; ?>

<section class="min-h-full text-zinc-950 dark:text-white">
	<div class="">
		<main class="min-w-0">
			@if($category?->name || $tag?->name || $search)
				<div class="mb-2 flex flex-wrap items-end justify-between gap-3 pl-2">
					<div>
						<h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ \App\Support\LocalizedRoute::is('search.*') ? __('Search results') : ($category?->name ?? ($tag?->name ? '#' . $tag->name : 'AI Gallery')) }}</h1>
						@if ($category && $tag)
							<flux:badge class="mt-2" rounded>#{{ $tag->name }} <flux:badge.close wire:click="clearTag" :aria-label="__('Clear tag filter')" /></flux:badge>
						@endif
					</div>
					@if (filled($category?->description ?? $tag?->description))
						<p class="mt-2 max-w-3xl text-sm text-zinc-500 dark:text-zinc-400">{{ $category?->description ?? $tag?->description }}</p>
					@endif
				</div>
			@endif

			@if (\App\Support\LocalizedRoute::is('home'))
				<div class="mb-5 grid gap-2 sm:grid-cols-3">
					<button type="button" class="group relative flex min-h-24 items-center gap-3 overflow-hidden rounded-2xl border border-emerald-900/5 bg-emerald-50 p-3 text-left transition hover:bg-emerald-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 md:min-h-32 md:p-5 lg:min-h-36 lg:p-6 dark:border-emerald-300/10 dark:bg-emerald-950/45 dark:hover:bg-emerald-950/65" x-data="{ active: 0, items: @js([__('Describe your idea and let AI create the image.'), __('Create avatars, illustrations, or social media images.'), __('Make product photos and marketing visuals.'), __('Use your own photo as a visual reference.')]), timer: null, mobile: window.matchMedia('(max-width: 639px)').matches, init() { if (this.mobile) this.start() }, start() { if (this.timer !== null || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return; this.active = (this.active + 1) % this.items.length; this.timer = setInterval(() => this.active = (this.active + 1) % this.items.length, 3200) }, stop() { clearInterval(this.timer); this.timer = null; this.active = 0 }, destroy() { clearInterval(this.timer) } }" x-on:mouseenter="start()" x-on:mouseleave="if (!mobile) stop()" x-on:focusin="start()" x-on:focusout="if (!mobile) stop()" x-on:click="$dispatch('open-image-composer')">
						<x-iconsax-two-magic-star class="absolute -bottom-6 -left-5 size-28 rotate-12 text-emerald-600/5 transition group-hover:rotate-6 md:size-36 dark:text-emerald-300/5" aria-hidden="true" />
						<span class="relative z-10 min-w-0 flex-1">
							<span class="block text-sm font-semibold text-zinc-900 md:text-base lg:text-lg dark:text-white">{{ __('Create AI images') }}</span>
							<span class="relative mt-1 block h-10 overflow-hidden text-xs leading-relaxed text-zinc-600 md:h-11 md:text-sm lg:h-12 lg:text-base dark:text-zinc-300" aria-live="off">
								<template x-for="(item, index) in items" x-bind:key="item">
									<span class="absolute inset-0" x-show="active === index" x-transition:enter="transition duration-500" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition duration-500" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="-translate-y-full opacity-0" x-text="item"></span>
								</template>
							</span>
						</span>
						<span class="relative z-10 h-16 w-16 shrink-0 md:h-20 md:w-20 lg:h-24 lg:w-24" aria-hidden="true">
							<span class="absolute bottom-1 right-0 flex size-11 rotate-6 items-center justify-center rounded-xl border border-white/70 bg-white/80 text-emerald-600 shadow-sm md:size-14 lg:size-16 dark:border-white/10 dark:bg-white/10 dark:text-emerald-300"><x-iconsax-two-gallery class="size-5 md:size-6 lg:size-7" /></span>
							<span class="absolute bottom-0 left-0 flex size-9 -rotate-6 items-center justify-center rounded-lg border border-white/70 bg-white text-emerald-700 shadow-sm md:size-11 lg:size-12 dark:border-white/10 dark:bg-zinc-800 dark:text-emerald-200"><x-iconsax-two-magic-star class="size-4 md:size-5" /></span>
						</span>
					</button>

					<a href="{{ route('skills.index') }}" wire:navigate class="group relative flex min-h-24 items-center gap-3 overflow-hidden rounded-2xl border border-blue-900/5 bg-blue-50 p-3 text-left transition hover:bg-blue-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 md:min-h-32 md:p-5 lg:min-h-36 lg:p-6 dark:border-blue-300/10 dark:bg-blue-950/45 dark:hover:bg-blue-950/65" x-data="{ active: 0, items: @js([__('Create a complete set of product images.'), __('Design marketing posters in a few steps.'), __('Keep your brand style consistent across images.'), __('Save projects and create new versions anytime.')]), timer: null, mobile: window.matchMedia('(max-width: 639px)').matches, init() { if (this.mobile) this.start() }, start() { if (this.timer !== null || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return; this.active = (this.active + 1) % this.items.length; this.timer = setInterval(() => this.active = (this.active + 1) % this.items.length, 3200) }, stop() { clearInterval(this.timer); this.timer = null; this.active = 0 }, destroy() { clearInterval(this.timer) } }" x-on:mouseenter="start()" x-on:mouseleave="if (!mobile) stop()" x-on:focusin="start()" x-on:focusout="if (!mobile) stop()">
						<x-iconsax-bul-star class="absolute -bottom-6 -left-5 size-28 -rotate-12 text-blue-600/5 transition group-hover:-rotate-6 md:size-36 dark:text-blue-300/5" aria-hidden="true" />
						<span class="relative z-10 min-w-0 flex-1">
							<span class="block text-sm font-semibold text-zinc-900 md:text-base lg:text-lg dark:text-white">{{ __('AI Studio') }}</span>
							<span class="relative mt-1 block h-10 overflow-hidden text-xs leading-relaxed text-zinc-600 md:h-11 md:text-sm lg:h-12 lg:text-base dark:text-zinc-300" aria-live="off">
								<template x-for="(item, index) in items" x-bind:key="item">
									<span class="absolute inset-0" x-show="active === index" x-transition:enter="transition duration-500" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition duration-500" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="-translate-y-full opacity-0" x-text="item"></span>
								</template>
							</span>
						</span>
						<span class="relative z-10 h-16 w-16 shrink-0 md:h-20 md:w-20 lg:h-24 lg:w-24" aria-hidden="true">
							<span class="absolute bottom-1 right-0 flex size-11 rotate-6 items-center justify-center rounded-xl border border-white/70 bg-white/80 text-blue-600 shadow-sm md:size-14 lg:size-16 dark:border-white/10 dark:bg-white/10 dark:text-blue-300"><x-iconsax-two-magic-star class="size-5 md:size-6 lg:size-7" /></span>
							<span class="absolute bottom-0 left-0 flex size-9 -rotate-6 items-center justify-center rounded-lg border border-white/70 bg-white text-blue-700 shadow-sm md:size-11 lg:size-12 dark:border-white/10 dark:bg-zinc-800 dark:text-blue-200"><x-iconsax-two-gallery class="size-4 md:size-5" /></span>
						</span>
					</a>

					<a href="#community-gallery" class="group relative hidden min-h-24 items-center gap-3 overflow-hidden sm:flex rounded-2xl border border-amber-900/5 bg-amber-50 p-3 text-left transition hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 md:min-h-32 md:p-5 lg:min-h-36 lg:p-6 dark:border-amber-300/10 dark:bg-amber-950/45 dark:hover:bg-amber-950/65" x-data="{ active: 0, items: @js([__('Browse ideas from community-created images.'), __('Find visual styles for your next project.'), __('Reuse an image idea to create your own version.'), __('Save your favorite images for later.')]), timer: null, start() { if (this.timer !== null || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return; this.active = (this.active + 1) % this.items.length; this.timer = setInterval(() => this.active = (this.active + 1) % this.items.length, 3200) }, stop() { clearInterval(this.timer); this.timer = null; this.active = 0 }, destroy() { clearInterval(this.timer) } }" x-on:mouseenter="start()" x-on:mouseleave="stop()" x-on:focusin="start()" x-on:focusout="stop()">
						<x-iconsax-two-gallery class="absolute -bottom-6 -left-5 size-28 rotate-12 text-amber-600/5 transition group-hover:rotate-6 md:size-36 dark:text-amber-300/5" aria-hidden="true" />
						<span class="relative z-10 min-w-0 flex-1">
							<span class="block text-sm font-semibold text-zinc-900 md:text-base lg:text-lg dark:text-white">{{ __('Community gallery') }}</span>
							<span class="relative mt-1 block h-10 overflow-hidden text-xs leading-relaxed text-zinc-600 md:h-11 md:text-sm lg:h-12 lg:text-base dark:text-zinc-300" aria-live="off">
								<template x-for="(item, index) in items" x-bind:key="item">
									<span class="absolute inset-0" x-show="active === index" x-transition:enter="transition duration-500" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition duration-500" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="-translate-y-full opacity-0" x-text="item"></span>
								</template>
							</span>
						</span>
						<span class="relative z-10 h-16 w-16 shrink-0 md:h-20 md:w-20 lg:h-24 lg:w-24" aria-hidden="true">
							<span class="absolute bottom-1 right-0 flex size-11 rotate-6 items-center justify-center rounded-xl border border-white/70 bg-white/80 text-amber-600 shadow-sm md:size-14 lg:size-16 dark:border-white/10 dark:bg-white/10 dark:text-amber-300"><x-iconsax-two-gallery class="size-5 md:size-6 lg:size-7" /></span>
							<span class="absolute bottom-0 left-0 flex size-9 -rotate-6 items-center justify-center rounded-lg border border-white/70 bg-white text-amber-700 shadow-sm md:size-11 lg:size-12 dark:border-white/10 dark:bg-zinc-800 dark:text-amber-200"><x-iconsax-two-heart class="size-4 md:size-5" /></span>
						</span>
					</a>
				</div>
			@endif

			<livewire:gallery.tags mode="popular" :category="$category" :key="'gallery-tags-'.($category?->id ?? 'all')" />

			<div id="community-gallery" class="scroll-mt-20">
			@if ($this->visibleImages()->isEmpty())
				<div class="rounded-4xl flex min-h-[55svh] items-center justify-center border border-dashed border-zinc-300 bg-white text-center dark:border-white/10 dark:bg-white/5">
					<div class="max-w-sm p-8">
						<div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-white/10">
							<x-iconsax-two-gallery class="size-7 text-zinc-500" />
						</div>
						<h2 class="text-lg font-semibold">{{ trim($search) === '' ? __('No published images yet') : __('No images found') }}</h2>
						<p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
							{{ trim($search) === '' ? __('Create and publish an image to make it appear here.') : __('Try another prompt or category.') }}
						</p>
					</div>
				</div>
			@else
			<x-gallery.list :images="$this->visibleImages()">
				@foreach ($this->visibleImages() as $image)
				@php($thumbUrl = $this->imageUrl($image, 'sm'))
				@php($imageSize = $this->imageSize($image, 'sm'))
				@if ($thumbUrl)
					<x-gallery.item :image="$image" :url="$thumbUrl" :image-size="$imageSize" :detail-url="$this->detailUrl($image)" :creator="$this->creatorName($image)" :loading="$loop->iteration <= 5 ? 'eager' : 'lazy'" wire:key="published-image-{{ $image->id }}">
						@if ($this->favoriteCount($image) > 0)
							<x-slot:badge>
								<flux:badge class="shadow" size="sm" variant="solid" color="primary" rounded icon="heart">
									{{ $this->favoriteCount($image) }}
								</flux:badge>
							</x-slot:badge>
						@endif
						<x-slot:actions>
							<flux:button type="button" size="sm" variant="filled" wire:click.stop="useAsPrompt({{ $image->id }})">{{ __('Create similar image') }}</flux:button>
						</x-slot:actions>
					</x-gallery.item>
				@endif
				@endforeach
			</x-gallery.list>

			@if ($this->hasMoreImages())
				<div class="flex justify-center py-8" wire:intersect.margin.600px="loadMore">
					<div class="flex items-center gap-3 rounded-full border border-zinc-200 bg-white/80 px-4 py-2 text-sm text-zinc-500 shadow-sm backdrop-blur dark:border-white/10 dark:bg-zinc-900/80 dark:text-zinc-400" role="status" aria-live="polite">
						<div class="size-4 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-900 dark:border-white/20 dark:border-t-white"></div>
						<span wire:loading.remove wire:target="loadMore">{{ __('Load more images') }}</span>
						<span wire:loading wire:target="loadMore">{{ __('Loading more images...') }}</span>
					</div>
				</div>
			@endif
			@endif
			</div>
		</main>
	</div>
</section>