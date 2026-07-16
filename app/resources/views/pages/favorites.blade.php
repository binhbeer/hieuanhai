<?php

use App\Models\GeneratedMedia;
use App\Models\MediaFavorite;
use App\Services\AiImageEditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Favorite images')] class extends Component
{
    #[Computed]
    public function images()
    {
        return GeneratedMedia::query()
            ->select('generated_media.*')
            ->with(['category', 'user'])
            ->join('media_favorites', 'media_favorites.media_id', '=', 'generated_media.id')
            ->where('media_favorites.user_id', (int) Auth::id())
            ->publiclyVisible()
            ->latest('media_favorites.created_at')
            ->limit(120)
            ->get();
    }

    public function removeFavorite(int $id): void
    {
        MediaFavorite::query()
            ->where('user_id', (int) Auth::id())
            ->where('media_id', $id)
            ->first()
            ?->delete();

        unset($this->images);
    }

    public function useAsPrompt(int $id): void
    {
        $image = GeneratedMedia::query()
            ->publiclyVisible()
            ->whereKey($id)
            ->first();

        if (! $image) {
            return;
        }

        $this->dispatch('use-prompt', prompt: $image->prompt);
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
}; ?>

<section class="min-h-full text-zinc-950 dark:text-white">
	<div class="">
		<main class="min-w-0">
			<div class="mb-5 flex flex-wrap items-end justify-between gap-3 pr-28">
				<div>
					<h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Favorite images') }}</h1>
					<p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Images you favorited in the gallery.') }}</p>
				</div>
			</div>

			@if ($this->images->isEmpty())
				<div class="rounded-4xl flex min-h-[55svh] items-center justify-center border border-dashed border-zinc-300 bg-white text-center dark:border-white/10 dark:bg-white/5">
					<div class="max-w-sm p-8">
						<div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-white/10">
							<x-iconsax-two-heart class="size-7 text-zinc-500" />
						</div>
						<h2 class="text-lg font-semibold">{{ __('No favorite images yet') }}</h2>
						<p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Click the heart in the gallery to save images here.') }}</p>
					</div>
				</div>
			@else
				<x-gallery.list :images="$this->images">
					@foreach ($this->images as $image)
						@php($url = $this->imageUrl($image, 'sm'))
						@php($imageSize = $this->imageSize($image, 'sm'))
						@if ($url)
							<x-gallery.item :image="$image" :url="$url" :image-size="$imageSize" :detail-url="$this->detailUrl($image)" :creator="$this->creatorName($image)" :loading="$loop->iteration <= 5 ? 'eager' : 'lazy'" wire:key="favorite-image-{{ $image->id }}">
								@if (($image->favorites_count ?? 0) > 0)
									<x-slot:badge>
										<flux:badge class="shadow" size="sm" variant="solid" color="primary" rounded icon="heart">
											{{ (int) $image->favorites_count }}
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
			@endif
		</main>
	</div>

</section>
