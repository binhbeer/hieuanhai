<?php

use App\Models\GeneratedMedia;
use App\Models\MediaFavorite;
use App\Models\Tag;
use App\Models\Category;
use App\Services\GeneratedMediaService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('AI Gallery')] class extends Component {
	public ?Category $category = null;

	public ?Tag $tag = null;

	#[Url(as: 'q', except: '')]
	public string $search = '';

	#[Url(except: 'new')]
	public string $sort = 'new';

	public int $perPage = 36;

	public function mount(?Category $category = null, ?Tag $tag = null): void
	{
		$search = request()->query('q');
		$sort = request()->query('sort');
		$tagSlug = $category ? request()->query('tag') : null;

		$this->category = $category;
		$this->tag = $tag ?? (is_string($tagSlug) ? Tag::query()->where(app()->getLocale() === 'en' ? 'slug_en' : 'slug', $tagSlug)->first() : null);
		$this->search = is_string($search) ? trim($search) : '';
		$this->sort = is_string($sort) && in_array($sort, ['featured', 'new', 'popular'], true) ? $sort : 'new';
	}

	public function updatedSearch(): void
	{
		$this->perPage = 36;
		unset($this->images);
	}

	public function updatedSort(): void
	{
		$this->perPage = 36;
		unset($this->images);
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
		return app(GeneratedMediaService::class)->publishedGallery(category: $this->category, limit: $this->perPage + 1, search: $this->search, sort: $this->sort, tag: $this->tag);
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
		return app(GeneratedMediaService::class)->imageUrl($image, $size);
	}

	public function imageSize(GeneratedMedia $image, string $size = 'original'): ?array
	{
		return app(GeneratedMediaService::class)->imageSize($image, $size);
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
			<div class="mb-4 flex flex-wrap items-end justify-between gap-3 pl-2">
				<div class="min-w-0 flex-1">
					<h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $category?->name ?? ($tag?->name ? '#' . $tag->name : ($search !== '' ? __('Search results') : __('AI Gallery'))) }}</h1>
					@if (! $category && ! $tag && $search === '')
						<p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Browse published images from the GenAnh community.') }}</p>
					@elseif ($search !== '')
						<p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Results for “:query”', ['query' => $search]) }}</p>
					@endif
					@if ($category && $tag)
						<flux:badge class="mt-2" rounded>#{{ $tag->name }} <flux:badge.close wire:click="clearTag" :aria-label="__('Clear tag filter')" /></flux:badge>
					@endif
					@if (filled($category?->description ?? $tag?->description))
						<p class="mt-2 max-w-3xl text-sm text-zinc-500 dark:text-zinc-400">{{ $category?->description ?? $tag?->description }}</p>
					@endif
				</div>
				<div class="flex w-full items-center gap-2 sm:w-auto sm:shrink-0">
					<form class="flex min-w-0 flex-1 items-center gap-2 sm:flex-none lg:hidden" action="{{ route('gallery.index') }}" method="GET" x-data="{ query: @js($search) }">
						<div class="min-w-0 flex-1 sm:w-72 sm:flex-none">
							<flux:input
								x-model="query"
								name="q"
								type="search"
								size="sm"
								:placeholder="__('Search images...')"
								class="[&_input::-webkit-search-cancel-button]:appearance-none"
								:aria-label="__('Search images')"
								clearable
							>
								<x-slot name="icon"><x-iconsax-two-search-normal class="size-4" /></x-slot>
							</flux:input>
						</div>
						<flux:button x-show="query.trim()" x-cloak type="submit" size="sm" variant="primary" square :aria-label="__('Search')"><x-iconsax-two-search-normal class="size-4" /></flux:button>
					</form>
					<flux:tabs class="shrink-0" variant="segmented" size="sm">
						<flux:tab wire:click="$set('sort', 'new')" :selected="$sort === 'new'">{{ __('New') }}</flux:tab>
						<flux:tab wire:click="$set('sort', 'featured')" :selected="$sort === 'featured'">{{ __('Featured') }}</flux:tab>
					</flux:tabs>
				</div>
			</div>


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
					<x-gallery.item id="image-{{ $image->id }}" class="scroll-mt-20" :image="$image" :url="$thumbUrl" :image-size="$imageSize" :detail-url="$this->detailUrl($image)" :creator="$this->creatorName($image)" :loading="$loop->iteration <= 5 ? 'eager' : 'lazy'" wire:key="published-image-{{ $image->id }}">
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