<?php

use App\Models\GeneratedMedia;
use App\Services\GeneratedMediaService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private const LIMIT = 36;

    #[Computed]
    public function images(): Collection
    {
        return app(GeneratedMediaService::class)->publishedGallery(limit: self::LIMIT, sort: 'featured');
    }

    public function imageUrl(GeneratedMedia $image): ?string
    {
        return app(GeneratedMediaService::class)->imageUrl($image, 'sm');
    }

    public function imageSize(GeneratedMedia $image): ?array
    {
        return app(GeneratedMediaService::class)->imageSize($image, 'sm');
    }

    public function detailUrl(GeneratedMedia $image): string
    {
        return route('images.show', $image);
    }

    public function creatorName(GeneratedMedia $image): string
    {
        return $image->user?->name ?: __('Guest');
    }

    public function galleryUrl(): string
    {
        $url = route('gallery.index', ['sort' => 'featured']);
        $lastImage = $this->images->last();

        return $lastImage ? $url.'#image-'.$lastImage->id : $url;
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <section aria-hidden="true">
                <div class="space-y-2">
                    <div class="h-8 w-44 animate-pulse rounded-lg bg-zinc-100 dark:bg-white/10"></div>
                    <div class="h-4 w-64 max-w-full animate-pulse rounded bg-zinc-100 dark:bg-white/10"></div>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
                    <div class="aspect-4/5 animate-pulse rounded-2xl bg-zinc-100 dark:bg-white/10"></div>
                    <div class="aspect-square animate-pulse rounded-2xl bg-zinc-100 dark:bg-white/10"></div>
                    <div class="aspect-3/4 animate-pulse rounded-2xl bg-zinc-100 dark:bg-white/10"></div>
                    <div class="aspect-4/5 animate-pulse rounded-2xl bg-zinc-100 dark:bg-white/10"></div>
                    <div class="hidden aspect-square animate-pulse rounded-2xl bg-zinc-100 md:block dark:bg-white/10"></div>
                    <div class="hidden aspect-3/4 animate-pulse rounded-2xl bg-zinc-100 md:block dark:bg-white/10"></div>
                </div>
                <div class="mt-8 flex justify-center">
                    <div class="h-9 w-28 animate-pulse rounded-xl bg-zinc-100 dark:bg-white/10"></div>
                </div>
            </section>
            HTML;
    }
}; ?>

<section aria-labelledby="featured-gallery-heading">
    <div>
        <flux:heading id="featured-gallery-heading" size="xl">{{ __('Featured images') }}</flux:heading>
        <flux:text class="font-medium text-zinc-600! dark:text-zinc-300!">{{ __('Fresh inspiration from the GenAnh community.') }}</flux:text>
    </div>

    @if ($this->images->isEmpty())
        <div class="mt-6 flex min-h-64 items-center justify-center rounded-4xl border border-dashed border-zinc-300 bg-white text-center dark:border-white/10 dark:bg-white/5">
            <div class="max-w-sm p-8">
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-white/10">
                    <x-iconsax-two-gallery class="size-7 text-zinc-500" />
                </div>
                <flux:heading size="lg">{{ __('No published images yet') }}</flux:heading>
                <flux:text class="mt-2" variant="subtle">{{ __('Create and publish an image to make it appear here.') }}</flux:text>
            </div>
        </div>
    @else
        <x-gallery.list :images="$this->images" class="mt-6 2xl:!grid-cols-6">
            @foreach ($this->images as $image)
                @php($url = $this->imageUrl($image))
                @php($imageSize = $this->imageSize($image))
                @if ($url)
                    <x-gallery.item :image="$image" :url="$url" :image-size="$imageSize" :detail-url="$this->detailUrl($image)" :creator="$this->creatorName($image)" loading="lazy" wire:key="creator-featured-image-{{ $image->id }}" />
                @endif
            @endforeach
        </x-gallery.list>
        <div class="mt-8 flex justify-center">
            <flux:button :href="$this->galleryUrl()" variant="outline" icon:trailing="arrow-right">{{ __('View more') }}</flux:button>
        </div>
    @endif
</section>
