<?php

use App\Models\AiImage;
use App\Models\AiImageFavorite;
use App\Models\User;
use App\Services\AiImageEditor;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedImageId = null;

    public bool $show = false;

    public function mount(): void
    {
        $routeImage = request()->route('image');

        if ($routeImage instanceof AiImage) {
            $this->openImage($routeImage->id);

            return;
        }

        if (request()->routeIs('images.index') && request()->boolean('composer')) {
            $id = request()->integer('image') ?: $this->latestCreatedImage()?->id;

            if ($id) {
                $this->openImage($id);
            }
        }
    }

    public function openImage(int $id): void
    {
        $image = $this->visibleImage($id);

        if (! $image) {
            $this->closeImage();

            return;
        }

        $this->selectedImageId = $image->id;
        $this->show = true;
        unset($this->selectedImage, $this->relatedImages, $this->favoriteIds);
    }

    public function closeImage(): void
    {
        $this->selectedImageId = null;
        $this->show = false;
        unset($this->selectedImage, $this->relatedImages, $this->favoriteIds);
    }

    public function refreshCompletedImage(array $payload = []): void
    {
        if ((int) ($payload['image_id'] ?? 0) !== $this->selectedImageId) {
            return;
        }

        unset($this->selectedImage, $this->relatedImages);
        $this->dispatch('image-usage-updated');
    }

    public function toggleFavorite(int $id): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $image = $this->publicImage($id);

        if (! $image) {
            return;
        }

        $userId = (int) Auth::id();
        $favorite = AiImageFavorite::query()->where('user_id', $userId)->where('ai_image_id', $image->id)->first();
        $wasFavorite = $favorite !== null;

        $wasFavorite
            ? $favorite->delete()
            : AiImageFavorite::query()->create(['user_id' => $userId, 'ai_image_id' => $image->id]);

        unset($this->selectedImage, $this->relatedImages, $this->favoriteIds);
        $this->dispatch('gallery-updated');

        Flux::toast(variant: 'success', text: $wasFavorite ? __('Remove favorite') : __('Favorite image'));
    }

    public function useAsPrompt(int $id): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $image = $this->visibleImage($id);

        if (! $image) {
            return;
        }

        $this->dispatch(
            'use-prompt',
            prompt: $image->prompt,
            imageId: $this->canUseAsReference($image) ? $image->id : null,
        );
    }

    public function toggleFeatured(int $id): void
    {
        if (! $this->canManageFeatured()) {
            return;
        }

        $image = AiImage::query()
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->whereKey($id)
            ->first();

        if (! $image) {
            return;
        }

        $image->update(['is_featured' => ! $image->is_featured]);

        unset($this->selectedImage, $this->relatedImages);
        $this->dispatch('gallery-updated');

        Flux::toast(variant: 'success', text: $image->is_featured ? __('Image featured.') : __('Image unfeatured.'));
    }

    #[Computed]
    public function selectedImage(): ?AiImage
    {
        return $this->selectedImageId ? $this->visibleImage($this->selectedImageId) : null;
    }

    #[Computed]
    public function relatedImages()
    {
        return $this->selectedImage && $this->isPublicImage($this->selectedImage)
            ? app(AiImageEditor::class)->relatedPublished($this->selectedImage, 6)
            : collect();
    }

    #[Computed]
    public function favoriteIds(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return AiImageFavorite::query()
            ->where('user_id', (int) Auth::id())
            ->pluck('ai_image_id')
            ->all();
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

    public function isFavorite(AiImage $image): bool
    {
        return in_array($image->id, $this->favoriteIds, true);
    }

    public function favoriteCount(AiImage $image): int
    {
        return (int) ($image->favorites_count ?? 0);
    }

    public function canFavorite(AiImage $image): bool
    {
        return Auth::check() && $this->isPublicImage($image);
    }

    public function canUseAsReference(AiImage $image): bool
    {
        return $this->isPublicImage($image);
    }

    public function canManageFeatured(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    public function statusLabel(AiImage $image): string
    {
        return match ($image->status) {
            'pending' => __('Creating'),
            'failed' => __('Failed'),
            default => $image->is_published ? __('Published') : __('Unpublished'),
        };
    }

    public function progressLabel(AiImage $image): string
    {
        return match (data_get($image->request_meta, 'progress', 'queued')) {
            'reviewing' => __('Reviewing prompt...'),
            'generating' => __('Waiting for image API response...'),
            'saving' => __('Saving generated image...'),
            default => __('Waiting in queue...'),
        };
    }

    public function progressStep(AiImage $image): int
    {
        return match (data_get($image->request_meta, 'progress', 'queued')) {
            'reviewing' => 2,
            'generating' => 3,
            'saving' => 4,
            default => 1,
        };
    }

    protected function getListeners(): array
    {
        $userId = Auth::id();

        return $userId ? ['echo-private:App.Models.User.'.$userId.',AiImageCompleted' => 'refreshCompletedImage'] : [];
    }

    private function latestCreatedImage(): ?AiImage
    {
        return app(AiImageEditor::class)->guestHistory(request(), 1)->first();
    }

    private function visibleImage(int $id): ?AiImage
    {
        $user = Auth::user();
        $query = AiImage::query()->with(['category', 'user', 'tags'])->whereKey($id);

        if ($user instanceof User && $user->isAdmin()) {
            return $query->first();
        }

        return $query
            ->where(function ($query) use ($user): void {
                $query->where(function ($query): void {
                    $query->where('is_published', true)
                        ->where('status', 'succeeded')
                        ->whereNotNull('result_path');
                });

                if ($user instanceof User) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->first();
    }

    private function publicImage(int $id): ?AiImage
    {
        return AiImage::query()
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->whereKey($id)
            ->first();
    }

    private function isPublicImage(AiImage $image): bool
    {
        return $image->is_published && $image->status === 'succeeded' && filled($image->result_path);
    }
}; ?>

<div class="contents" x-data x-on:open-image-detail.window="$wire.openImage($event.detail.id)">
    @php($selected = $this->selectedImage)

    @if ($show && $selected)
        @php($selectedUrl = $this->imageUrl($selected))
        @php($progressStep = $this->progressStep($selected))

        <div class="fixed inset-0 z-60 bg-white/95 text-zinc-950 backdrop-blur dark:bg-zinc-950/95 dark:text-white" role="dialog" aria-modal="true" aria-label="{{ __('Image details') }}" wire:key="image-detail-{{ $selected->id }}" @if ($selected->status === 'pending') wire:poll.2s @endif>
            <div class="h-full overflow-y-auto lg:grid lg:grid-cols-[minmax(0,1fr)_360px] lg:grid-rows-1 lg:overflow-hidden">
                <div class="bg-zinc-100 dark:bg-black/30 lg:grid lg:min-h-0 lg:grid-rows-[auto_minmax(0,1fr)]">
                    <header class="z-10 flex items-center justify-between bg-white/55 px-4 py-3 backdrop-blur dark:bg-zinc-950/35">
                        <flux:button type="button" variant="filled" icon="x-mark" wire:click="closeImage">{{ __('Close') }}</flux:button>

                        <div class="flex items-center gap-2">
                            @if ($this->canManageFeatured() && $this->isPublicImage($selected))
                                <flux:button type="button" :variant="$selected->is_featured ? 'primary' : 'filled'" icon="star" wire:click="toggleFeatured({{ $selected->id }})">
                                    {{ $selected->is_featured ? __('Unfeature image') : __('Feature image') }}
                                </flux:button>
                            @endif

                            @if ($this->canFavorite($selected))
                                <flux:button type="button" :variant="$this->isFavorite($selected) ? 'primary' : 'filled'" icon="heart" wire:click="toggleFavorite({{ $selected->id }})">
                                    {{ $this->isFavorite($selected) ? __('Remove favorite') : __('Favorite image') }} · {{ $this->favoriteCount($selected) }}
                                </flux:button>
                            @endif
                        </div>
                    </header>

                    <div class="flex items-start justify-center overflow-hidden sm:p-4 lg:min-h-0 lg:items-center">
                        @if ($selectedUrl)
                            <img class="h-auto w-full sm:rounded-2xl sm:shadow-2xl lg:h-full lg:w-full lg:object-contain" src="{{ $selectedUrl }}" alt="{{ Str::limit($selected->prompt, 80) }}" decoding="async">
                        @elseif ($selected->status === 'pending')
                            <div class="relative flex aspect-square w-full max-w-md items-center justify-center overflow-hidden rounded-4xl bg-zinc-100 text-zinc-700 shadow-inner dark:bg-white/10 dark:text-white/80">
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,var(--color-zinc-50),var(--color-zinc-200))] dark:bg-[radial-gradient(circle_at_center,rgba(255,255,255,.12),rgba(255,255,255,.04))]"></div>
                                <div class="relative flex w-4/5 max-w-64 flex-col items-center gap-4 rounded-3xl border border-white/80 bg-white/85 p-5 text-center shadow-lg backdrop-blur dark:border-white/10 dark:bg-zinc-900/80">
                                    <div class="relative flex size-16 items-center justify-center">
                                        <div class="absolute inset-0 animate-spin rounded-full border-4 border-zinc-200 border-t-zinc-900 dark:border-white/15 dark:border-t-white"></div>
                                        <flux:icon class="size-7" name="sparkles" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold">{{ __('Creating image...') }}</div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $this->progressLabel($selected) }}</div>
                                    </div>
                                    <div class="h-1 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10" role="progressbar" aria-valuemin="1" aria-valuemax="4" aria-valuenow="{{ $progressStep }}" aria-label="{{ $this->progressLabel($selected) }}">
                                        <div class="h-full animate-pulse rounded-full bg-zinc-900 transition-[width] dark:bg-white" style="width: {{ $progressStep * 25 }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="flex aspect-square w-full max-w-md items-center justify-center rounded-4xl bg-red-50 text-center text-red-600 shadow-inner dark:bg-red-400/10 dark:text-red-200">
                                <div class="max-w-xs p-8">
                                    <flux:icon class="mx-auto mb-4 size-12" name="exclamation-triangle" />
                                    <div class="text-lg font-semibold">{{ __('Failed') }}</div>
                                    <div class="mt-2 text-sm">{{ $selected->error ?: __('Could not create this image.') }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <aside class="min-h-0 border-l border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-zinc-950 lg:overflow-y-auto">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-full bg-zinc-900 text-sm font-bold text-white dark:bg-white dark:text-zinc-950">
                                {{ Str::upper(Str::substr($this->creatorName($selected), 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold">{{ $this->creatorName($selected) }}</div>
                                <div class="text-xs text-zinc-500">{{ $selected->published_at?->diffForHumans() ?? $selected->created_at?->diffForHumans() }}</div>
                            </div>
                        </div>
                        <flux:button type="button" variant="ghost" icon="x-mark" wire:click="closeImage">{{ __('Close') }}</flux:button>
                    </div>

                    <div class="mb-5 flex flex-wrap gap-2">
                        <flux:badge size="sm" :color="$selected->status === 'failed' ? 'red' : null">{{ $this->statusLabel($selected) }}</flux:badge>
                        @if ($selected->is_featured)
                            <flux:badge size="sm" color="amber">{{ __('Featured') }}</flux:badge>
                        @endif
                        @if ($selected->category)
                            <span class="inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ $selected->category->name }}</span>
                        @endif
                        @foreach ($selected->tags as $tag)
                            <span class="inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-white/10 dark:text-zinc-300">#{{ $tag->name }}</span>
                        @endforeach
                    </div>

                    <div class="space-y-3" x-data="{ copied: false, expanded: false, prompt: @js($selected->prompt) }">
                        <div>
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('Prompt') }}</div>
                            <div class="relative max-h-[200px] overflow-hidden rounded-2xl bg-zinc-100 p-4 text-sm leading-6 dark:bg-white/10" :class="expanded ? 'max-h-none' : 'max-h-[200px]'">
                                <p>{{ $selected->prompt }}</p>
                                <div x-show="! expanded" x-cloak class="pointer-events-none absolute inset-x-0 bottom-0 h-12 rounded-b-2xl bg-linear-to-t from-zinc-100 to-transparent dark:from-zinc-900"></div>
                            </div>
                            <flux:button class="mt-2 w-full" type="button" size="sm" variant="ghost" x-on:click="expanded = ! expanded" x-bind:aria-expanded="expanded ? 'true' : 'false'">
                                <span x-text="expanded ? @js(__('Show less')) : @js(__('Show more'))"></span>
                            </flux:button>
                        </div>

                        @if ($selected->status === 'failed' && filled($selected->error))
                            <div class="rounded-2xl bg-red-50 p-4 text-sm text-red-700 dark:bg-red-400/10 dark:text-red-100">{{ $selected->error }}</div>
                        @endif

                        <div class="grid grid-cols-2 gap-2">
                            <flux:button type="button" variant="filled" x-on:click="navigator.clipboard.writeText(prompt); copied = true; setTimeout(() => copied = false, 1400)">
                                <span x-text="copied ? @js(__('Copied')) : @js(__('Copy prompt'))"></span>
                            </flux:button>

                            <flux:button type="button" variant="primary" wire:click="useAsPrompt({{ $selected->id }})">{{ __('Create similar image') }}</flux:button>

                            @if ($selectedUrl)
                                <flux:button class="col-span-2" :href="$selectedUrl" download="{{ $selected->downloadName() }}">{{ __('Download') }}</flux:button>
                            @endif
                        </div>
                    </div>

                    @if ($this->relatedImages->isNotEmpty())
                        <div class="mt-7">
                            <div class="mb-3 text-sm font-semibold">{{ __('Similar images') }}</div>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ($this->relatedImages as $related)
                                    @php($relatedUrl = $this->imageUrl($related))
                                    @if ($relatedUrl)
                                        <a class="overflow-hidden rounded-2xl bg-zinc-100 dark:bg-white/10" href="{{ $this->detailUrl($related) }}" x-data x-on:click.prevent="$dispatch('open-image-detail', { id: {{ $related->id }} })" wire:key="related-image-detail-{{ $related->id }}">
                                            <img class="aspect-3/4 w-full object-cover" src="{{ $relatedUrl }}" alt="{{ Str::limit($related->prompt, 50) }}" loading="lazy">
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    @endif
</div>
