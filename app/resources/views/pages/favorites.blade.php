<?php

use App\Models\AiImage;
use App\Models\AiImageFavorite;
use App\Services\AiImageEditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ảnh yêu thích')] class extends Component
{
    #[Computed]
    public function images()
    {
        return AiImage::query()
            ->select('ai_images.*')
            ->with(['category', 'user'])
            ->join('ai_image_favorites', 'ai_image_favorites.ai_image_id', '=', 'ai_images.id')
            ->where('ai_image_favorites.user_id', (int) Auth::id())
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->latest('ai_image_favorites.created_at')
            ->limit(120)
            ->get();
    }

    public function removeFavorite(int $id): void
    {
        AiImageFavorite::query()
            ->where('user_id', (int) Auth::id())
            ->where('ai_image_id', $id)
            ->first()
            ?->delete();

        unset($this->images);
    }

    public function useAsPrompt(int $id): void
    {
        $image = AiImage::query()
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->whereKey($id)
            ->first();

        if (! $image) {
            return;
        }

        $this->dispatch('use-prompt', prompt: $image->prompt);
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
}; ?>

<section class="min-h-full text-zinc-950 dark:text-white">
	<div class="">
		<main class="min-w-0">
			<div class="mb-5 flex flex-wrap items-end justify-between gap-3 pr-28">
				<div>
					<h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Ảnh yêu thích') }}</h1>
					<p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Images you favorited in the gallery.') }}</p>
				</div>
			</div>

			@if ($this->images->isEmpty())
				<div class="rounded-4xl flex min-h-[55svh] items-center justify-center border border-dashed border-zinc-300 bg-white text-center dark:border-white/10 dark:bg-white/5">
					<div class="max-w-sm p-8">
						<div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-white/10">
							<flux:icon class="size-7 text-zinc-500" name="heart" />
						</div>
						<h2 class="text-lg font-semibold">{{ __('Chưa có ảnh yêu thích') }}</h2>
						<p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Bấm trái tim trong gallery để lưu ảnh vào đây.') }}</p>
					</div>
				</div>
			@else
				<x-media-list :images="$this->images">
					@foreach ($this->images as $image)
						@php($url = $this->imageUrl($image))
						@if ($url)
							<x-media-item :image="$image" :url="$url" :detail-url="$this->detailUrl($image)" :creator="$this->creatorName($image)" wire:key="favorite-image-{{ $image->id }}">
								<x-slot:badge>
									<flux:button class="shadow" type="button" size="sm" variant="primary" icon="heart" wire:click.stop="removeFavorite({{ $image->id }})" aria-label="{{ __('Remove favorite') }}">{{ (int) ($image->favorites_count ?? 0) }}</flux:button>
								</x-slot:badge>
								<x-slot:actions>
									<flux:button type="button" size="sm" variant="filled" wire:click.stop="useAsPrompt({{ $image->id }})">{{ __('Create similar image') }}</flux:button>
								</x-slot:actions>
							</x-media-item>
						@endif
					@endforeach
				</x-media-list>
			@endif
		</main>
	</div>

</section>
