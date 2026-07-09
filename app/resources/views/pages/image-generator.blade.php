<?php

use App\Jobs\CreateAiImage;
use App\Models\AiImage;
use App\Services\AiImageEditor;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Auth;
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

    public mixed $newPhotos = [];

    public string $prompt = '';

    public string $rewriteInstruction = '';

    public ?int $resultId = null;

    public ?string $errorMessage = null;

    public ?string $publishMessage = null;

    public function mount(): void
    {
        $this->showComposer = Auth::check() && request()->boolean('composer');
    }

    public function openComposer(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $this->showComposer = true;
    }

    public function closeComposer(): void
    {
        $this->showComposer = false;
    }

    #[On('use-prompt')]
    public function usePrompt(string $prompt, ?int $imageId = null): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $this->prompt = $prompt;
        $this->rewriteInstruction = '';
        $this->resultId = null;
        $this->publishMessage = null;
        $this->showComposer = true;

        if ($imageId) {
            $this->referenceImageIds = array_slice(array_values(array_unique([$imageId, ...$this->referenceImageIds])), 0, $this->maxReferencePhotos());
            $this->photos = array_slice($this->photos, 0, max(0, $this->maxReferencePhotos() - count($this->referenceImageIds)));
        }
    }

    public function updatedNewPhotos(): void
    {
        $newPhotos = is_array($this->newPhotos) ? $this->newPhotos : [$this->newPhotos];
        $this->photos = array_slice([...$this->photos, ...$newPhotos], 0, max(0, $this->maxReferencePhotos() - count($this->referenceImageIds)));
        $this->newPhotos = [];
        $this->resultId = null;
        $this->errorMessage = null;
        $this->publishMessage = null;
        $this->resetValidation(['photos', 'photos.*', 'newPhotos', 'newPhotos.*']);
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

    public function maxReferencePhotos(): int
    {
        return min(3, max(1, AppSettings::int('ai.image_max_reference_photos', (int) config('ai.image_max_reference_photos', 1))));
    }

    /**
     * @return array<int, mixed>
     */
    private function promptRules(): array
    {
        return [
            'required',
            'string',
            'max:12000',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (preg_match_all('/[\p{L}\p{N}]+/u', (string) $value) > 1200) {
                    $fail(__('Prompt must not exceed 1200 words.'));
                }
            },
        ];
    }

    public function createImage(AiImageEditor $editor): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);

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
            'referenceImageIds' => ['array', 'max:'.$this->maxReferencePhotos()],
            'referenceImageIds.*' => ['integer'],
            'photos' => ['array', 'max:'.max(0, $this->maxReferencePhotos() - count($this->referenceImageIds))],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::int('ai.image_upload_max_kb', (int) config('ai.image_upload_max_kb', 32768))],
        ]);

        if ($editor->isLimitExceeded(request())) {
            $this->errorMessage = __('You have used all image generations for today.');

            return;
        }

        try {
            $image = $editor->createPending(request(), $this->photos, $this->prompt, $this->referenceImageIds);

            CreateAiImage::dispatch($image->id, $image->user_id);
            $this->resultId = null;
            unset($this->remainingToday, $this->resultImage);
            $this->dispatch('image-usage-updated');
            $this->redirectRoute('images.index', ['composer' => 1], navigate: true);
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            report($e);

            $this->errorMessage = __('Could not create an image right now. Please try again later.');
        }
    }

    public function rewritePrompt(AiImageEditor $editor): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $this->validate([
            'prompt' => $this->promptRules(),
            'rewriteInstruction' => ['nullable', 'string', 'max:1000'],
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

    public function publishResult(AiImageEditor $editor): void
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
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            report($e);

            $this->errorMessage = __('Could not publish the image right now.');
        }
    }

    public function createNew(): void
    {
        $this->reset('photos', 'referenceImageIds', 'newPhotos', 'prompt', 'rewriteInstruction', 'resultId', 'errorMessage', 'publishMessage');
        $this->resetValidation();
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
        return app(AiImageEditor::class)->remainingToday(request());
    }

    #[Computed]
    public function referenceImages()
    {
        if ($this->referenceImageIds === []) {
            return collect();
        }

        return AiImage::query()
            ->whereIn('id', $this->referenceImageIds)
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->get()
            ->sortBy(fn (AiImage $image) => array_search($image->id, $this->referenceImageIds, true));
    }

    #[Computed]
    public function resultImage(): ?AiImage
    {
        return $this->resultId ? AiImage::with('category')->find($this->resultId) : null;
    }

    public function imageUrl(AiImage $image): ?string
    {
        return app(AiImageEditor::class)->resultUrl($image);
    }
}; ?>

