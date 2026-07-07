<?php

use App\Models\AiImage;
use App\Services\AiImageEditor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?string $selectedPreset = null;

    public array $photos = [];

    public mixed $newPhotos = [];

    public string $customPrompt = '';

    public ?int $resultId = null;

    public ?string $errorMessage = null;

    public function updatedSelectedPreset(): void
    {
        if (!$this->selectedPreset || !array_key_exists($this->selectedPreset, $this->presets())) {
            $this->selectedPreset = null;

            return;
        }

        $this->resultId = null;
        $this->errorMessage = null;
        $this->resetValidation();
    }

    public function selectPreset(string $preset): void
    {
        $this->selectedPreset = $preset;
        $this->updatedSelectedPreset();
    }

    public function updatedNewPhotos(): void
    {
        $newPhotos = is_array($this->newPhotos) ? $this->newPhotos : [$this->newPhotos];
        $this->photos = array_slice([...$this->photos, ...$newPhotos], 0, $this->maxReferencePhotos());
        $this->newPhotos = [];
        $this->resultId = null;
        $this->errorMessage = null;
        $this->resetValidation(['photos', 'photos.*', 'newPhotos', 'newPhotos.*']);
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
        $this->resetValidation(['photos', 'photos.*']);
    }

    public function maxReferencePhotos(): int
    {
        return min(3, max(1, (int) config('ai.image_max_reference_photos', 1)));
    }

    public function backToPresets(): void
    {
        $this->reset('selectedPreset', 'photos', 'newPhotos', 'customPrompt', 'errorMessage');
        $this->resetValidation();
    }

    public function createImage(AiImageEditor $editor): void
    {
        $this->errorMessage = null;

        $this->validate([
            'selectedPreset' => ['required', 'string'],
            'photos' => ['required', 'array', 'min:1', 'max:' . $this->maxReferencePhotos()],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:' . config('ai.image_upload_max_kb', 32768)],
            'customPrompt' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!$this->selectedPreset || !isset($this->presets()[$this->selectedPreset])) {
            $this->addError('selectedPreset', 'Hãy chọn một phong cách.');

            return;
        }

        if ($editor->isLimitExceeded(request())) {
            $this->errorMessage = 'Bạn đã dùng hết lượt tạo ảnh hôm nay.';

            return;
        }

        $preset = $this->presets()[$this->selectedPreset];

        try {
            $image = $editor->create(request(), $this->photos, $preset['prompt'], trim($this->customPrompt) ?: null, $this->selectedPreset);

            $this->resultId = $image->id;
            $this->reset('photos', 'newPhotos', 'customPrompt');
            unset($this->remainingToday);
            $this->dispatch('image-usage-updated');
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            report($e);

            $this->errorMessage = 'Không tạo được ảnh lúc này. Vui lòng thử lại sau.';
        }
    }

    public function createNew(): void
    {
        $this->reset('selectedPreset', 'photos', 'newPhotos', 'customPrompt', 'resultId', 'errorMessage');
        $this->resetValidation();
    }

    #[On('image-deleted')]
    public function clearDeletedResult(int $id): void
    {
        if ($this->resultId !== $id) {
            return;
        }

        $this->resultId = null;
    }

    /**
				 * @return array<string, array{title: string, prompt: string}>
				 */
    public function presets(): array
    {
        return [
            'comic' => [
                'title' => 'Comic',
                'prompt' => 'Biến ảnh thành comic hiện đại: nét mực rõ, màu tươi, giữ nhận diện và bố cục.',
            ],
            'studio' => [
                'title' => 'Studio',
                'prompt' => 'Tạo chân dung studio cao cấp: ánh sáng mềm, da tự nhiên, nền sạch, giữ nhận diện.',
            ],
            'cinematic' => [
                'title' => 'Film',
                'prompt' => 'Tạo ảnh điện ảnh: ánh sáng kịch tính, màu film, tương phản đẹp, giữ chủ thể tự nhiên.',
            ],
            'product' => [
                'title' => 'Shop',
                'prompt' => 'Tối ưu ảnh sản phẩm: sắc nét, nền sạch, ánh sáng thương mại, giữ đúng hình dáng.',
            ],
            'oil-painting' => [
                'title' => 'Sơn dầu',
                'prompt' => 'Biến ảnh thành tranh sơn dầu: nét cọ tự nhiên, màu sâu, giữ khuôn mặt và bố cục.',
            ],
            '3d' => [
                'title' => '3D',
                'prompt' => 'Biến ảnh thành phong cách 3D: tạo hình mượt, ánh sáng studio, vật liệu rõ, giữ nhận diện.',
            ],
            'sharpen' => [
                'title' => 'Làm nét',
                'prompt' => 'Làm nét tự nhiên: giảm mờ, tăng chi tiết, giữ màu và khuôn mặt không bị giả.',
            ],
            'restore' => [
                'title' => 'Phục hồi',
                'prompt' => 'Phục hồi ảnh cũ: giảm xước, giảm nhiễu, tăng nét và màu, giữ người và bối cảnh.',
            ],
            'id-photo' => [
                'title' => 'Ảnh thẻ',
                'prompt' => 'Tạo ảnh thẻ sạch: nền trơn, ánh sáng đều, mặt rõ, giữ nhận diện tự nhiên.',
            ],
            'photo-merge' => [
                'title' => 'Ghép ảnh',
                'prompt' => 'Ghép ảnh tự nhiên: đồng nhất ánh sáng, màu sắc và bố cục, giữ chi tiết chính.',
            ],
        ];
    }

    #[Computed]
    public function remainingToday(): ?int
    {
        return app(AiImageEditor::class)->remainingToday(request());
    }

    #[Computed]
    public function resultImage(): ?AiImage
    {
        return $this->resultId ? AiImage::find($this->resultId) : null;
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
	$selectedPresetData = $selectedPreset ? $this->presets()[$selectedPreset] ?? null : null;
	$maxReferencePhotos = $this->maxReferencePhotos();
@endphp
<section
	class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-4 sm:p-5">
		@if ($resultUrl)
			<div class="space-y-4">
				<a data-lightbox data-alt="Ảnh đã chỉnh bằng AI" href="{{ $resultUrl }}">
					<img class="max-h-[55svh] w-full cursor-zoom-in rounded-3xl border border-white/10 object-contain"
						src="{{ $resultUrl }}" alt="Ảnh đã chỉnh bằng AI" />
				</a>

				<div class="space-y-3 p-4 text-center">
					<flux:text class="text-emerald-100">Ảnh đã tạo xong.</flux:text>
					<div class="grid grid-cols-2 gap-2">
						<flux:button :href="$resultUrl" download="{{ $resultDownloadName }}">Tải về</flux:button>
						<flux:button type="button" variant="outline" wire:click="createNew">Tạo ảnh mới</flux:button>
					</div>
				</div>
			</div>
		@else
			<form class="space-y-4 overflow-x-hidden" wire:submit="createImage">
				@if (!$selectedPresetData)
					<div class="space-y-3">
						<flux:heading size="lg">Chọn phong cách</flux:heading>
						<div class="grid grid-cols-2 gap-3 overflow-y-auto overflow-x-hidden pe-1 md:grid-cols-4">
							@foreach ($this->presets() as $key => $preset)
								<flux:card
									class="min-w-0 cursor-pointer p-4! text-center transition hover:border-orange-300/40 hover:bg-white/15"
									size="sm" role="button" tabindex="0" wire:key="preset-{{ $key }}"
									wire:click="selectPreset('{{ $key }}')" wire:keydown.enter="selectPreset('{{ $key }}')">
									<p class="truncate font-medium text-white">{{ $preset['title'] }}</p>
								</flux:card>
							@endforeach
						</div>
					</div>
				@else
					<div class="space-y-4">
						<div class="flex items-center justify-between gap-3">
							<flux:button type="button" variant="ghost" icon="arrow-left" wire:click="backToPresets">Quay lại</flux:button>
							<flux:text class="truncate text-sm" variant="subtle">{{ $selectedPresetData['title'] }}</flux:text>
						</div>

						@if ($errorMessage)
							<div class="rounded-2xl border border-red-400/30 bg-red-400/10 p-3 text-sm text-red-100">{{ $errorMessage }}
							</div>
						@endif

						<div class="space-y-3">
							<div
								class="relative overflow-hidden rounded-xl border border-dashed border-white/10 bg-white/4 p-3">
								@if ($photos)
									<div @class([
										'grid h-72 gap-2',
										'grid-cols-1' => count($photos) === 1,
										'grid-rows-2' => count($photos) === 2,
										'grid-rows-[2fr_1fr]' => count($photos) === 3,
									])>
										@foreach ($photos as $index => $item)
											<div @class([
												'group relative overflow-hidden rounded-2xl border border-white/10 bg-black/30',
												'grid grid-cols-2 gap-2 border-0 bg-transparent' =>
													count($photos) === 3 && $index === 1,
											])>
												@if (count($photos) === 3 && $index === 1)
													<div class="relative overflow-hidden rounded-2xl border border-white/10 bg-black/30">
														<img class="size-full object-cover" src="{{ $photos[1]->temporaryUrl() }}" alt="Ảnh tham chiếu 2" />
														<flux:button class="absolute left-2 top-2 z-20" type="button" size="xs" variant="filled"
															icon="x-mark" wire:click="removePhoto(1)" wire:loading.remove wire:target="createImage"
															aria-label="Bỏ ảnh tham chiếu 2" />
													</div>
													<div class="relative overflow-hidden rounded-2xl border border-white/10 bg-black/30">
														<img class="size-full object-cover" src="{{ $photos[2]->temporaryUrl() }}" alt="Ảnh tham chiếu 3" />
														<flux:button class="absolute left-2 top-2 z-20" type="button" size="xs" variant="filled"
															icon="x-mark" wire:click="removePhoto(2)" wire:loading.remove wire:target="createImage"
															aria-label="Bỏ ảnh tham chiếu 3" />
													</div>
													@break

												@else
													<img class="size-full object-contain transition-opacity" src="{{ $item->temporaryUrl() }}"
														alt="Ảnh tham chiếu {{ $index + 1 }}" wire:loading.class="opacity-20" wire:target="createImage" />
													<flux:button class="absolute left-2 top-2 z-20" type="button" size="xs" variant="filled"
														icon="x-mark" wire:click="removePhoto({{ $index }})" wire:loading.remove
														wire:target="createImage" aria-label="Bỏ ảnh tham chiếu {{ $index + 1 }}" />
												@endif
											</div>
										@endforeach
									</div>
								@else
									<div class="flex h-72 flex-col items-center justify-center gap-4 rounded-2xl bg-black/20 p-3 text-center">
										<flux:icon class="size-10 text-zinc-400" name="cloud-arrow-up" />
										<div>
											<p class="font-medium text-white">Tải ảnh cần chỉnh</p>
											<flux:text class="text-sm" variant="subtle">Tối đa {{ $maxReferencePhotos }} ảnh JPG, PNG, WEBP, AVIF.</flux:text>
										</div>
									</div>
								@endif

								<div class="absolute inset-0 z-10 hidden items-center justify-center bg-black/35" wire:loading.flex
									wire:target="createImage">
									<flux:skeleton class="absolute inset-3 h-auto rounded-2xl opacity-70" animate="shimmer" />
									<div
										class="relative z-10 flex items-center gap-3 rounded-2xl border border-orange-300/20 bg-zinc-950/90 px-4 py-3 text-sm text-orange-100 shadow-xl">
										<span class="size-5 animate-spin rounded-full border-2 border-orange-200 border-t-transparent"></span>
										<span>Đang tạo ảnh...</span>
									</div>
								</div>
							</div>

							@if (count($photos) < $maxReferencePhotos)
								<label
									class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/10 px-4 py-3 text-sm font-medium text-white transition hover:bg-white/15">
									<flux:icon class="size-5" name="plus" />
									<span>{{ $photos ? 'Upload thêm ảnh' : 'Upload ảnh' }}</span>
									<input class="sr-only" type="file" wire:model="newPhotos" accept="image/jpeg,image/png,image/webp,image/avif"
										@if ($maxReferencePhotos > 1) multiple @endif>
								</label>
							@endif
							<div class="text-sm text-zinc-400" wire:loading wire:target="newPhotos">Đang tải ảnh lên...</div>
						</div>

						<flux:textarea wire:model="customPrompt" rows="3" label="Yêu cầu tùy chỉnh"
							placeholder="Ví dụ: giữ nguyên nền, tăng ánh sáng mặt..." />

						<div class="grid grid-cols-[auto_1fr] gap-2">
							<flux:button class="w-full" type="submit" variant="primary"
								:disabled="count($photos) === 0 || $this->remainingToday === 0" wire:loading.attr="disabled"
								wire:target="createImage">
								Tạo ảnh</flux:button>
						</div>
					</div>
				@endif
			</form>
		@endif
	</div>
</section>
