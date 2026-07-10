<?php

use App\Models\AiTag;
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
     * @return Collection<int, AiTag>
     */
    #[Computed]
    public function tags(): Collection
    {
        return AiTag::query()
            ->withCount([
                'images as recent_images_count' => fn($query) => $query
                    ->publiclyVisible()
                    ->where('ai_images.created_at', '>=', now()->subDay())
                    ->when($this->category, fn($query, Category $category) => $query->where('ai_images.category_id', (string) $category->id)),
            ])
            ->when($this->mode === 'popular', fn($query) => $query
                ->having('recent_images_count', '>', '0')
                ->orderByDesc('recent_images_count'))
            ->orderBy('name')
            ->limit(20)
            ->get();
    }
}; ?>

@if ($this->tags->isNotEmpty())
    <nav class="mb-5 flex flex-nowrap gap-2 overflow-x-auto pb-1" aria-label="{{ __('Popular tags') }}">
        @foreach ($this->tags as $popularTag)
            <flux:button :href="route('tags.show', $popularTag)" size="xs" variant="ghost" wire:navigate wire:key="popular-tag-{{ $popularTag->id }}">
                #{{ $popularTag->name }}
            </flux:button>
        @endforeach
    </nav>
@endif