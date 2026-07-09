<?php

use App\Models\AiImage;
use App\Models\AiImageFavorite;
use App\Models\Category;
use App\Services\AiImageEditor;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create image')] class extends Component {
	public ?Category $category = null;

	public string $search = '';

	public string $sort = 'featured';

	public int $perPage = 36;

	public function mount(?Category $category = null, ?AiImage $image = null): void
	{
		$search = request()->query('search');
		$sort = request()->query('sort');

		$this->category = $category;
		$this->search = is_string($search) ? trim($search) : '';
		$this->sort = is_string($sort) && in_array($sort, ['featured', 'new', 'popular'], true) ? $sort : 'featured';

		if ($image) {
			abort_unless($this->isPublicImage($image), 404);
		}
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
		$this->redirectRoute('images.show', ['image' => $id], navigate: true);
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

		$this->dispatch('use-prompt', prompt: $image->prompt, imageId: $image->id);
	}

	#[Computed]
	public function images()
	{
		return app(AiImageEditor::class)->publishedGallery($this->category, $this->perPage + 1, $this->search, $this->sort);
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
			->where('is_published', true)
			->where('status', 'succeeded')
			->whereNotNull('result_path')
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
	public function imageUrl(AiImage $image): ?string
	{
		return app(AiImageEditor::class)->resultUrl($image);
	}

	public function detailUrl(AiImage $image): string
	{
		return route('images.show', $image);
	}

	public function creatorName(AiImage $image): string
	{
		return $image->user?->name ?: __('Guest');
	}

	private function isPublicImage(AiImage $image): bool
	{
		return $image->is_published && $image->status === 'succeeded' && filled($image->result_path);
	}

	private function publishedImage(int $id): ?AiImage
	{
		return AiImage::query()
			->where('is_published', true)
			->where('status', 'succeeded')
			->whereNotNull('result_path')
			->whereKey($id)
			->first();
	}
}; ?>

<section class="min-h-full text-zinc-950 dark:text-white">
	<div class="">
		<main class="min-w-0">
			@if($category?->name || $search)
				<div class="mb-5 flex flex-wrap items-end justify-between gap-3 pr-28">
					<div>
						<h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $category?->name ?? 'AI Gallery' }}</h1>
						<p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Published community images. Click an image to view details.') }}</p>
					</div>
				</div>
			@endif

			@if ($this->visibleImages()->isEmpty())
				<div class="rounded-4xl flex min-h-[55svh] items-center justify-center border border-dashed border-zinc-300 bg-white text-center dark:border-white/10 dark:bg-white/5">
					<div class="max-w-sm p-8">
						<div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-white/10">
							<flux:icon class="size-7 text-zinc-500" name="photo" />
						</div>
						<h2 class="text-lg font-semibold">{{ trim($search) === '' ? __('No published images yet') : __('No images found') }}</h2>
						<p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
							{{ trim($search) === '' ? __('Create and publish an image to make it appear here.') : __('Try another prompt or category.') }}
						</p>
					</div>
				</div>
			@else
			<x-media-list :images="$this->visibleImages()">
				@foreach ($this->visibleImages() as $image)
				@php($url = $this->imageUrl($image))
				@if ($url)
					<x-media-item :image="$image" :url="$url" :detail-url="$this->detailUrl($image)" :creator="$this->creatorName($image)" wire:key="published-image-{{ $image->id }}">
						<x-slot:badge>
							<flux:button class="shadow" type="button" size="sm" :variant="$this->isFavorite($image) ? 'primary' : 'filled'" icon="heart" wire:click.stop="toggleFavorite({{ $image->id }})" aria-label="{{ $this->isFavorite($image) ? __('Remove favorite') : __('Favorite image') }}">{{ $this->favoriteCount($image) }}</flux:button>
						</x-slot:badge>
						<x-slot:actions>
							<flux:button type="button" size="sm" variant="filled" wire:click.stop="useAsPrompt({{ $image->id }})">{{ __('Create similar image') }}</flux:button>
						</x-slot:actions>
					</x-media-item>
				@endif
				@endforeach
			</x-media-list>

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