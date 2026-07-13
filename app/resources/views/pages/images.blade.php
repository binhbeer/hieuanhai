<?php

use App\Jobs\CreateAiImage;
use App\Models\AiImage;
use App\Services\AiImageEditor;
use App\Support\AppSettings;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Ảnh của bạn')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $publish = 'all';

    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

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

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPublish(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ['id', 'created_at', 'status'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = $column === 'created_at' || $column === 'id' ? 'desc' : 'asc';
        }

        $this->resetPage();
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
            $this->refreshImages();
            Flux::toast(text: $e->getMessage());
        }
    }

    public function cancelPending(int $id, AiImageEditor $editor): void
    {
        $query = AiImage::query();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', $editor->visitorKey(request()));

        $image = $query->where('status', 'pending')->find($id);

        if (! $image || ! $editor->cancelPending($image)) {
            return;
        }

        $this->refreshImages();
        Flux::toast(text: __('Image creation cancelled.'));
    }

    public function deleteImage(int $id, AiImageEditor $editor): void
    {
        $editor->deleteGuestImage(request(), $id);
        $this->refreshImages();
        $this->dispatch('gallery-updated');
        Flux::toast(variant: 'success', text: __('Image deleted.'));
    }

    #[Computed]
    public function usage(): array
    {
        $editor = app(AiImageEditor::class);

        return [
            'limit' => $editor->dailyLimit(),
            'remaining' => $editor->remainingToday(request()),
            'used' => $editor->countToday(request()),
        ];
    }

    #[Computed]
    public function dailyUsage(): array
    {
        $from = today()->subDays(29);
        $counts = AiImage::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'succeeded'])
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date');

        return collect(range(0, 29))->map(function (int $offset) use ($from, $counts): array {
            $date = $from->copy()->addDays($offset);

            return [
                'date' => $date,
                'total' => (int) ($counts[$date->toDateString()] ?? 0),
            ];
        })->all();
    }

    #[Computed]
    public function upgradeUrl(): string
    {
        return trim(AppSettings::string('contact.zalo_url'));
    }

    #[Computed]
    public function images()
    {
        $sortBy = in_array($this->sortBy, ['id', 'created_at', 'status'], true) ? $this->sortBy : 'created_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $query = AiImage::query();

        Auth::check()
            ? $query->where('user_id', Auth::id())
            : $query->where('visitor_key', app(AiImageEditor::class)->visitorKey(request()));

        return $query
            ->with('category')
            ->whereIn('status', ['pending', 'succeeded', 'failed'])
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->publish === 'published', fn ($q) => $q->where('is_published', true))
            ->when($this->publish === 'unpublished', fn ($q) => $q->where('is_published', false))
            ->orderBy($sortBy, $sortDirection)
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function imageUrl(AiImage $image, string $size = 'original'): ?string
    {
        return app(AiImageEditor::class)->imageUrl($image, $size);
    }

    public function imageSize(AiImage $image, string $size = 'original'): ?array
    {
        return app(AiImageEditor::class)->imageSize($image, $size);
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

    public function statusLabel(AiImage $image): string
    {
        return match ($image->status) {
            'pending' => __('Creating'),
            'failed' => __('Failed'),
            default => $image->is_published ? __('Published') : __('Unpublished'),
        };
    }

    public function sizeLabel(AiImage $image): ?string
    {
        $meta = $image->request_meta ?? [];
        $size = is_string($meta['size'] ?? null) ? $meta['size'] : null;
        $aspect = is_string($meta['aspect_ratio'] ?? null) ? $meta['aspect_ratio'] : null;
        $resolution = is_string($meta['resolution'] ?? null) ? $meta['resolution'] : null;

        return collect([$aspect, $resolution, $size])->filter()->unique()->implode(' · ') ?: null;
    }

    public function errorCode(AiImage $image): ?string
    {
        if (! Auth::user()?->isAdmin()) {
            return null;
        }

        $error = trim((string) ($image->error ?? ''));

        if ($error === '') {
            return null;
        }

        if (preg_match('/(?:lỗi|error|status)\s*(\d{3})\b/iu', $error, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\b([45]\d{2})\b/', $error, $matches)) {
            return $matches[1];
        }

        return null;
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
        unset($this->images, $this->usage, $this->dailyUsage);
        $this->dispatch('image-usage-updated');
    }
}; ?>

<section class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6" @if ($this->images->contains(fn ($image) => $image->status === 'pending')) wire:poll.2s @endif>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Created images') }}</flux:heading>
            <flux:text variant="subtle">{{ __('Images you created in this browser. Publish them to make them appear in the gallery.') }}</flux:text>
        </div>
        <flux:text variant="subtle">{{ __(':count images', ['count' => number_format($this->images->total())]) }}</flux:text>
    </div>

    @php($usage = $this->usage)
    @php($dailyUsage = $this->dailyUsage)
    @php($maxDailyUsage = max(1, collect($dailyUsage)->max('total')))
    @php($periodUsage = collect($dailyUsage)->sum('total'))

    <flux:card class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Image usage') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Daily generations counted toward your image quota.') }}</flux:text>
            </div>
            @if (! auth()->user()?->isAdmin() && $this->upgradeUrl !== '')
                <flux:button :href="$this->upgradeUrl" target="_blank" rel="noopener noreferrer" variant="primary">
                    {{ __('Upgrade') }}
                </flux:button>
            @endif
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-zinc-100 p-4 dark:bg-white/5">
                <flux:text variant="subtle">{{ __('Used today') }}</flux:text>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($usage['used']) }}</div>
            </div>
            <div class="rounded-xl bg-zinc-100 p-4 dark:bg-white/5">
                <flux:text variant="subtle">{{ __('Remaining today') }}</flux:text>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $usage['remaining'] === null ? '∞' : number_format($usage['remaining']) . '/' . number_format($usage['limit']) }}</div>
            </div>
            <div class="rounded-xl bg-zinc-100 p-4 dark:bg-white/5">
                <flux:text variant="subtle">{{ __('Last 30 days') }}</flux:text>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($periodUsage) }}</div>
            </div>
        </div>

        <div class="overflow-x-auto pb-1">
            <div class="min-w-2xl">
                <div class="flex h-36 items-end gap-1 border-b border-zinc-200 dark:border-white/10" role="img" aria-label="{{ __('Daily image generations for the last 30 days') }}">
                    @foreach ($dailyUsage as $day)
                        @php($height = $day['total'] > 0 ? max(6, $day['total'] / $maxDailyUsage * 100) : 0)
                        <div class="group relative flex h-full min-w-0 flex-1 items-end" wire:key="image-usage-{{ $day['date']->toDateString() }}">
                            <div class="w-full rounded-t bg-violet-500 transition hover:bg-violet-400" style="height: {{ $height }}%"></div>
                            <div class="pointer-events-none absolute bottom-full inset-s-1/2 z-10 mb-2 hidden w-max -translate-x-1/2 rounded-lg bg-zinc-950 px-2 py-1.5 text-xs text-white shadow-lg group-hover:block">
                                {{ $day['date']->format('d/m') }} · {{ __(':count generations', ['count' => number_format($day['total'])]) }}
                            </div>
                            <span class="sr-only">{{ $day['date']->format('d/m/Y') }}: {{ __(':count generations', ['count' => number_format($day['total'])]) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                    <span>{{ $dailyUsage[0]['date']->format('d/m') }}</span>
                    <span>{{ __('30 days') }}</span>
                    <span>{{ $dailyUsage[29]['date']->format('d/m') }}</span>
                </div>
            </div>
        </div>
    </flux:card>

    <div class="flex flex-wrap items-end gap-3">
        <flux:select class="min-w-40" wire:model.live="status" :label="__('Status')">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="succeeded">{{ __('Succeeded') }}</flux:select.option>
            <flux:select.option value="pending">{{ __('Creating') }}</flux:select.option>
            <flux:select.option value="failed">{{ __('Failed') }}</flux:select.option>
        </flux:select>
        <flux:select class="min-w-40" wire:model.live="publish" :label="__('Publish')">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="published">{{ __('Published') }}</flux:select.option>
            <flux:select.option value="unpublished">{{ __('Unpublished') }}</flux:select.option>
        </flux:select>
    </div>

    @if ($this->images->isEmpty())
        <div class="flex min-h-[40svh] items-center justify-center rounded-4xl border border-dashed border-zinc-300 bg-white text-center dark:border-white/10 dark:bg-white/5">
            <div class="max-w-sm p-8">
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-white/10">
                    <x-iconsax-two-gallery class="size-7 text-zinc-500" />
                </div>
                <flux:heading size="lg">{{ __('No images yet') }}</flux:heading>
                <flux:text class="mt-2" variant="subtle">{{ __('Images will appear here after you create them.') }}</flux:text>
            </div>
        </div>
    @else
        <flux:table :paginate="$this->images">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sort('id')">{{ __('Image') }}</flux:table.column>
                <flux:table.column>{{ __('Prompt') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Size') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->images as $image)
                    @php($originalUrl = $this->imageUrl($image))
                    @php($thumbUrl = $this->imageUrl($image, 'xs'))
                    @php($thumbSize = $this->imageSize($image, 'xs'))
                    @php($sizeLabel = $this->sizeLabel($image))
                    @php($progressStep = $this->progressStep($image))
                    <flux:table.row wire:key="created-image-{{ $image->id }}-{{ $image->is_published ? 'published' : 'unpublished' }}">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <button class="shrink-0 overflow-hidden rounded-xl bg-zinc-100 dark:bg-white/10" type="button" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }} })" aria-label="{{ __('View image details') }}">
                                    @if ($thumbUrl)
                                        <img class="size-16 object-cover" src="{{ $thumbUrl }}" alt="{{ Str::limit($image->title ?: __('Image #:id', ['id' => $image->id]), 80) }}" @if ($thumbSize) width="{{ $thumbSize['width'] }}" height="{{ $thumbSize['height'] }}" @endif loading="lazy" />
                                    @elseif ($image->status === 'pending')
                                        <div class="relative flex size-16 items-center justify-center text-zinc-600 dark:text-white/70">
                                            <div class="absolute inset-2 animate-spin rounded-full border-2 border-zinc-200 border-t-zinc-900 dark:border-white/15 dark:border-t-white"></div>
                                            <x-iconsax-two-magic-star class="size-5" />
                                        </div>
                                    @else
                                        <div class="flex size-16 items-center justify-center text-red-500 dark:text-red-200">
                                            <x-iconsax-two-danger class="size-6" />
                                        </div>
                                    @endif
                                </button>
                                <div class="min-w-0">
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">#{{ $image->id }}</div>
                                    @if ($image->category)
                                        <flux:text class="text-xs" variant="subtle">{{ $image->category->name }}</flux:text>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="max-w-sm">
                            <button class="block w-full text-left" type="button" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }} })">
                                <div class="line-clamp-2 font-medium">{{ $image->title ?: $image->prompt }}</div>
                            </button>
                            @if ($image->status === 'pending')
                                <flux:text class="mt-1 text-xs" variant="subtle">{{ $this->progressLabel($image) }}</flux:text>
                                <div class="mt-2 h-1 w-full max-w-40 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10" role="progressbar" aria-valuemin="1" aria-valuemax="4" aria-valuenow="{{ $progressStep }}" aria-label="{{ $this->progressLabel($image) }}">
                                    <div class="h-full animate-pulse rounded-full bg-zinc-900 transition-[width] dark:bg-white" style="width: {{ $progressStep * 25 }}%"></div>
                                </div>
                            @elseif ($image->status !== 'failed')
                                <flux:text class="mt-1 line-clamp-1 text-xs" variant="subtle">{{ $image->model }}</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1">
                                <flux:badge size="sm" :color="$image->status === 'failed' ? 'red' : ($image->status === 'pending' ? 'amber' : null)">
                                    {{ $this->statusLabel($image) }}
                                </flux:badge>
                                @if ($image->status === 'failed' && ($errorCode = $this->errorCode($image)))
                                    <flux:text class="block text-xs text-red-600 dark:text-red-300">{{ $errorCode }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-xs" variant="subtle">{{ $sizeLabel ?: '—' }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="whitespace-nowrap text-sm">{{ $image->created_at?->format('Y-m-d H:i') }}</div>
                            <flux:text class="text-xs" variant="subtle">{{ $image->created_at?->diffForHumans() }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                @if ($image->status === 'pending')
                                    <flux:button type="button" size="sm" variant="danger" icon="stop" wire:click="cancelPending({{ $image->id }})" wire:confirm="{{ __('Cancel image creation?') }}" wire:loading.attr="disabled" wire:target="cancelPending({{ $image->id }})">{{ __('Stop') }}</flux:button>
                                @elseif ($image->status === 'succeeded' && $image->result_path)
                                    @php($publishError = is_string(data_get($image->response_meta, 'publish_error')) ? data_get($image->response_meta, 'publish_error') : null)
                                    @php($publishDisabled = $publishError && ! auth()->user()?->isAdmin())
                                    @if ($publishDisabled)
                                        <flux:tooltip :content="$publishError">
                                            <div>
                                                <flux:button type="button" size="sm" variant="primary" disabled>{{ __('Publish') }}</flux:button>
                                            </div>
                                        </flux:tooltip>
                                    @else
                                        <flux:button type="button" size="sm" :variant="$image->is_published ? 'danger' : 'primary'" wire:click="togglePublish({{ $image->id }})">
                                            {{ $image->is_published ? __('Unpublish') : __('Publish') }}
                                        </flux:button>
                                    @endif
                                @endif

                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions')" />
                                    <flux:menu>
                                        @if ($originalUrl)
                                            <flux:menu.item :href="$originalUrl" download="{{ $image->downloadName() }}" icon="arrow-down-tray">{{ __('Download') }}</flux:menu.item>
                                        @endif
                                        <flux:menu.item as="button" type="button" variant="danger" icon="trash" wire:click="deleteImage({{ $image->id }})" wire:confirm="{{ __('Delete this image?') }}">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
