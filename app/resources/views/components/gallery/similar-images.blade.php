<?php

use App\Models\GeneratedMedia;
use App\Services\GeneratedMediaService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $imageId;

    #[Locked]
    public bool $standalone = false;

    private const LIMIT = 20;

    #[Computed]
    public function images(): Collection
    {
        $image = GeneratedMedia::query()
            ->with('tags')
            ->publiclyVisible()
            ->find($this->imageId);

        return $image
            ? app(GeneratedMediaService::class)->relatedPublished($image, self::LIMIT)
            : new Collection;
    }

    public function imageUrl(GeneratedMedia $image): ?string
    {
        return app(GeneratedMediaService::class)->imageUrl($image, 'xs');
    }

    public function imageSize(GeneratedMedia $image): ?array
    {
        return app(GeneratedMediaService::class)->imageSize($image, 'xs');
    }

    public function detailUrl(GeneratedMedia $image): string
    {
        return route('images.show', $image);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="mt-7" aria-hidden="true">
                <div class="mb-3 h-7 w-32 animate-pulse rounded-lg bg-zinc-100 dark:bg-white/10"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="aspect-square animate-pulse rounded-2xl bg-zinc-100 dark:bg-white/10"></div>
                    <div class="aspect-square animate-pulse rounded-2xl bg-zinc-100 dark:bg-white/10"></div>
                </div>
            </div>
            HTML;
    }
}; ?>

<div class="mt-7">
    @if ($this->images->isNotEmpty())
        <div class="mb-3 text-lg font-semibold">{{ __('Similar images') }}</div>
        <x-gallery.list :images="$this->images" class="gap-x-3 gap-y-2" style="grid-template-columns: repeat(2, minmax(0, 1fr))">
            @foreach ($this->images as $related)
                @php($relatedUrl = $this->imageUrl($related))
                @php($relatedSize = $this->imageSize($related))
                @php($relatedTitle = Str::limit($related->title ?: $related->prompt, 70, ''))
                @if ($relatedUrl)
                    <a class="overflow-hidden rounded-2xl bg-zinc-100 dark:bg-white/10" href="{{ $this->detailUrl($related) }}" @if ($standalone) wire:navigate @else x-data x-on:click.prevent="$dispatch('open-image-detail', { id: {{ $related->id }}, url: @js($this->detailUrl($related)), title: @js($relatedTitle), preview: @js($relatedUrl) })" @endif wire:key="related-image-detail-{{ $related->id }}">
                        <img class="block h-auto w-full" src="{{ $relatedUrl }}" alt="{{ Str::limit($related->title ?: $related->prompt, 50) }}" @if ($relatedSize) width="{{ $relatedSize['width'] }}" height="{{ $relatedSize['height'] }}" @endif loading="lazy" decoding="async">
                    </a>
                @endif
            @endforeach
        </x-gallery.list>
    @endif
</div>
