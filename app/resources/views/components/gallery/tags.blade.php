<?php

use App\Models\Tag;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $mode = 'popular';

    public ?Category $category = null;

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="mb-5 flex h-7 gap-2 overflow-hidden pb-1" aria-hidden="true">
                <div class="h-6 w-20 shrink-0 animate-pulse rounded-md bg-zinc-100 dark:bg-white/5"></div>
                <div class="h-6 w-28 shrink-0 animate-pulse rounded-md bg-zinc-100 dark:bg-white/5"></div>
                <div class="h-6 w-16 shrink-0 animate-pulse rounded-md bg-zinc-100 dark:bg-white/5"></div>
                <div class="h-6 w-24 shrink-0 animate-pulse rounded-md bg-zinc-100 dark:bg-white/5"></div>
                <div class="h-6 w-32 shrink-0 animate-pulse rounded-md bg-zinc-100 dark:bg-white/5"></div>
                <div class="h-6 w-20 shrink-0 animate-pulse rounded-md bg-zinc-100 dark:bg-white/5"></div>
            </div>
            HTML;
    }

    /**
     * @return Collection<int, Tag>
     */
    #[Computed]
    public function tags(): Collection
    {
        $recentImages = fn($query) => $query
            ->publiclyVisible()
            ->where('generated_media.created_at', '>=', now()->subDay())
            ->when($this->category, fn($query, Category $category) => $query->where('generated_media.category_id', (string) $category->id));

        return Tag::query()
            ->withCount(['media as recent_images_count' => $recentImages])
            ->when($this->mode === 'popular', fn($query) => $query
                ->whereHas('media', $recentImages)
                ->orderByDesc('recent_images_count'))
            ->orderBy('id')
            ->limit(20)
            ->get();
    }
}; ?>

<nav class="mb-5 flex flex-nowrap gap-2 overflow-x-auto pb-1" aria-label="{{ __('Popular tags') }}">
    @foreach ($this->tags as $popularTag)
        <flux:button :href="$category ? route('categories.show', ['category' => $category, 'tag' => $popularTag->slug]) : route('tags.show', $popularTag)" size="xs" variant="ghost" wire:navigate wire:key="popular-tag-{{ $popularTag->id }}">
            #{{ $popularTag->name }}
        </flux:button>
    @endforeach
</nav>