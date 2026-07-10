<?php

use App\Models\AiImage;
use App\Models\AiImageFavorite;
use App\Models\AiTag;
use App\Models\Category;
use App\Services\AiImageEditor;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Trang chủ')] class extends Component {
	public ?Category $category = null;

	public ?AiTag $tag = null;

	public string $search = '';

	public string $sort = 'new';

	public int $perPage = 36;

	public function mount(?Category $category = null, ?AiTag $tag = null): void
	{
		$search = request()->routeIs('search.*') ? request()->query('q') : null;
		$sort = request()->query('sort');

		$this->category = $category;
		$this->tag = $tag;
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
			$this->redirectRoute('login', navigate: true);

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

		return AiImageFavorite::query()
			->where('user_id', (int) Auth::id())
			->pluck('ai_image_id')
			->all();
	}

	public function isFavorite(AiImage $image): bool
	{
		return in_array($image->id, $this->favoriteIds, true);
	}

	public function favoriteCount(AiImage $image): int
	{
		return (int) ($image->favorites_count ?? 0);
	}

	public function toggleFavorite(int $id): void
	{
		if (!Auth::check()) {
			$this->redirectRoute('login', navigate: true);

			return;
		}

		$image = AiImage::query()
			->publiclyVisible()
			->find($id);

		if (!$image) {
			return;
		}

		$userId = (int) Auth::id();
		$favorite = AiImageFavorite::query()->where('user_id', $userId)->where('ai_image_id', $image->id)->first();

		$wasFavorite = $favorite !== null;

		$wasFavorite
			? $favorite->delete()
			: AiImageFavorite::query()->create(['user_id' => $userId, 'ai_image_id' => $image->id]);

		unset($this->images, $this->favoriteIds);

		Flux::toast(variant: 'success', text: $wasFavorite ? __('Remove favorite') : __('Favorite image'));
	}

	public function imageUrl(AiImage $image, string $size = 'original'): ?string
	{
		return app(AiImageEditor::class)->imageUrl($image, $size);
	}

	public function imageSize(AiImage $image, string $size = 'original'): ?array
	{
		return app(AiImageEditor::class)->imageSize($image, $size);
	}

	public function detailUrl(AiImage $image): string
	{
		return route('images.show', $image);
	}

	public function creatorName(AiImage $image): string
	{
		return $image->user?->name ?: __('Guest');
	}

	private function publishedImage(int $id): ?AiImage
	{
		return AiImage::query()
			->publiclyVisible()
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
						<h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ request()->routeIs('search.*') ? __('Search results') : ($category?->name ?? ($tag?->name ? '#' . $tag->name : 'AI Gallery')) }}</h1>
					</div>
				</div>
			@endif

			<livewire:gallery.tags mode="popular" :category="$category" lazy />

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
		</main>
	</div>
</section>