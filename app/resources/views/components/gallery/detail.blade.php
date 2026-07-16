<?php

use App\Jobs\CreateAiImage;
use App\Models\GeneratedMedia;
use App\Models\MediaFavorite;
use App\Models\User;
use App\Services\AiImageEditor;
use App\Support\GptImageOptions;
use Flux\Flux;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedImageId = null;

    public bool $show = false;

    public bool $standalone = false;

    public function mount(?GeneratedMedia $image = null, ?int $selectedImageId = null, bool $standalone = false): void
    {
        $this->selectedImageId = $selectedImageId;
        $this->standalone = $standalone;

        if ($image) {
            abort_unless($this->isPublicImage($image), 404);

            if (request()->route()?->originalParameter('image') !== $image->getRouteKey()) {
                abort(new RedirectResponse(route('images.show', $image), 301));
            }

            $this->standalone = true;
            $this->openImage($image->id);

            return;
        }

        if ($this->standalone) {
            $image = $this->selectedImageId ? $this->publicImage($this->selectedImageId) : null;

            abort_unless($image instanceof GeneratedMedia, 404);

            $this->openImage($image->id);

            return;
        }

        if (\App\Support\LocalizedRoute::is('history.index')) {
            $id = request()->integer('image');

            if (! $id && request()->boolean('composer')) {
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

        if (! $image) {
            $this->closeImage();

            return;
        }

        $this->selectedImageId = $image->id;
        $this->show = true;
        unset($this->selectedImage, $this->favoriteIds);
    }

    public function closeImage(): void
    {
        $this->selectedImageId = null;
        $this->show = false;
        unset($this->selectedImage, $this->favoriteIds);
    }

    public function refreshCompletedImage(array $payload = []): void
    {
        if ((int) ($payload['image_id'] ?? 0) !== $this->selectedImageId) {
            return;
        }

        unset($this->selectedImage);
        $this->dispatch('image-usage-updated');
    }

    public function toggleFavorite(int $id): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $image = $this->publicImage($id);

        if (! $image) {
            return;
        }

        $userId = (int) Auth::id();
        $favorite = MediaFavorite::query()->where('user_id', $userId)->where('media_id', $image->id)->first();
        $wasFavorite = $favorite !== null;

        $wasFavorite
            ? $favorite->delete()
            : MediaFavorite::query()->create(['user_id' => $userId, 'media_id' => $image->id]);

        unset($this->selectedImage, $this->favoriteIds);
        $this->dispatch('gallery-updated');

        Flux::toast(variant: 'success', text: $wasFavorite ? __('Remove favorite') : __('Favorite image'));
    }

    public function useAsPrompt(int $id): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $image = $this->visibleImage($id);

        if (! $image) {
            return;
        }

        $this->dispatch('use-prompt', prompt: $image->prompt);

        if (! $this->standalone) {
            $this->closeImage();
        }
    }

    public function editImage(int $id): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $image = GeneratedMedia::query()
            ->where('user_id', Auth::id())
            ->whereKey($id)
            ->first();

        if (! $image) {
            return;
        }

        $this->dispatch('edit-image', imageId: $image->id);

        if (! $this->standalone) {
            $this->closeImage();
        }
    }

    public function cancelPending(int $id, AiImageEditor $editor): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $image = GeneratedMedia::query()
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->whereKey($id)
            ->first();

        if (! $image || ! $editor->cancelPending($image)) {
            return;
        }

        unset($this->selectedImage);
        $this->dispatch('image-usage-updated');
        $this->dispatch('gallery-updated');
        Flux::toast(text: __('Image creation cancelled.'));
    }

    public function retryImage(int $id, AiImageEditor $editor): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $image = GeneratedMedia::query()
            ->where('user_id', Auth::id())
            ->whereKey($id)
            ->first();

        if (! $image || $image->status !== 'failed') {
            return;
        }

        try {
            $image = $editor->retryFailed($image, request());
        } catch (InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: __('Could not create an image right now. Please try again later.'));

            return;
        }

        try {
            CreateAiImage::dispatch($image->id, $image->user_id)->afterCommit();
        } catch (Throwable $e) {
            report($e);
            (new CreateAiImage($image->id, $image->user_id))->failed(
                new RuntimeException('Không thể đưa tác vụ tạo ảnh vào hàng đợi.'),
            );
            Flux::toast(variant: 'danger', text: __('Could not create an image right now. Please try again later.'));

            return;
        }

        unset($this->selectedImage);
        $this->dispatch('image-usage-updated');
        $this->dispatch('gallery-updated');
    }

    public function toggleFeatured(int $id): void
    {
        if (! $this->canManageFeatured()) {
            return;
        }

        $image = GeneratedMedia::query()
            ->publiclyVisible()
            ->whereKey($id)
            ->first();

        if (! $image) {
            return;
        }

        $image->update(['is_featured' => ! $image->is_featured]);

        unset($this->selectedImage);
        $this->dispatch('gallery-updated');

        Flux::toast(variant: 'success', text: $image->is_featured ? __('Image featured.') : __('Image unfeatured.'));
    }

    #[Computed]
    public function selectedImage(): ?GeneratedMedia
    {
        return $this->selectedImageId ? $this->visibleImage($this->selectedImageId) : null;
    }

    #[Computed]
    public function favoriteIds(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return MediaFavorite::query()
            ->where('user_id', (int) Auth::id())
            ->pluck('media_id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function referenceImageUrls(GeneratedMedia $image): array
    {
        if (! $this->canEdit($image) && ! $this->canManageFeatured()) {
            return [];
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return array_values(array_filter(
            array_map(
                fn (string $path): ?string => $disk->exists($path) ? $disk->url($path) : null,
                app(AiImageEditor::class)->referenceSourcePaths($image),
            ),
        ));
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

    public function isFavorite(GeneratedMedia $image): bool
    {
        return in_array($image->id, $this->favoriteIds, true);
    }

    public function favoriteCount(GeneratedMedia $image): int
    {
        return (int) ($image->favorites_count ?? 0);
    }

    public function canFavorite(GeneratedMedia $image): bool
    {
        return Auth::check() && $this->isPublicImage($image);
    }

    public function canEdit(GeneratedMedia $image): bool
    {
        return Auth::check() && $image->user_id === Auth::id();
    }

    public function canManageFeatured(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    public function statusLabel(GeneratedMedia $image): string
    {
        return match ($image->status) {
            'pending' => __('Creating'),
            'failed' => __('Failed'),
            default => $image->is_published ? __('Published') : __('Unpublished'),
        };
    }

    public function progressLabel(GeneratedMedia $image): string
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

    public function progressStep(GeneratedMedia $image): int
    {
        return match (data_get($image->request_meta, 'progress', 'queued')) {
            'reviewing' => 2,
            'generating' => 3,
            'saving' => 4,
            default => 1,
        };
    }

    /**
     * @return array{aspect_ratio: ?string, resolution: ?string, image_detail: ?string}
     */
    public function generationOptions(GeneratedMedia $image): array
    {
        $meta = is_array($image->request_meta) ? $image->request_meta : [];
        $aspectRatio = is_string($meta['aspect_ratio'] ?? null) ? $meta['aspect_ratio'] : null;
        $resolution = is_string($meta['resolution'] ?? null) ? strtoupper($meta['resolution']) : null;
        $imageDetail = is_string($meta['image_detail'] ?? null) ? $meta['image_detail'] : null;

        if (($aspectRatio === null || $resolution === null) && is_string($meta['size'] ?? null)) {
            $defaults = GptImageOptions::defaultsFromSettings($meta['size']);
            $aspectRatio ??= $defaults['aspect_ratio'];
            $resolution ??= strtoupper($defaults['resolution']);
        }

        return [
            'aspect_ratio' => $aspectRatio === 'auto' ? __('Auto') : $aspectRatio,
            'resolution' => $resolution,
            'image_detail' => match ($imageDetail) {
                'auto' => __('Automatic'),
                'low' => __('Fair'),
                'high' => __('Good'),
                'original' => __('High'),
                default => null,
            },
        ];
    }

    protected function getListeners(): array
    {
        $userId = Auth::id();

        return $userId ? ['echo-private:App.Models.User.'.$userId.',AiImageCompleted' => 'refreshCompletedImage'] : [];
    }

    private function latestCreatedImage(): ?GeneratedMedia
    {
        return app(AiImageEditor::class)->guestHistory(request(), 1)->first();
    }

    private function visibleImage(int $id): ?GeneratedMedia
    {
        $user = Auth::user();
        $query = GeneratedMedia::query()->with(['category', 'user', 'tags'])->whereKey($id);

        if ($user instanceof User && $user->isAdmin()) {
            return $query->first();
        }

        return $query
            ->where(function ($query) use ($user): void {
                $query->where(fn ($query) => $query->publiclyVisible());

                if ($user instanceof User) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->first();
    }

    private function publicImage(int $id): ?GeneratedMedia
    {
        return GeneratedMedia::query()
            ->publiclyVisible()
            ->whereKey($id)
            ->first();
    }

    private function isPublicImage(GeneratedMedia $image): bool
    {
        return $image->is_published && $image->status === 'succeeded' && filled($image->result_path);
    }
}; ?>

<div class="contents" x-data="{
    previousUrl: null,
    previousTitle: null,
    loading: false,
    loadingImageId: null,
    preview: null,
    siteName: @js(\App\Support\AppSettings::string('site.name', config('app.name', 'GenAnh'))),
    openImage(id, url, title, preview = null) {
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

        this.preview = preview;
        this.loadingImageId = id;
        this.loading = Boolean(preview);
        $wire.openImage(id).catch(() => {
            if (this.loadingImageId === id) this.finishLoading();
        });
    },
    finishLoading() {
        const finishedPreview = this.preview;

        this.loading = false;
        this.loadingImageId = null;
        setTimeout(() => {
            if (this.preview === finishedPreview) this.preview = null;
        }, 200);
    },
    finishLoadingImage(image, id) {
        if (this.loadingImageId !== id) return;

        if (typeof image.decode !== 'function') {
            this.finishLoading();

            return;
        }

        image.decode().catch(() => {}).finally(() => {
            if (this.loadingImageId === id) this.finishLoading();
        });
    },
    fallbackToPreview(image, id) {
        if (this.loadingImageId !== id) return;

        const previewUrl = this.preview ? new URL(this.preview, window.location.href).href : null;

        if (previewUrl && image.src !== previewUrl) {
            image.src = previewUrl;

            return;
        }

        this.finishLoading();
    },
    closeImage() {
        if (this.previousUrl) {
            history.replaceState(null, '', this.previousUrl);
            document.title = this.previousTitle;
            this.previousUrl = null;
            this.previousTitle = null;
        }

        this.loading = false;
        this.loadingImageId = null;
        this.preview = null;
        $wire.closeImage();
    },
}" x-on:keydown.escape.window="if (!document.querySelector('.lightbox3-overlay')) { {{ $standalone ? "window.location.href = '" . route('home') . "'" : 'closeImage()' }} }" @if (!$standalone) x-on:open-image-detail.window="openImage($event.detail.id, $event.detail.url, $event.detail.title, $event.detail.preview)" @endif>
    @if (!$standalone)
        <div x-show="loading" x-cloak class="fixed inset-0 z-60 flex flex-col overflow-hidden bg-zinc-100/90 text-zinc-950 backdrop-blur dark:bg-zinc-950/80 dark:text-white md:grid md:grid-cols-[1fr_480px] md:grid-rows-[minmax(0,1fr)]" role="dialog" x-transition:leave="transition-opacity ease-out duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" aria-modal="true" aria-label="{{ __('Image details') }}">
            <div class="relative flex min-h-0 items-start justify-center pt-16 sm:p-4 sm:pt-16 md:items-center md:p-20">
                <img x-show="preview" x-bind:src="preview" alt="" class="block h-auto max-h-[62svh] max-w-full rounded-xl object-contain md:max-h-[calc(100svh-10rem)] md:rounded-2xl" decoding="sync">
            </div>
            <aside class="hidden border-l border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-950 md:block"></aside>
        </div>
    @endif

    @php($selected = $this->selectedImage())

    @if ($show && $selected)
    @php($selectedUrl = $this->imageUrl($selected))
    @php($selectedThumbUrl = $this->imageUrl($selected, 'md'))
    @php($selectedImageSize = $this->imageSize($selected, 'md'))
    @php($selectedOriginalSize = $this->imageSize($selected))
    @php($progressStep = $this->progressStep($selected))
    @php($selectedTitle = $selected->title ?: $selected->prompt)
    @php($canViewFullPrompt = Auth::check())
    @php($visiblePrompt = $canViewFullPrompt ? $selected->prompt : Str::limit($selected->prompt, 160))
    @php($generationOptions = $this->generationOptions($selected))

    @if (!$selectedThumbUrl && !$standalone)
        <div x-init="finishLoading()"></div>
    @endif

    <div class="{{ $standalone ? 'h-dvh' : 'fixed inset-0 z-50' }} flex flex-col overflow-y-auto bg-zinc-100/90 text-zinc-950 dark:bg-zinc-950/80 dark:text-white md:grid md:backdrop-blur md:grid-cols-[1fr_480px] md:grid-rows-[minmax(0,1fr)] md:overflow-hidden" @if (!$standalone) role="dialog" aria-modal="true" aria-label="{{ __('Image details') }}" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[.985]" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @endif wire:key="image-detail-{{ $selected->id }}" @if ($selected->status === 'pending') wire:poll.2s @endif>
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
                            <a class="relative grid max-h-[62svh] max-w-full cursor-zoom-in items-center justify-center overflow-hidden rounded-xl md:max-h-[calc(100svh-10rem)] md:rounded-2xl" href="{{ $selectedUrl }}" data-lightbox @if ($selectedOriginalSize) data-width="{{ $selectedOriginalSize['width'] }}" data-height="{{ $selectedOriginalSize['height'] }}" @endif aria-label="{{ __('View original image') }}">
                                <img class="col-start-1 row-start-1 block h-auto max-h-[62svh] max-w-full rounded-xl object-contain md:max-h-[calc(100svh-10rem)] md:rounded-2xl" src="{{ $selectedThumbUrl }}" alt="{{ Str::limit($selectedTitle, 80) }}" @if ($selectedImageSize) width="{{ $selectedImageSize['width'] }}" height="{{ $selectedImageSize['height'] }}" @endif decoding="async" @if (!$standalone) x-init="$nextTick(() => { if ($el.complete) finishLoadingImage($el, {{ $selected->id }}) })" x-on:load="finishLoadingImage($el, {{ $selected->id }})" x-on:error="fallbackToPreview($el, {{ $selected->id }})" @endif />
                            </a>
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
                                @if ($this->canEdit($selected))
                                    <flux:button class="w-full" type="button" size="sm" variant="danger" icon="stop" wire:click="cancelPending({{ $selected->id }})" wire:confirm="{{ __('Cancel image creation?') }}" wire:loading.attr="disabled" wire:target="cancelPending">{{ __('Stop') }}</flux:button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="flex aspect-square w-full max-w-md items-center justify-center rounded-4xl bg-red-50 text-center text-red-600 shadow-inner dark:bg-red-400/10 dark:text-red-200">
                            <div class="max-w-xs p-8">
                                <x-iconsax-two-danger class="mx-auto mb-4 size-12" />
                                <div class="text-lg font-semibold">{{ __('Failed') }}</div>
                                <div class="mt-2 text-sm">{{ $selected->displayError() }}</div>
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
                    @if (filled($selected->description))
                        <p class="w-full text-sm text-zinc-500 dark:text-zinc-400">{{ $selected->description }}</p>
                    @endif
                    @if ($selected->is_featured)
                    <div class="mb-5 flex flex-wrap gap-1">
                        <flux:badge size="sm" color="amber">{{ __('Featured') }}</flux:badge>
                    </div>
                @endif

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

                    @if (collect($generationOptions)->filter()->isNotEmpty())
                        <div x-show="expanded" x-cloak>
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('Image settings') }}</div>
                            <div class="flex flex-wrap gap-2">
                                @if ($generationOptions['aspect_ratio'])
                                    <flux:badge size="sm" color="zinc" rounded>{{ $generationOptions['aspect_ratio'] }}</flux:badge>
                                @endif
                                @if ($generationOptions['resolution'])
                                    <flux:badge size="sm" color="zinc" rounded>{{ $generationOptions['resolution'] }}</flux:badge>
                                @endif
                                @if ($generationOptions['image_detail'])
                                    <flux:badge size="sm" color="zinc" rounded>{{ __('Quality') }}: {{ $generationOptions['image_detail'] }}</flux:badge>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($selected->status === 'failed')
                        <div class="rounded-2xl bg-red-50 p-4 text-sm text-red-700 dark:bg-red-400/10 dark:text-red-100">{{ $selected->displayError() }}</div>
                    @endif
                </div>

                @php($referenceImageUrls = $this->referenceImageUrls($selected))
                @if ($referenceImageUrls !== [])
                    <div class="mt-7">
                        <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('Reference images') }}</div>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($referenceImageUrls as $refUrl)
                                <a class="overflow-hidden rounded-2xl bg-zinc-100 dark:bg-white/10" href="{{ $refUrl }}" data-lightbox wire:key="reference-image-{{ $selected->id }}-{{ $loop->index }}" aria-label="{{ __('Reference image :number', ['number' => $loop->iteration]) }}">
                                    <img class="aspect-square size-full object-cover" src="{{ $refUrl }}" alt="{{ __('Reference image :number', ['number' => $loop->iteration]) }}" loading="lazy" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($this->isPublicImage($selected))
                    <livewire:gallery.similar-images :image-id="$selected->id" :standalone="$standalone" lazy :key="'similar-images-'.$selected->id" />
                @endif

                @if ($selected->category || $selected->tags->isNotEmpty())
                    <div class="mt-7 flex flex-wrap gap-1">
                        @if ($selected->category)
                            <flux:button :href="route('categories.show', $selected->category)" size="xs" variant="ghost" wire:navigate>{{ $selected->category->name }}</flux:button>
                        @endif
                        @foreach ($selected->tags as $tag)
                            <flux:button :href="route('tags.show', $tag)" size="xs" variant="ghost" wire:navigate wire:key="image-detail-tag-{{ $tag->id }}">
                                #{{ $tag->name }}
                            </flux:button>
                        @endforeach
                    </div>
                @endif
            </div>

            @php($isFailed = $selected->status === 'failed')
            <footer class="sticky bottom-0 z-10 shrink-0 border-t border-zinc-200 bg-white p-2 dark:border-white/10 dark:bg-zinc-950 md:static">
                <div class="grid gap-2 {{ $this->canEdit($selected) && $selectedUrl ? 'grid-cols-[auto_minmax(0,1fr)_auto]' : 'grid-cols-2' }}">
                    @if ($selectedUrl)
                        <flux:button :href="route('images.download', $selected)" x-on:click.prevent="downloadImage($event.currentTarget.href, $event.currentTarget)" data-download-error="{{ __('Could not download image.') }}">
                            <x-slot name="icon">
                                <x-iconsax-two-document-download class="size-5" data-download-idle />
                                <flux:icon.loading class="hidden size-5" data-download-loading />
                            </x-slot>
                            {{ __('Download') }}
                        </flux:button>
                    @endif

                    @if ($isFailed && $this->canEdit($selected))
                        <flux:button type="button" variant="primary" icon="arrow-path" wire:click="retryImage({{ $selected->id }})">{{ __('Retry') }}</flux:button>
                    @elseif (!$isFailed)
                        @auth
                            <flux:button type="button" variant="primary" wire:click="useAsPrompt({{ $selected->id }})">
                                <x-slot name="icon"><x-iconsax-two-magic-star class="size-5" /></x-slot>
                                {{ __('Create similar image') }}
                            </flux:button>
                        @else
                            <flux:button type="button" variant="primary" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">
                                <x-slot name="icon"><x-iconsax-two-magic-star class="size-5" /></x-slot>
                                {{ __('Create similar image') }}
                            </flux:button>
                        @endauth
                    @endif

                    @if ($this->canEdit($selected))
                        <flux:button type="button" variant="filled" wire:click="editImage({{ $selected->id }})">{{ __('Edit image') }}</flux:button>
                    @endif
                </div>
            </footer>
        </aside>
    </div>
    @endif
</div>