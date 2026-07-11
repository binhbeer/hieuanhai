<?php

use App\Jobs\CreateAiImage;
use App\Models\AiImage;
use App\Models\AiImageFavorite;
use App\Models\User;
use App\Services\AiImageEditor;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $selectedImageId = null;

    public bool $show = false;

    public bool $standalone = false;

    public function mount(?AiImage $image = null, ?int $selectedImageId = null, bool $standalone = false): void
    {
        $this->selectedImageId = $selectedImageId;
        $this->standalone = $standalone;

        if ($image) {
            abort_unless($this->isPublicImage($image), 404);

            $this->standalone = true;
            $this->openImage($image->id);

            return;
        }

        if ($this->standalone) {
            $image = $this->selectedImageId ? $this->publicImage($this->selectedImageId) : null;

            abort_unless($image instanceof AiImage, 404);

            $this->openImage($image->id);

            return;
        }

        if (request()->routeIs('images.index')) {
            $id = request()->integer('image');

            if (!$id && request()->boolean('composer')) {
                $id = $this->latestCreatedImage()?->id;
            }

            if ($id) {
                $this->openImage($id);
            }
        }
    }

    public function openImage(int $id): void
    {
        $image = $this->visibleImage($id);

        if (!$image) {
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
        if (!Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $image = $this->publicImage($id);

        if (!$image) {
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
        if (!Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $image = $this->visibleImage($id);

        if (!$image) {
            return;
        }

        $this->dispatch('use-prompt', prompt: $image->prompt);

        if (!$this->standalone) {
            $this->closeImage();
        }
    }

    public function editImage(int $id): void
    {
        if (!Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $image = AiImage::query()
            ->where('user_id', Auth::id())
            ->whereKey($id)
            ->first();

        if (!$image) {
            return;
        }

        $this->dispatch('edit-image', imageId: $image->id);

        if (!$this->standalone) {
            $this->closeImage();
        }
    }

    public function toggleFeatured(int $id): void
    {
        if (!$this->canManageFeatured()) {
            return;
        }

        $image = AiImage::query()
            ->publiclyVisible()
            ->whereKey($id)
            ->first();

        if (!$image) {
            return;
        }

        $image->update(['is_featured' => !$image->is_featured]);

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
        if (!Auth::check()) {
            return [];
        }

        return AiImageFavorite::query()
            ->where('user_id', (int) Auth::id())
            ->pluck('ai_image_id')
            ->all();
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

    public function canEdit(AiImage $image): bool
    {
        return Auth::check() && $image->user_id === Auth::id();
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
        if ($image->updated_at?->lt(now()->subMinutes(CreateAiImage::STALE_AFTER_MINUTES))) {
            return __('Task interrupted. Please try again.');
        }

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

        return $userId ? ['echo-private:App.Models.User.' . $userId . ',AiImageCompleted' => 'refreshCompletedImage'] : [];
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
                $query->where(fn($query) => $query->publiclyVisible());

                if ($user instanceof User) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->first();
    }

    private function publicImage(int $id): ?AiImage
    {
        return AiImage::query()
            ->publiclyVisible()
            ->whereKey($id)
            ->first();
    }

    private function isPublicImage(AiImage $image): bool
    {
        return $image->is_published && $image->status === 'succeeded' && filled($image->result_path);
    }
}; ?>

<div class="contents" x-data="{
    previousUrl: null,
    previousTitle: null,
    siteName: @js(\App\Support\AppSettings::string('site.name', config('app.name', 'GenAnh'))),
    openImage(id, url, title) {
        if (url) {
            if (this.previousUrl) {
                history.replaceState(null, '', url);
            } else {
                this.previousUrl = window.location.href;
                this.previousTitle = document.title;
                history.replaceState(null, '', url);
            }
        }

        if (title) document.title = `${title} - ${this.siteName}`;

        $wire.openImage(id);
    },
    closeImage() {
        if (this.previousUrl) {
            history.replaceState(null, '', this.previousUrl);
            document.title = this.previousTitle;
            this.previousUrl = null;
            this.previousTitle = null;
        }

        $wire.closeImage();
    },
}" x-on:keydown.escape.window="{{ $standalone ? "window.location.href = '" . route('home') . "'" : 'closeImage()' }}" @if (!$standalone) x-on:open-image-detail.window="openImage($event.detail.id, $event.detail.url, $event.detail.title)" @endif>
    @php($selected = $this->selectedImage())

    @if ($show && $selected)
    @php($selectedUrl = $this->imageUrl($selected))
    @php($selectedThumbUrl = $this->imageUrl($selected, 'md'))
    @php($selectedImageSize = $this->imageSize($selected, 'md'))
    @php($progressStep = $this->progressStep($selected))
    @php($selectedTitle = $selected->title ?: $selected->prompt)
    @php($canViewFullPrompt = Auth::check())
    @php($visiblePrompt = $canViewFullPrompt ? $selected->prompt : Str::limit($selected->prompt, 160))

    <div class="{{ $standalone ? 'h-dvh' : 'fixed inset-0 z-50' }} flex flex-col overflow-y-auto bg-zinc-100/90 text-zinc-950 dark:bg-zinc-950/80 dark:text-white md:grid md:backdrop-blur md:grid-cols-[1fr_480px] md:grid-rows-[minmax(0,1fr)] md:overflow-hidden" @if (!$standalone) role="dialog" aria-modal="true" aria-label="{{ __('Image details') }}" @endif wire:key="image-detail-{{ $selected->id }}" @if ($selected->status === 'pending') wire:poll.2s @endif>
        <div class="relative flex flex-col shrink-0 md:shrink md:flex-1">
            <div class="fixed inset-x-0 top-0 z-20 flex h-16 min-w-0 items-center gap-3 bg-zinc-100/90 px-4 backdrop-blur dark:bg-zinc-950/80 md:absolute md:inset-x-4 md:top-4 md:h-auto md:bg-transparent md:p-0 md:backdrop-blur-none">
                @if (filled($selected->title))
                    <h1 class="min-w-0 flex-1 truncate text-lg font-semibold tracking-tight" title="{{ $selected->title }}">{{ $selected->title }}</h1>
                @else
                    <div class="flex-1"></div>
                @endif

                <div class="flex shrink-0 items-center gap-1">
                    @if ($this->canManageFeatured() && $this->isPublicImage($selected))
                        <flux:button type="button" size="sm" :variant="'primary'" :color="$selected->is_featured ? 'amber' : 'zinc'" wire:click="toggleFeatured({{ $selected->id }})" :aria-label="$selected->is_featured ? __('Featured') : __('Feature image')">
                            <x-slot name="icon"><x-iconsax-bul-star class="size-5" /></x-slot>
                        </flux:button>
                    @endif

                    @if ($this->canFavorite($selected))
                        <flux:button type="button" size="sm" :variant="'primary'" :color="$this->isFavorite($selected) ? 'amber' : 'zinc'" wire:click="toggleFavorite({{ $selected->id }})" :aria-label="$this->isFavorite($selected) ? __('Remove favorite') : __('Favorite image')">
                            <x-slot name="icon"><x-iconsax-two-heart class="size-5" /></x-slot>
                            {{ $this->favoriteCount($selected) }}
                        </flux:button>
                    @endif

                    @if ($standalone)
                        <flux:button size="sm" :href="route('home')" wire:navigate :variant="'primary'">
                            <x-slot name="icon"><x-iconsax-bul-close-circle class="size-5" /></x-slot>
                            Esc
                        </flux:button>
                    @else
                        <flux:button type="button" size="sm" :variant="'primary'" x-on:click="closeImage">
                            <x-slot name="icon"><x-iconsax-bul-close-circle class="size-5" /></x-slot>
                            Esc
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="flex flex-1 items-start justify-center overflow-hidden pt-16 sm:px-4 sm:pb-4 md:min-h-0 md:items-center md:p-4">
                <div class="flex flex-1 items-center justify-center gap-4 p-0 md:px-16 md:py-16">
                    @if ($selectedThumbUrl)
                        <div class="relative flex flex-col min-w-0 flex-1 gap-2 items-center justify-center overflow-hidden">
                            <div class="relative flex max-h-[62svh] max-w-full items-center justify-center overflow-hidden rounded-xl md:max-h-[calc(100svh-10rem)] md:rounded-2xl">
                                <img class="block h-auto max-h-[62svh] max-w-full object-contain opacity-100 md:max-h-[calc(100svh-10rem)]" src="{{ $selectedThumbUrl }}" alt="{{ Str::limit($selectedTitle, 80) }}" @if ($selectedImageSize) width="{{ $selectedImageSize['width'] }}" height="{{ $selectedImageSize['height'] }}" @endif decoding="async" />
                            </div>
                        </div>
                    @elseif ($selected->status === 'pending')
                        <div class="relative flex aspect-square w-full max-w-md items-center justify-center overflow-hidden rounded-4xl bg-zinc-100 text-zinc-700 shadow-inner dark:bg-white/10 dark:text-white/80">
                            <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,var(--color-zinc-50),var(--color-zinc-200))] dark:bg-[radial-gradient(circle_at_center,rgba(255,255,255,.12),rgba(255,255,255,.04))]"></div>
                            <div class="relative flex w-4/5 max-w-64 flex-col items-center gap-4 rounded-3xl border border-white/80 bg-white/85 p-5 text-center shadow-lg backdrop-blur dark:border-white/10 dark:bg-zinc-900/80">
                                <div class="relative flex size-16 items-center justify-center">
                                    <div class="absolute inset-0 animate-spin rounded-full border-4 border-zinc-200 border-t-zinc-900 dark:border-white/15 dark:border-t-white"></div>
                                    <x-iconsax-two-magic-star class="size-7" />
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
                                <x-iconsax-two-danger class="mx-auto mb-4 size-12" />
                                <div class="text-lg font-semibold">{{ __('Failed') }}</div>
                                <div class="mt-2 text-sm">{{ $selected->error ?: __('Could not create this image.') }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <aside class="flex flex-col border-l border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-950 md:min-h-0 md:overflow-hidden">
            <header class="flex shrink-0 items-center justify-between gap-3 border-b border-zinc-200 p-3 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <flux:avatar size="lg" circle :name="$this->creatorName($selected)" :initials="$selected->user?->initials() ?? Str::upper(Str::substr($this->creatorName($selected), 0, 1))" :src="$selected->user?->avatar_path ? Storage::url($selected->user->avatar_path) : null" />
                    <div class="text-sm font-semibold">{{ $this->creatorName($selected) }}</div>
                </div>
                <div class="text-right">
                    <flux:badge size="sm" :color="$selected->status === 'failed' ? 'red' : null">{{ $this->statusLabel($selected) }}</flux:badge>
                    <div class="mt-1 text-xs text-zinc-500">{{ $selected->published_at?->diffForHumans() ?? $selected->created_at?->diffForHumans() }}</div>
                </div>
            </header>

            <div class="flex-1 p-4 md:min-h-0 md:overflow-y-auto">
                <div class="mb-5 flex flex-wrap gap-1">
                    @if ($selected->is_featured)
                        <flux:badge size="sm" color="amber">{{ __('Featured') }}</flux:badge>
                    @endif
                    @if ($selected->category)
                        <flux:button :href="route('categories.show', $selected->category)" size="xs" variant="ghost" wire:navigate>{{ $selected->category->name }}</flux:button>
                    @endif
                    @foreach ($selected->tags as $tag)
                        <flux:button :href="route('tags.show', $tag)" size="xs" variant="ghost" wire:navigate wire:key="image-detail-tag-{{ $tag->id }}">
                            #{{ $tag->name }}
                        </flux:button>
                    @endforeach
                </div>

                <div class="space-y-3" x-data="{ copied: false, expanded: false, prompt: @js($canViewFullPrompt ? $selected->prompt : $visiblePrompt) }">
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('Prompt') }}</div>
                            @if ($canViewFullPrompt)
                                <flux:button type="button" size="xs" variant="ghost" x-on:click="navigator.clipboard.writeText(prompt); copied = true; setTimeout(() => copied = false, 1400)">
                                    <x-slot name="icon"><x-iconsax-two-clipboard class="size-5" /></x-slot>
                                    <span x-text="copied ? @js(__('Copied')) : @js(__('Copy prompt'))"></span>
                                </flux:button>
                            @endif
                        </div>
                        <div class="text-sm leading-5.5">
                            <p :class="expanded ? '' : 'line-clamp-7'">{{ $visiblePrompt }}</p>
                            @if (!$canViewFullPrompt && mb_strlen($selected->prompt) > 160)
                                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Log in to view full prompt.') }}</p>
                            @endif
                        </div>
                        @if ($canViewFullPrompt)
                            <flux:button class="mt-2" type="button" size="xs" variant="ghost" icon:trailing="chevron-down" x-on:click="expanded = ! expanded" x-bind:aria-expanded="expanded ? 'true' : 'false'">
                                <span x-text="expanded ? @js(__('Show less')) : @js(__('Show more'))"></span>
                            </flux:button>
                        @endif
                    </div>

                    @if ($selected->status === 'failed' && filled($selected->error))
                        <div class="rounded-2xl bg-red-50 p-4 text-sm text-red-700 dark:bg-red-400/10 dark:text-red-100">{{ $selected->error }}</div>
                    @endif
                </div>

                @if ($this->relatedImages->isNotEmpty())
                <div class="mt-7">
                    <div class="mb-3 text-lg font-semibold">{{ __('Similar images') }}</div>
                    <x-gallery.list :images="$this->relatedImages" class="gap-x-3 gap-y-2" style="grid-template-columns: repeat(2, minmax(0, 1fr))">
                        @foreach ($this->relatedImages as $related)
                        @php($relatedUrl = $this->imageUrl($related, 'xs'))
                        @php($relatedSize = $this->imageSize($related, 'xs'))
                        @php($relatedTitle = Str::limit($related->title ?: $related->prompt, 70, ''))
                        @if ($relatedUrl)
                            <a class="overflow-hidden rounded-2xl bg-zinc-100 dark:bg-white/10" href="{{ $this->detailUrl($related) }}" @if ($standalone) wire:navigate @else x-data x-on:click.prevent="$dispatch('open-image-detail', { id: {{ $related->id }}, url: @js($this->detailUrl($related)), title: @js($relatedTitle) })" @endif wire:key="related-image-detail-{{ $related->id }}">
                                <img class="block h-auto w-full" src="{{ $relatedUrl }}" alt="{{ Str::limit($related->title ?: $related->prompt, 50) }}" @if ($relatedSize) width="{{ $relatedSize['width'] }}" height="{{ $relatedSize['height'] }}" @endif loading="lazy">
                            </a>
                        @endif
                        @endforeach
                    </x-gallery.list>
                </div>
                @endif
            </div>

            <footer class="sticky bottom-0 z-10 shrink-0 border-t border-zinc-200 bg-white p-2 dark:border-white/10 dark:bg-zinc-950 md:static">
                <div class="grid gap-2 {{ $this->canEdit($selected) && $selectedUrl ? 'grid-cols-[auto_minmax(0,1fr)_auto]' : 'grid-cols-2' }}">
                    @if ($selectedUrl)
                        <flux:button :href="$selectedUrl" download="{{ $selected->downloadName() }}">
                            <x-slot name="icon"><x-iconsax-two-document-download class="size-5" /></x-slot>
                            {{ __('Download') }}
                        </flux:button>
                    @endif

                    <flux:button type="button" variant="primary" wire:click="useAsPrompt({{ $selected->id }})">
                        <x-slot name="icon"><x-iconsax-two-magic-star class="size-5" /></x-slot>
                        {{ __('Create similar image') }}
                    </flux:button>

                    @if ($this->canEdit($selected))
                        <flux:button type="button" variant="filled" wire:click="editImage({{ $selected->id }})">{{ __('Edit image') }}</flux:button>
                    @endif
                </div>
            </footer>
        </aside>
    </div>
    @endif
</div>