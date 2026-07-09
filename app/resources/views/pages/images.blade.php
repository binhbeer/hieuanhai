<?php

use App\Models\AiImage;
use App\Services\AiImageEditor;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Created images')] class extends Component
{
    public function refreshCompletedImage(array $payload = []): void
    {
        $this->refreshImages();

        if (($payload['status'] ?? null) === 'succeeded') {
            Flux::toast(variant: 'success', text: __('Image created successfully.'));

            return;
        }

        if (($payload['status'] ?? null) === 'failed') {
            Flux::toast(text: __('Could not create this image.'));
        }
    }

    public function togglePublish(int $id, AiImageEditor $editor): void
    {
        $image = $this->findImage($id);

        if (! $image) {
            return;
        }

        if ($image->is_published) {
            $image->update(['is_published' => false, 'published_at' => null]);
            $this->refreshImages();
            $this->dispatch('gallery-updated');
            Flux::toast(variant: 'success', text: __('Image unpublished.'));

            return;
        }

        try {
            $editor->publish($image, request());
            $this->refreshImages();
            $this->dispatch('gallery-updated');
            Flux::toast(variant: 'success', text: __('Image published.'));
        } catch (InvalidArgumentException $e) {
            Flux::toast(text: $e->getMessage());
        }
    }

    public function cancelPending(int $id): void
    {
        $query = AiImage::query();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', app(AiImageEditor::class)->visitorKey(request()));

        $image = $query->where('status', 'pending')->find($id);

        if (! $image) {
            return;
        }

        $requestMeta = is_array($image->request_meta) ? $image->request_meta : [];
        $requestMeta['progress'] = 'cancelled';

        $image->update([
            'status' => 'failed',
            'error' => 'Đã hủy tạo ảnh.',
            'request_meta' => $requestMeta,
        ]);

        $this->refreshImages();
        Flux::toast(text: __('Image creation cancelled.'));
    }

    #[Computed]
    public function images()
    {
        return app(AiImageEditor::class)->guestHistory(request(), 120);
    }

    public function imageUrl(AiImage $image): ?string
    {
        return app(AiImageEditor::class)->resultUrl($image);
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

    private function findImage(int $id): ?AiImage
    {
        $query = AiImage::query();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', app(AiImageEditor::class)->visitorKey(request()));

        return $query
            ->where('status', 'succeeded')
            ->whereNotNull('result_path')
            ->find($id);
    }

    private function refreshImages(): void
    {
        unset($this->images);
        $this->dispatch('image-usage-updated');
    }
}; ?>

<section class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6" @if ($this->images->contains(fn ($image) => $image->status === 'pending')) wire:poll.2s @endif>
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div class="space-y-1">
			<flux:heading size="xl">{{ __('Created images') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Images you created in this browser. Publish them to make them appear in the gallery.') }}</flux:text>
		</div>
	</div>

	@if ($this->images->isEmpty())
		<div class="flex min-h-[55svh] items-center justify-center rounded-4xl border border-dashed border-zinc-300 bg-white text-center dark:border-white/10 dark:bg-white/5">
			<div class="max-w-sm p-8">
				<div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-white/10">
					<flux:icon class="size-7 text-zinc-500" name="photo" />
				</div>
				<flux:heading size="lg">{{ __('No images yet') }}</flux:heading>
				<flux:text class="mt-2" variant="subtle">{{ __('Images will appear here after you create them.') }}</flux:text>
			</div>
		</div>
	@else
		<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
			@foreach ($this->images as $image)
				@php($url = $this->imageUrl($image))
				@php($progressStep = $this->progressStep($image))
				<flux:card class="overflow-hidden p-0" wire:key="created-image-{{ $image->id }}">
					<button class="block w-full text-left" type="button" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }} })" aria-label="{{ __('View image details') }}">
						@if ($url)
							<img class="aspect-square w-full bg-zinc-100 object-cover dark:bg-white/10" src="{{ $url }}" alt="{{ __('Image #:id', ['id' => $image->id]) }}" loading="lazy" />
						@elseif ($image->status === 'pending')
							<div class="relative flex aspect-square items-center justify-center overflow-hidden bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-white/80">
								<div class="absolute inset-0 bg-[radial-gradient(circle_at_center,var(--color-zinc-50),var(--color-zinc-200))] dark:bg-[radial-gradient(circle_at_center,rgba(255,255,255,.12),rgba(255,255,255,.04))]"></div>
								<div class="relative flex w-4/5 max-w-56 flex-col items-center gap-4 rounded-3xl border border-white/80 bg-white/85 p-5 text-center shadow-lg backdrop-blur dark:border-white/10 dark:bg-zinc-900/80">
									<div class="relative flex size-16 items-center justify-center">
										<div class="absolute inset-0 animate-spin rounded-full border-4 border-zinc-200 border-t-zinc-900 dark:border-white/15 dark:border-t-white"></div>
										<flux:icon class="size-7" name="sparkles" />
									</div>
									<div>
										<div class="text-sm font-semibold">{{ __('Creating image') }}</div>
										<div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $this->progressLabel($image) }}</div>
									</div>
									<div class="h-1 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10" role="progressbar" aria-valuemin="1" aria-valuemax="4" aria-valuenow="{{ $progressStep }}" aria-label="{{ $this->progressLabel($image) }}">
										<div class="h-full animate-pulse rounded-full bg-zinc-900 transition-[width] dark:bg-white" style="width: {{ $progressStep * 25 }}%"></div>
									</div>
								</div>
							</div>
						@else
							<div class="flex aspect-square items-center justify-center bg-red-50 text-red-500 dark:bg-red-400/10 dark:text-red-200">
								<flux:icon class="size-8" name="exclamation-triangle" />
							</div>
						@endif
					</button>

					<div class="space-y-3 p-4">
						<div class="flex flex-wrap items-center justify-between gap-2">
							<flux:badge size="sm" :color="$image->status === 'failed' ? 'red' : null">
								{{ match ($image->status) {
									'pending' => __('Creating'),
									'failed' => __('Failed'),
									default => $image->is_published ? __('Published') : __('Unpublished'),
								} }}
							</flux:badge>
							<flux:text class="text-xs" variant="subtle">#{{ $image->id }} · {{ $image->created_at?->diffForHumans() }}</flux:text>
						</div>

						<p class="line-clamp-3 text-sm font-medium">{{ $image->prompt }}</p>

						@if ($image->status === 'failed')
							<flux:text class="text-sm text-red-600 dark:text-red-300">{{ $image->error ?: __('Could not create this image.') }}</flux:text>
						@endif

						<div class="grid grid-cols-2 gap-2">
							@if ($url)
								<flux:button :href="$url" download="{{ $image->downloadName() }}" size="sm" variant="filled">{{ __('Download') }}</flux:button>
							@endif
							@if ($image->status === 'succeeded' && $image->result_path)
								<flux:button type="button" size="sm" :variant="$image->is_published ? 'danger' : 'primary'" wire:click="togglePublish({{ $image->id }})">
									{{ $image->is_published ? __('Unpublish') : __('Publish image') }}
								</flux:button>
							@endif
							@if ($image->status === 'pending')
								<flux:button class="col-span-2" type="button" size="sm" variant="ghost" wire:click="cancelPending({{ $image->id }})" wire:confirm="{{ __('Cancel image creation?') }}">
									{{ __('Cancel') }}
								</flux:button>
							@endif
						</div>
					</div>
				</flux:card>
			@endforeach
		</div>
	@endif

</section>
