<?php

use App\Jobs\CreateAiImage;
use App\Models\GeneratedMedia;
use App\Services\CreatorImageService;
use App\Support\AppSettings;
use App\Support\GptImageOptions;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $showComposer = false;

    public array $photos = [];

    public array $referenceImageIds = [];

    public array $parentReferenceIndexes = [];

    public mixed $newPhotos = [];

    public mixed $promptSourcePhoto = null;

    public string $prompt = '';

    public ?int $parentId = null;

    public string $parentPrompt = '';

    public string $rewriteInstruction = '';

    public ?int $resultId = null;

    public ?string $errorMessage = null;

    public ?string $publishMessage = null;

    public string $aspectRatio = 'auto';

    public string $resolution = '1k';

    public string $imageDetail = 'high';

    public string $imageModel = '';

    public function mount(): void
    {
        $defaults = GptImageOptions::defaultsFromSettings();
        $this->aspectRatio = $defaults['aspect_ratio'];
        $this->resolution = $defaults['resolution'];
        $this->imageDetail = GptImageOptions::defaultImageDetail();
        $this->imageModel = AppSettings::defaultImageModel();

        if (request()->boolean('composer')) {
            $this->openComposer();
        }
    }

    public function openComposer(): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $this->showComposer = true;
    }

    public function closeComposer(): void
    {
        $this->showComposer = false;
    }

    #[On('use-prompt')]
    public function usePrompt(string $prompt): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $this->reset('photos', 'referenceImageIds', 'parentReferenceIndexes', 'newPhotos', 'parentId', 'parentPrompt', 'rewriteInstruction', 'resultId', 'errorMessage', 'publishMessage');
        $this->prompt = $prompt;
        $this->resetValidation();
        unset($this->parentReferenceImages);
        $this->showComposer = true;
    }

    #[On('edit-image')]
    public function editImage(int $imageId, CreatorImageService $editor): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $image = GeneratedMedia::query()
            ->where('user_id', Auth::id())
            ->whereKey($imageId)
            ->first();

        if (! $image) {
            return;
        }

        $this->reset('photos', 'referenceImageIds', 'parentReferenceIndexes', 'newPhotos', 'prompt', 'parentId', 'parentPrompt', 'rewriteInstruction', 'resultId', 'errorMessage', 'publishMessage');
        $this->parentId = $image->id;
        $this->parentPrompt = $image->prompt;
        $this->parentReferenceIndexes = $this->availableReferenceIndexes($editor, $image);
        $this->resetValidation();
        unset($this->parentReferenceImages);
        $this->showComposer = true;
    }

    /**
     * @return array<int, int>
     */
    private function availableReferenceIndexes(CreatorImageService $editor, ?GeneratedMedia $source): array
    {
        if (! $source instanceof GeneratedMedia) {
            return [];
        }

        return array_slice(array_keys(array_filter(
            $editor->referenceSourcePaths($source),
            fn (string $path): bool => Storage::disk('public')->exists($path),
        )), 0, $this->maxReferencePhotos());
    }

    public function updatedNewPhotos(): void
    {
        $newPhotos = is_array($this->newPhotos) ? $this->newPhotos : [$this->newPhotos];
        $this->photos = array_slice([...$this->photos, ...$newPhotos], 0, max(0, $this->maxReferencePhotos() - count($this->referenceImageIds) - count($this->parentReferenceIndexes)));
        $this->newPhotos = [];
        $this->resultId = null;
        $this->errorMessage = null;
        $this->publishMessage = null;
        $this->resetValidation(['photos', 'photos.*', 'newPhotos', 'newPhotos.*']);
    }

    public function updatedPromptSourcePhoto(): void
    {
        if (! AppSettings::bool('ai.image_to_prompt_enabled', true)) {
            $this->promptSourcePhoto = null;

            return;
        }

        if (! Auth::check()) {
            $this->promptSourcePhoto = null;
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $this->validateOnly('promptSourcePhoto', [
            'promptSourcePhoto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::imageUploadMaxKb()],
        ]);

        $this->dispatch('prompt-source-uploaded');
    }

    public function analyzePromptSourcePhoto(CreatorImageService $editor): void
    {
        if (! AppSettings::bool('ai.image_to_prompt_enabled', true) || ! Auth::check() || ! $this->promptSourcePhoto) {
            return;
        }

        try {
            $this->prompt = $editor->promptFromImage($this->promptSourcePhoto);
            $this->errorMessage = null;
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            report($e);

            $this->errorMessage = __('Could not create a prompt from this image right now. Please try again later.');
        } finally {
            $this->promptSourcePhoto = null;
        }
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
        $this->resetValidation(['photos', 'photos.*']);
    }

    public function removeReferenceImage(int $id): void
    {
        $this->referenceImageIds = array_values(array_filter($this->referenceImageIds, fn (int $imageId) => $imageId !== $id));
    }

    public function removeParentReference(int $index): void
    {
        $this->parentReferenceIndexes = array_values(array_filter($this->parentReferenceIndexes, fn (int $referenceIndex) => $referenceIndex !== $index));
        unset($this->parentReferenceImages);
    }

    public function maxReferencePhotos(): int
    {
        return AppSettings::maxReferencePhotos();
    }

    /**
     * @return array<int, mixed>
     */
    private function promptRules(): array
    {
        return AppSettings::promptRules(__('Prompt must not exceed 1200 words.'));
    }

    public function createImage(CreatorImageService $editor): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        if ($editor->requiresEmailVerificationForImageCreation()) {
            session()->flash('status', 'image-creation-requires-verification');
            $this->redirectRoute('verification.notice', navigate: true);

            return;
        }

        $this->errorMessage = null;
        $this->publishMessage = null;

        $this->validate([
            'prompt' => $this->promptRules(),
            'aspectRatio' => ['required', 'string', 'in:'.implode(',', GptImageOptions::ASPECT_RATIOS)],
            'resolution' => ['required', 'string', 'in:'.implode(',', GptImageOptions::RESOLUTIONS)],
            'imageDetail' => ['required', 'string', 'in:'.implode(',', GptImageOptions::IMAGE_DETAILS)],
            'imageModel' => ['required', 'string', 'max:120', Rule::in(AppSettings::enabledImageModels())],
            'referenceImageIds' => ['array', 'max:'.$this->maxReferencePhotos()],
            'referenceImageIds.*' => ['integer'],
            'parentId' => ['nullable', 'integer'],
            'parentReferenceIndexes' => ['array', 'max:'.$this->maxReferencePhotos()],
            'parentReferenceIndexes.*' => ['integer', 'min:0', 'max:'.($this->maxReferencePhotos() - 1)],
            'photos' => ['array', 'max:'.max(0, $this->maxReferencePhotos() - count($this->referenceImageIds) - count($this->parentReferenceIndexes))],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::imageUploadMaxKb()],
        ]);

        if ($editor->isLimitExceeded(request())) {
            $this->errorMessage = __('You have used all image generations for today.');

            return;
        }

        try {
            $image = $editor->createPending(
                request(),
                $this->photos,
                $this->prompt,
                $this->referenceImageIds,
                $this->parentId,
                $this->parentReferenceIndexes,
                GptImageOptions::size($this->aspectRatio, $this->resolution),
                $this->imageDetail,
                $this->aspectRatio,
                $this->resolution,
                $this->imageModel,
                ['generation_mode' => 'creator', 'source' => 'web'],
            );
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (Throwable $e) {
            report($e);
            $this->errorMessage = __('Could not create an image right now. Please try again later.');

            return;
        }

        try {
            CreateAiImage::dispatch($image->id, $image->user_id)->afterCommit();
        } catch (Throwable $e) {
            report($e);
            (new CreateAiImage($image->id, $image->user_id))->failed(
                new RuntimeException('Không thể đưa tác vụ tạo ảnh vào hàng đợi.'),
            );
            $this->errorMessage = __('Could not create an image right now. Please try again later.');

            return;
        }

        $this->showComposer = false;
        $this->resultId = null;
        unset($this->remainingToday, $this->resultImage);
        $this->dispatch('image-usage-updated');
        $this->redirectRoute('history.index', ['image' => $image->id], navigate: true);
    }

    public function rewritePrompt(CreatorImageService $editor): void
    {
        if (! AppSettings::bool('ai.prompt_rewrite_enabled', true)) {
            return;
        }

        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $this->validate([
            'prompt' => array_replace($this->promptRules(), ['nullable']),
            'rewriteInstruction' => ['required_without:prompt', 'nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->prompt = $editor->rewritePrompt($this->prompt, $this->rewriteInstruction);
            $this->rewriteInstruction = '';
            $this->errorMessage = null;
            $this->dispatch('prompt-rewritten');
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            report($e);

            $this->errorMessage = __('Could not rewrite the prompt right now. Please try again later.');
        }
    }

    public function translatePrompt(CreatorImageService $editor): void
    {
        if (! AppSettings::bool('ai.prompt_translation_enabled', true)) {
            return;
        }

        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $this->validate(['prompt' => $this->promptRules()]);

        try {
            $this->prompt = $editor->translatePrompt($this->prompt);
            $this->errorMessage = null;
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            report($e);

            $this->errorMessage = __('Could not translate the prompt right now. Please try again later.');
        }
    }

    public function publishResult(CreatorImageService $editor): void
    {
        $this->errorMessage = null;
        $this->publishMessage = null;

        if (! $this->resultImage) {
            return;
        }

        try {
            $image = $editor->publish($this->resultImage, request());
            $this->publishMessage = __('Published to :category.', ['category' => $image->category?->name ?? __('Other')]);
            unset($this->resultImage);
            $this->dispatch('gallery-updated');
            $this->dispatch('image-usage-updated');
        } catch (InvalidArgumentException $e) {
            unset($this->resultImage);
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            report($e);

            $this->errorMessage = __('Could not publish the image right now.');
        }
    }

    public function createNew(): void
    {
        $this->reset('photos', 'referenceImageIds', 'parentReferenceIndexes', 'newPhotos', 'prompt', 'parentId', 'parentPrompt', 'rewriteInstruction', 'resultId', 'errorMessage', 'publishMessage');
        $this->resetValidation();
        unset($this->parentReferenceImages);
        $this->showComposer = true;
    }

    #[On('image-deleted')]
    public function clearDeletedResult(int $id): void
    {
        if ($this->resultId !== $id) {
            return;
        }

        $this->resultId = null;
    }

    #[Computed]
    public function remainingToday(): ?int
    {
        return app(CreatorImageService::class)->remainingToday(request());
    }

    #[Computed]
    public function referenceImages()
    {
        if ($this->referenceImageIds === []) {
            return collect();
        }

        return GeneratedMedia::query()
            ->whereIn('id', $this->referenceImageIds)
            ->publiclyVisible()
            ->get()
            ->sortBy(fn (GeneratedMedia $image) => array_search($image->id, $this->referenceImageIds, true));
    }

    #[Computed]
    public function parentReferenceImages(): array
    {
        if (! $this->parentId || $this->parentReferenceIndexes === []) {
            return [];
        }

        $image = GeneratedMedia::query()
            ->where('user_id', Auth::id())
            ->whereKey($this->parentId)
            ->first();

        if (! $image) {
            return [];
        }

        $paths = app(CreatorImageService::class)->referenceSourcePaths($image);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return collect($this->parentReferenceIndexes)
            ->filter(fn (int $index): bool => isset($paths[$index]) && $disk->exists($paths[$index]))
            ->mapWithKeys(fn (int $index): array => [$index => $disk->url($paths[$index])])
            ->all();
    }

    #[Computed]
    public function resultImage(): ?GeneratedMedia
    {
        return $this->resultId ? GeneratedMedia::with('category')->find($this->resultId) : null;
    }

    public function imageUrl(GeneratedMedia $image, string $size = 'original'): ?string
    {
        return app(CreatorImageService::class)->imageUrl($image, $size);
    }

    public function imageSize(GeneratedMedia $image, string $size = 'original'): ?array
    {
        return app(CreatorImageService::class)->imageSize($image, $size);
    }
}; ?>

@php
    $resultImage = $this->resultImage;
    $resultUrl = $resultImage ? $this->imageUrl($resultImage) : null;
    $resultThumbUrl = $resultImage ? $this->imageUrl($resultImage, 'md') : null;
    $resultImageSize = $resultImage ? $this->imageSize($resultImage, 'md') : null;
    $resultDownloadUrl = $resultImage ? route('images.download', $resultImage) : null;
    $maxReferencePhotos = $this->maxReferencePhotos();
    $referenceImages = $this->referenceImages;
    $parentReferenceImages = $this->parentReferenceImages;
    $referenceCount = count($photos) + $referenceImages->count() + count($parentReferenceImages);
@endphp

<div class="contents" x-data x-on:open-image-composer.window="$wire.openComposer()">
    <flux:modal name="image-composer" flyout class="flex w-full max-w-none flex-col p-0! overflow-hidden! md:w-[400px]" wire:model.self="showComposer" @close="closeComposer">
        <div class="flex min-h-0 flex-1 flex-col">
            <div class="shrink-0 space-y-1 border-b border-zinc-200 p-6 pe-14 dark:border-white/10">
                <flux:heading size="xl">{{ __('Create image') }}</flux:heading>
            </div>

            @if ($resultUrl)
            <div class="flex min-h-0 flex-1 flex-col">
                <div class="flex-1 space-y-4 overflow-y-auto p-4">
                    @if ($errorMessage)
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-100">{{ $errorMessage }}</div>
                    @endif

                    @if ($publishMessage)
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-100">{{ $publishMessage }}</div>
                    @endif

                    <button class="block w-full" type="button" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $resultImage->id }}, preview: @js($resultThumbUrl) })" aria-label="{{ __('View image details') }}">
                        <img class="max-h-[58svh] w-full rounded-[1.75rem] bg-zinc-100 object-contain shadow-inner" src="{{ $resultThumbUrl }}" alt="{{ __('AI-generated image') }}" @if ($resultImageSize) width="{{ $resultImageSize['width'] }}" height="{{ $resultImageSize['height'] }}" @endif />
                    </button>

                    <div class="rounded-3xl bg-zinc-50 p-4">
                        <p class="text-sm font-medium text-emerald-700">{{ __('Image created successfully.') }}</p>
                        @if ($resultImage?->is_published)
                            <p class="mt-1 text-sm text-zinc-500">{{ __('Published in :category.', ['category' => $resultImage->category?->name ?? __('Other')]) }}</p>
                        @else
                            <p class="mt-1 text-sm text-zinc-500">{{ __('Publish to make the image appear in the community gallery.') }}</p>
                        @endif
                    </div>
                </div>

                <div class="shrink-0 border-t border-zinc-200 p-4 dark:border-white/10">
                    <div class="grid grid-cols-2 gap-2">
                        <flux:button :href="$resultDownloadUrl" x-on:click.prevent="downloadImage($event.currentTarget.href, $event.currentTarget)" data-download-error="{{ __('Could not download image.') }}">
                            <x-slot name="icon">
                                <flux:icon.arrow-down-tray class="size-5" data-download-idle />
                                <flux:icon.loading class="hidden size-5" data-download-loading />
                            </x-slot>
                            {{ __('Download') }}
                        </flux:button>
                        <flux:button type="button" variant="outline" wire:click="createNew">{{ __('Create new image') }}</flux:button>
                        @php($publishError = is_string(data_get($resultImage?->response_meta, 'publish_error')) ? data_get($resultImage?->response_meta, 'publish_error') : null)
                        @php($publishDisabled = $publishError && !auth()->user()?->isAdmin())
                                        @if ($publishDisabled)
                                            <flux:tooltip class="col-span-2" :content="$publishError">
                                                <div>
                                                    <flux:button class="w-full" type="button" variant="primary" disabled>{{ __('Publish image') }}</flux:button>
                                                </div>
                                            </flux:tooltip>
                                        @else
                                            <flux:button class="col-span-2" type="button" variant="primary" wire:click="publishResult" :disabled="$resultImage?->is_published" wire:loading.attr="disabled" wire:target="publishResult">
                                                {{ $resultImage?->is_published ? __('Published') : __('Publish image') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
            <form class="flex min-h-0 flex-1 flex-col" wire:submit="createImage">
                <div class="flex-1 space-y-4 overflow-y-auto p-3">
                    @if ($errorMessage)
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-100">{{ $errorMessage }}</div>
                    @endif

                    @if ($publishMessage)
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-100">{{ $publishMessage }}</div>
                    @endif

                    @php($isMultiple = $maxReferencePhotos > 1)
                    <div>
                        <x-image-upload-grid model="newPhotos" :count="$referenceCount" :limit="$maxReferencePhotos" :heading="__('Reference images')" :add-label="__('Add image')" :multiple="$isMultiple">
                            @foreach ($parentReferenceImages as $index => $url)
                                <div class="group relative overflow-hidden rounded-2xl bg-zinc-200">
                                    <img class="aspect-square size-full object-cover" src="{{ $url }}" alt="{{ __('Reference image :number', ['number' => $loop->iteration]) }}" />
                                    <div class="absolute top-1 right-1 z-10">
                                        <flux:button type="button" size="xs" variant="filled" icon="x-mark" wire:click="removeParentReference({{ $index }})" wire:loading.remove wire:target="createImage" :aria-label="__('Remove reference image :number', ['number' => $loop->iteration])" />
                                    </div>
                                </div>
                            @endforeach
                            @foreach ($referenceImages as $image)
                            @php($url = $this->imageUrl($image, 'xs'))
                            @php($imageSize = $this->imageSize($image, 'xs'))
                            @if ($url)
                                <div class="group relative overflow-hidden rounded-2xl bg-zinc-200">
                                    <img class="aspect-square size-full object-cover" src="{{ $url }}" alt="{{ __('Reference image :number', ['number' => $loop->iteration]) }}" @if ($imageSize) width="{{ $imageSize['width'] }}" height="{{ $imageSize['height'] }}" @endif />
                                    <div class="absolute top-1 right-1 z-10">
                                        <flux:button type="button" size="xs" variant="filled" icon="x-mark" wire:click="removeReferenceImage({{ $image->id }})" wire:loading.remove wire:target="createImage" :aria-label="__('Remove reference image :number', ['number' => $loop->iteration])" />
                                    </div>
                                </div>
                            @endif
                            @endforeach
                            @foreach ($photos as $index => $item)
                                <div class="group relative overflow-hidden rounded-2xl bg-zinc-200 dark:bg-white/10">
                                    <img class="aspect-square size-full object-cover" src="{{ $item->temporaryUrl() }}" alt="{{ __('Reference image :number', ['number' => $referenceImages->count() + $index + 1]) }}" />
                                    <div class="absolute top-1 right-1 z-10">
                                        <flux:button type="button" size="xs" variant="filled" icon="x-mark" wire:click="removePhoto({{ $index }})" wire:loading.remove wire:target="createImage" :aria-label="__('Remove reference image :number', ['number' => $referenceImages->count() + $index + 1])" />
                                    </div>
                                </div>
                            @endforeach
                        </x-image-upload-grid>
                        <div class="mt-2 text-sm text-zinc-500" wire:loading wire:target="newPhotos">{{ __('Uploading image...') }}</div>
                    </div>

                    @if ($parentId && filled($parentPrompt))
                        <div class="space-y-2 rounded-2xl bg-zinc-100 p-4 dark:bg-white/10">
                            <flux:heading size="sm">{{ __('Original prompt') }}</flux:heading>
                            <p class="max-h-40 overflow-y-auto whitespace-pre-wrap text-sm leading-6 text-zinc-700 dark:text-zinc-200">{{ $parentPrompt }}</p>
                        </div>
                    @endif

                    <div x-data="{
							pickerOpen: false,
							openPicker() {
								this.pickerOpen = true
								this.$nextTick(() => this.$refs.picker?.open?.())
							},
							closePicker() {
								this.pickerOpen = false
								this.$refs.picker?.close?.()
							},
							handlePromptInput(event) {
								const textarea = event.target
								const pos = textarea.selectionStart
								const char = textarea.value.charAt(pos - 1)
								const before = textarea.value.charAt(pos - 2)

								if (char === '#' && (before === ' ' || before === '')) {
									this.openPicker()
								}
							},
							insertColor(event) {
								const value = event.target.closest('ui-color-picker')?.getValue?.()
								const textarea = this.$refs.prompt

								if (! value || ! textarea) return

								const pos = textarea.selectionStart ?? textarea.value.length
								const before = textarea.value.slice(0, pos).replace(/#$/, '')
								const after = textarea.value.slice(pos)
								const next = before + value + after

								textarea.value = next
								const insertPos = (before + value).length
								textarea.dispatchEvent(new Event('input', { bubbles: true }))
								this.closePicker()

								const restoreCursor = () => {
									textarea.focus()
									textarea.setSelectionRange(insertPos, insertPos)
								}

								requestAnimationFrame(restoreCursor)
								setTimeout(restoreCursor, 350)
							},
						}" x-on:prompt-rewritten.window="$refs.rewriteDropdown?.lastElementChild?.hidePopover?.()">
                        <div class="space-y-2" x-data="{ uploadingPromptImage: false }" x-on:livewire-upload-start="uploadingPromptImage = true" x-on:livewire-upload-finish="uploadingPromptImage = false" x-on:livewire-upload-error="uploadingPromptImage = false" x-on:livewire-upload-cancel="uploadingPromptImage = false" x-on:prompt-source-uploaded.window="$wire.analyzePromptSourcePhoto()">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-zinc-800 dark:text-white">{{ __('Prompt') }}</span>
                                <div class="flex items-center gap-2">
                                    @if (AppSettings::bool('ai.prompt_translation_enabled', true))
                                        <flux:button type="button" size="sm" variant="ghost" :disabled="blank($prompt)" :loading="false" wire:click="translatePrompt" wire:loading.attr="disabled" wire:target="translatePrompt" tooltip="{{ __('Translate prompt to Vietnamese') }}" tooltip:position="top" :aria-label="__('Translate prompt to Vietnamese')">
                                            <x-slot name="icon">
                                                <flux:icon.language class="size-5" wire:loading.remove wire:target="translatePrompt" />
                                                <flux:icon.loading class="size-5" wire:loading wire:target="translatePrompt" />
                                            </x-slot>
                                        </flux:button>
                                    @endif
                                    @if (AppSettings::bool('ai.image_to_prompt_enabled', true))
                                        <flux:file-upload wire:model="promptSourcePhoto" accept="image/jpeg,image/png,image/webp,image/avif">
                                            <flux:button type="button" size="sm" variant="ghost" :loading="false" x-bind:disabled="uploadingPromptImage" wire:loading.attr="disabled" wire:target="promptSourcePhoto,analyzePromptSourcePhoto" tooltip="{{ __('Image to prompt') }}" tooltip:position="top" :aria-label="__('Image to prompt')">
                                                <x-slot name="icon">
                                                    <flux:icon.photo class="size-5" x-show="! uploadingPromptImage" wire:loading.remove wire:target="promptSourcePhoto,analyzePromptSourcePhoto" />
                                                    <flux:icon.loading class="size-5" x-show="uploadingPromptImage" x-cloak />
                                                    <flux:icon.loading class="size-5" x-show="! uploadingPromptImage" wire:loading wire:target="promptSourcePhoto,analyzePromptSourcePhoto" x-cloak />
                                                </x-slot>
                                            </flux:button>
                                        </flux:file-upload>
                                    @endif
                                    @if (AppSettings::bool('ai.prompt_rewrite_enabled', true))
                                        <flux:dropdown x-ref="rewriteDropdown" position="bottom" align="end">
                                            <flux:button type="button" size="sm" variant="ghost" tooltip="{{ __('Rewrite prompt') }}" tooltip:position="top" :aria-label="__('Rewrite prompt')">
                                                <x-slot name="icon"><x-iconsax-two-magic-star class="size-5" /></x-slot>
                                            </flux:button>
                                            <flux:popover class="w-80 space-y-3">
                                                <div>
                                                    <flux:heading size="sm">{{ __('Rewrite prompt') }}</flux:heading>
                                                    <flux:text variant="subtle">{{ __('Tell AI how to rewrite your current prompt.') }}</flux:text>
                                                </div>
                                                <flux:textarea wire:model="rewriteInstruction" rows="4" resize="vertical" :label="__('Rewrite instruction')" :placeholder="__('e.g. Make it more cinematic, add product lighting, keep the same subject...')" />
                                                <flux:button class="w-full" type="button" size="sm" variant="primary" color="emerald" wire:click="rewritePrompt" wire:loading.attr="disabled" wire:target="rewritePrompt">
                                                    <span wire:loading.remove wire:target="rewritePrompt">{{ __('Rewrite prompt') }}</span>
                                                    <span wire:loading wire:target="rewritePrompt">{{ __('Rewriting prompt...') }}</span>
                                                </flux:button>
                                            </flux:popover>
                                        </flux:dropdown>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" role="status" aria-live="polite" x-show="uploadingPromptImage" x-cloak>
                                <flux:icon.loading class="size-4" />
                                <span>{{ __('Uploading image...') }}</span>
                            </div>
                            <div class="items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" role="status" aria-live="polite" x-show="! uploadingPromptImage" wire:loading.flex wire:target="analyzePromptSourcePhoto" x-cloak>
                                <flux:icon.loading class="size-4" />
                                <span>{{ __('Analyzing image...') }}</span>
                            </div>
                            <div class="items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" role="status" aria-live="polite" wire:loading.flex wire:target="translatePrompt">
                                <flux:icon.loading class="size-4" />
                                <span>{{ __('Translating prompt...') }}</span>
                            </div>
                            <flux:textarea class="max-h-[400px] overflow-y-auto [&_textarea]:max-h-[400px] [&_textarea]:overflow-y-auto" wire:model.live.debounce.300ms="prompt" rows="auto" :placeholder="__('Describe the image you want to create...')" x-ref="prompt" x-on:input="handlePromptInput($event)" required />
                        </div>

                        <div class="relative">
                            <div class="absolute bottom-2 inset-s-2 z-10" x-show="pickerOpen" x-cloak x-transition @keydown.escape="closePicker()">
                                <flux:color-picker type="button" size="xs" x-ref="picker" x-on:input="insertColor($event)" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 space-y-3 border-t border-zinc-200 p-4 dark:border-white/10">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:dropdown position="top" align="start">
                            <flux:button type="button" size="sm" variant="outline" icon:trailing="chevron-down" wire:loading.attr="disabled" wire:target="createImage" :aria-label="__('Image model')">
                                {{ AppSettings::imageModelLabel($imageModel) }}
                            </flux:button>
                            <flux:menu class="min-w-56">
                                <flux:menu.radio.group wire:model.live="imageModel">
                                    @foreach (AppSettings::enabledImageModels() as $model)
                                        <flux:menu.radio :value="$model" wire:key="image-model-{{ md5($model) }}">{{ AppSettings::imageModelLabel($model) }}</flux:menu.radio>
                                    @endforeach
                                </flux:menu.radio.group>
                            </flux:menu>
                        </flux:dropdown>
                        <flux:dropdown position="top" align="start">
                            <flux:button type="button" size="sm" variant="outline" icon:trailing="chevron-down" wire:loading.attr="disabled" wire:target="createImage">
                                {{ $aspectRatio === 'auto' ? __('Auto') : $aspectRatio }}
                            </flux:button>
                            <flux:menu class="min-w-48">
                                <flux:menu.radio.group wire:model.live="aspectRatio">
                                    @foreach (GptImageOptions::ASPECT_RATIOS as $ratio)
                                        <flux:menu.radio :value="$ratio" wire:key="aspect-{{ $ratio }}">
                                            <div class="flex items-center gap-2.5">
                                                <span class="flex size-5 shrink-0 items-center justify-center" aria-hidden="true">
                                                    <span class="{{ GptImageOptions::aspectRatioIconClasses()[$ratio] ?? 'size-3.5' }} rounded-[2px] border-2 border-current opacity-70"></span>
                                                </span>
                                                <span class="flex items-baseline gap-2">
                                                    <span>{{ $ratio === 'auto' ? __('Auto') : $ratio }}</span>
                                                    <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ GptImageOptions::aspectRatioDescriptions()[$ratio] ?? '' }}</span>
                                                </span>
                                            </div>
                                        </flux:menu.radio>
                                    @endforeach
                                </flux:menu.radio.group>
                            </flux:menu>
                        </flux:dropdown>

                        <flux:dropdown position="top" align="start">
                            <flux:button type="button" size="sm" variant="outline" icon:trailing="chevron-down" wire:loading.attr="disabled" wire:target="createImage">
                                {{ strtoupper($resolution) }}
                            </flux:button>
                            <flux:menu class="min-w-56">
                                <flux:menu.radio.group wire:model.live="resolution">
                                    <flux:menu.radio value="1k">
                                        <div class="space-y-0.5">
                                            <div>1K</div>
                                            <flux:text size="sm" class="mt-0!">{{ __('Recommended for most use cases') }}</flux:text>
                                        </div>
                                    </flux:menu.radio>
                                    <flux:menu.radio value="2k">
                                        <div class="space-y-0.5">
                                            <div>2K</div>
                                            <flux:text size="sm" class="mt-0!">{{ __('Higher detail, balanced') }}</flux:text>
                                        </div>
                                    </flux:menu.radio>
                                    <flux:menu.radio value="4k">
                                        <div class="space-y-0.5">
                                            <div>4K</div>
                                            <flux:text size="sm" class="mt-0!">{{ __('Maximum resolution') }}</flux:text>
                                        </div>
                                    </flux:menu.radio>
                                </flux:menu.radio.group>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    @if ($referenceCount > 0)
                        <flux:radio.group wire:model.live="imageDetail" :label="__('Image quality')" variant="segmented" size="sm" class="w-full *:flex-1">
                            <flux:radio value="auto" :label="__('Automatic')" />
                            <flux:radio value="low" :label="__('Fair')" />
                            <flux:radio value="high" :label="__('Good')" />
                            <flux:radio value="original" :label="__('High')" />
                        </flux:radio.group>
                    @endif

                    <flux:button class="w-full" type="submit" variant="primary" color="emerald" wire:loading.attr="disabled" wire:target="newPhotos,createImage">
                        <span wire:loading.remove wire:target="newPhotos,createImage">{{ __('Create image') }}</span>
                        <span wire:loading wire:target="newPhotos">{{ __('Uploading image...') }}</span>
                        <span wire:loading wire:target="createImage">{{ __('Creating image...') }}</span>
                    </flux:button>
                </div>
            </form>
            @endif
        </div>
    </flux:modal>
</div>