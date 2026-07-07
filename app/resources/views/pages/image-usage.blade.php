<?php

use App\Models\AiImage;
use App\Services\AiImageEditor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    #[On('image-usage-updated')]
    public function refreshUsage(): void
    {
        unset($this->history, $this->userImageCount, $this->remainingToday, $this->dailyLimit);
    }

    public function deleteHistoryImage(int $id): void
    {
        app(AiImageEditor::class)->deleteGuestImage(request(), $id);

        $this->refreshUsage();
        $this->dispatch('image-deleted', id: $id);
    }

    #[Computed]
    public function userImageCount(): int
    {
        return app(AiImageEditor::class)->guestImageCount(request());
    }

    #[Computed]
    public function remainingToday(): ?int
    {
        return app(AiImageEditor::class)->remainingToday(request());
    }

    #[Computed]
    public function dailyLimit(): int
    {
        return app(AiImageEditor::class)->dailyLimit();
    }

    #[Computed]
    public function history()
    {
        return app(AiImageEditor::class)->guestHistory(request(), 50);
    }

    public function imageUrl(AiImage $image): ?string
    {
        return app(AiImageEditor::class)->resultUrl($image);
    }
}; ?>

<div class="space-y-3 rounded-md bg-zinc-200 p-3 dark:bg-white/10">
	<flux:modal class="max-w-md" name="history-modal">
		<div class="space-y-5">
			<div>
				<flux:heading size="lg">{{ number_format($this->userImageCount) }} Ảnh đã tạo</flux:heading>
			</div>

			<div class="max-h-[60svh] space-y-3 overflow-y-auto pe-1">
				@forelse ($this->history as $item)
					@php($url = $this->imageUrl($item))
					<div class="grid grid-cols-[6rem_1fr] items-center gap-3 rounded-md border border-white/10 bg-white/5"
						wire:key="history-modal-{{ $item->id }}">
						@if ($url)
							<a class="overflow-hidden rounded-l-md border border-white/10 bg-black/20" data-lightbox
								data-alt="Ảnh đã tạo" href="{{ $url }}">
								<img class="size-24 cursor-zoom-in object-cover" src="{{ $url }}" alt="Ảnh đã tạo" loading="lazy" />
							</a>
						@else
							<div class="aspect-square rounded-md border border-white/10 bg-white/5"></div>
						@endif

						<div class="min-w-0 space-y-2 px-2">
							@if ($item->custom_prompt)
								<flux:text class="line-clamp-2 text-sm">{{ $item->custom_prompt }}</flux:text>
							@endif
							<div class="flex items-center justify-between gap-2">
								<flux:text class="text-xs" variant="subtle">{{ $item->created_at?->diffForHumans() }}</flux:text>
								<div class="flex items-center gap-1">
									@if ($url)
										<flux:button :href="$url" download="{{ $item->downloadName() }}" size="sm" variant="ghost" icon="arrow-down-tray" aria-label="Tải ảnh" />
									@endif
									<flux:button type="button" size="sm" variant="danger" icon="trash"
										wire:click="deleteHistoryImage({{ $item->id }})" wire:confirm="Xóa ảnh này?" aria-label="Xóa ảnh" />
								</div>
							</div>
						</div>
					</div>
				@empty
					<div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
						<flux:text variant="subtle">Chưa có ảnh nào.</flux:text>
					</div>
				@endforelse
			</div>
		</div>
	</flux:modal>

	<flux:field>
		<flux:label>
			Còn lại
			<x-slot name="trailing">
				<span
					class="tabular-nums">{{ $this->remainingToday === null ? '∞' : $this->remainingToday . '/' . $this->dailyLimit }}</span>
			</x-slot>
		</flux:label>
		<flux:progress max="{{ $this->dailyLimit }}" value="{{ $this->remainingToday ?? $this->dailyLimit }}"
			color="amber" />
	</flux:field>

	<flux:modal.trigger name="history-modal">
		<flux:button class="w-full justify-start" type="button" variant="filled" icon="arrow-down-tray">
			{{ number_format($this->userImageCount) }} ảnh đã tạo
		</flux:button>
	</flux:modal.trigger>
</div>