@php
	$resultImage = $this->resultImage;
	$resultUrl = $resultImage ? $this->imageUrl($resultImage) : null;
	$resultDownloadName = $resultImage?->downloadName();
	$maxReferencePhotos = $this->maxReferencePhotos();
	$referenceImages = $this->referenceImages;
	$referenceCount = count($photos) + $referenceImages->count();
@endphp

<div class="contents" x-data x-on:open-image-composer.window="$wire.openComposer()">
	<flux:modal name="image-composer" flyout class="md:w-[470px]" wire:model.self="showComposer" @close="closeComposer">
		<div class="space-y-5">
			<div class="space-y-1">
				<flux:heading size="xl">{{ __('Create image') }}</flux:heading>
				<flux:text variant="subtle">{{ __('Prompt is required. Reference images are optional.') }}</flux:text>
			</div>

				@if ($errorMessage)
					<div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-100">{{ $errorMessage }}</div>
				@endif

				@if ($publishMessage)
					<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-100">{{ $publishMessage }}</div>
				@endif

				@if ($resultUrl)
					<div class="space-y-4">
						<button class="block w-full" type="button" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $resultImage->id }} })" aria-label="{{ __('View image details') }}">
							<img class="max-h-[58svh] w-full rounded-[1.75rem] bg-zinc-100 object-contain shadow-inner"
								src="{{ $resultUrl }}" alt="{{ __('AI-generated image') }}" />
						</button>

						<div class="rounded-3xl bg-zinc-50 p-4">
							<p class="text-sm font-medium text-emerald-700">{{ __('Image created successfully.') }}</p>
							@if ($resultImage?->is_published)
								<p class="mt-1 text-sm text-zinc-500">{{ __('Published in :category.', ['category' => $resultImage->category?->name ?? __('Other')]) }}</p>
							@else
								<p class="mt-1 text-sm text-zinc-500">{{ __('Publish to make the image appear in the community gallery.') }}</p>
							@endif
						</div>

						<div class="grid grid-cols-2 gap-2">
							<flux:button :href="$resultUrl" download="{{ $resultDownloadName }}">{{ __('Download') }}</flux:button>
							<flux:button type="button" variant="outline" wire:click="createNew">{{ __('Create new image') }}</flux:button>
							<flux:button class="col-span-2" type="button" variant="primary" wire:click="publishResult"
								:disabled="$resultImage?->is_published" wire:loading.attr="disabled" wire:target="publishResult">
								{{ $resultImage?->is_published ? __('Published') : __('Publish image') }}
							</flux:button>
						</div>
					</div>
				@else
					<form class="space-y-4" wire:submit="createImage">
						<flux:card class="space-y-3">
							<div class="flex items-center justify-between gap-3">
								<flux:heading size="sm">{{ __('Reference images') }}</flux:heading>
								<flux:text class="text-sm" variant="subtle">{{ $referenceCount }}/{{ $maxReferencePhotos }}</flux:text>
							</div>

							@if ($referenceCount > 0)
								<div class="grid grid-cols-3 gap-2">
									@foreach ($referenceImages as $image)
										@php($url = $this->imageUrl($image))
										@if ($url)
											<div class="group relative overflow-hidden rounded-2xl bg-zinc-200">
												<img class="aspect-square size-full object-cover" src="{{ $url }}" alt="{{ __('Reference image :number', ['number' => $loop->iteration]) }}" />
												<flux:button class="absolute right-1 top-1" type="button" size="xs" variant="filled" icon="x-mark"
													wire:click="removeReferenceImage({{ $image->id }})" wire:loading.remove wire:target="createImage"
													aria-label="{{ __('Remove reference image :number', ['number' => $loop->iteration]) }}" />
											</div>
										@endif
									@endforeach
									@foreach ($photos as $index => $item)
										<div class="overflow-hidden rounded-2xl bg-zinc-100 text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
											<div class="group relative bg-zinc-200 dark:bg-white/10">
												<img class="aspect-square size-full object-cover" src="{{ $item->temporaryUrl() }}" alt="{{ __('Reference image :number', ['number' => $referenceImages->count() + $index + 1]) }}" />
												<flux:button class="absolute right-1 top-1" type="button" size="xs" variant="filled" icon="x-mark"
													wire:click="removePhoto({{ $index }})" wire:loading.remove wire:target="createImage"
													aria-label="{{ __('Remove reference image :number', ['number' => $referenceImages->count() + $index + 1]) }}" />
											</div>
											<div class="flex items-center gap-1.5 px-2 py-1.5">
												<flux:icon class="size-3.5 text-emerald-500" name="check-circle" />
												<span class="truncate">{{ $item->getClientOriginalName() }}</span>
											</div>
										</div>
									@endforeach
								</div>
							@endif

							@if ($referenceCount < $maxReferencePhotos)
								<flux:file-upload wire:model="newPhotos" accept="image/jpeg,image/png,image/webp,image/avif" :multiple="$maxReferencePhotos > 1">
									<flux:file-upload.dropzone
										:heading="$referenceCount > 0 ? __('Add image') : __('Upload optional image')"
										:text="__('Drop images here or click to browse')"
										with-progress
										inline />
								</flux:file-upload>
							@endif
							<div class="mt-2 text-sm text-zinc-500" wire:loading wire:target="newPhotos">{{ __('Uploading image...') }}</div>
						</flux:card>

						<div
							x-data="{
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
						}"
							x-on:prompt-rewritten.window="$refs.rewriteDropdown?.lastElementChild?.hidePopover?.()">
							<div class="space-y-2">
								<div class="flex items-center justify-between gap-2">
									<span class="text-sm font-medium text-zinc-800 dark:text-white">{{ __('Prompt') }}</span>
									<flux:tooltip content="{{ __('Rewrite prompt') }}" position="top">
										<flux:dropdown x-ref="rewriteDropdown" position="bottom" align="end">
											<flux:button type="button" size="sm" variant="filled" icon="sparkles">{{ __('Rewrite prompt') }}</flux:button>
											<flux:popover class="w-80 space-y-3">
												<div>
													<flux:heading size="sm">{{ __('Rewrite prompt') }}</flux:heading>
													<flux:text variant="subtle">{{ __('Tell AI how to rewrite your current prompt.') }}</flux:text>
												</div>
												<flux:textarea wire:model="rewriteInstruction" rows="4" resize="vertical" :label="__('Rewrite instruction')" :placeholder="__('e.g. Make it more cinematic, add product lighting, keep the same subject...')" />
												<flux:button class="w-full" type="button" size="sm" variant="primary" wire:click="rewritePrompt" wire:loading.attr="disabled" wire:target="rewritePrompt">
													<span wire:loading.remove wire:target="rewritePrompt">{{ __('Rewrite prompt') }}</span>
													<span wire:loading wire:target="rewritePrompt">{{ __('Rewriting prompt...') }}</span>
												</flux:button>
											</flux:popover>
										</flux:dropdown>
									</flux:tooltip>
								</div>
								<flux:textarea
									class="max-h-[400px] overflow-y-auto [&_textarea]:max-h-[400px] [&_textarea]:overflow-y-auto"
									wire:model.live.debounce.300ms="prompt"
									rows="auto"
									:placeholder="__('Describe the image you want to create...')"
									x-ref="prompt"
									x-on:input="handlePromptInput($event)"
									required />
							</div>

							<div class="relative">
								<div
									class="absolute bottom-2 inset-s-2 z-10"
									x-show="pickerOpen"
									x-cloak
									x-transition
									@keydown.escape="closePicker()">
									<flux:color-picker type="button" size="xs" x-ref="picker" x-on:input="insertColor($event)" />
								</div>
							</div>
						</div>

						<flux:button class="w-full" type="submit" variant="primary" wire:loading.attr="disabled" wire:target="newPhotos,createImage">
							<span wire:loading.remove wire:target="newPhotos,createImage">{{ __('Create image') }}</span>
							<span wire:loading wire:target="newPhotos">{{ __('Uploading image...') }}</span>
							<span wire:loading wire:target="createImage">{{ __('Creating image...') }}</span>
						</flux:button>
					</form>
				@endif
		</div>
	</flux:modal>
</div>
