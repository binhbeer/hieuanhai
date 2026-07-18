<?php

use App\Models\GeneratedMedia;
use App\Services\GeneratedMediaService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component {
    private const LIMIT = 7;

    #[Computed]
    public function images(): Collection
    {
        return app(GeneratedMediaService::class)->publishedGallery(limit: self::LIMIT, sort: 'featured');
    }

    public function imageUrl(GeneratedMedia $image): ?string
    {
        return app(GeneratedMediaService::class)->imageUrl($image, 'xs');
    }

    /** @return array{width: int, height: int} */
    public function imageSize(): array
    {
        return ['width' => 320, 'height' => 320];
    }

    public function detailUrl(GeneratedMedia $image): string
    {
        return route('images.show', $image);
    }

    public function creatorName(GeneratedMedia $image): string
    {
        return $image->user?->name ?: __('Guest');
    }

    public function placeholder(): string
    {
        // Match loaded grid: 1 hero (2×2 from sm) + 6 tiles. Plain divs — flux:skeleton defaults to h-4.
        return <<<'HTML'
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-5" aria-hidden="true">
                <div class="col-span-2 aspect-square w-full animate-pulse rounded-3xl bg-zinc-200 dark:bg-white/10 sm:row-span-2"></div>
                <div class="aspect-square w-full animate-pulse rounded-2xl bg-zinc-200 dark:bg-white/10"></div>
                <div class="aspect-square w-full animate-pulse rounded-2xl bg-zinc-200 dark:bg-white/10"></div>
                <div class="aspect-square w-full animate-pulse rounded-2xl bg-zinc-200 dark:bg-white/10"></div>
                <div class="aspect-square w-full animate-pulse rounded-2xl bg-zinc-200 dark:bg-white/10"></div>
                <div class="aspect-square w-full animate-pulse rounded-2xl bg-zinc-200 dark:bg-white/10"></div>
                <div class="aspect-square w-full animate-pulse rounded-2xl bg-zinc-200 dark:bg-white/10"></div>
            </div>
            HTML;
    }
}; ?>

<div>
    @if ($this->images->isEmpty())
        <div class="flex min-h-80 flex-col items-center justify-center rounded-4xl border border-dashed border-zinc-300 bg-white/70 p-8 text-center dark:border-white/15 dark:bg-white/5">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-300/15 dark:text-amber-200">
                <x-iconsax-two-gallery class="size-7" />
            </span>
            <flux:heading class="mt-4" size="lg">{{ __('Discover images from the community') }}</flux:heading>
            <flux:text class="mt-2 max-w-sm" variant="subtle">{{ __('Published creations will appear here. Explore the Gallery for more ideas.') }}</flux:text>
            <flux:button class="mt-5" :href="route('gallery.index')" variant="filled" icon:trailing="arrow-up-right" wire:navigate>{{ __('Browse Gallery') }}</flux:button>
        </div>
    @else
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5" aria-label="{{ __('Featured community images') }}">
            @foreach ($this->images as $image)
                @php($url = $this->imageUrl($image))
                @php($imageSize = $this->imageSize())
                @if ($url)
                    <x-gallery.item
                        :image="$image"
                        :url="$url"
                        :image-size="$imageSize"
                        :detail-url="$this->detailUrl($image)"
                        :creator="$this->creatorName($image)"
                        loading="lazy"
                        class="mb-0! aspect-square rounded-2xl! shadow-none first:col-span-2 first:rounded-3xl! sm:first:row-span-2 [&_a]:h-full [&_img]:h-full [&_img]:object-cover"
                        wire:key="home-featured-image-{{ $image->id }}"
                    />
                @endif
            @endforeach
        </div>
    @endif
</div>