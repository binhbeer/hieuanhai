<?php

use App\Models\GeneratedMedia;
use App\Models\Category;
use App\Models\User;
use App\Services\GeneratedMediaService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manage created images')] class extends Component {
	use WithPagination;

	public string $search = '';

	public string $publish = 'all';

	public string $status = 'all';

	public string $categoryId = 'all';

	public string $creatorId = 'all';

	public function mount(): void
	{
		abort_unless(auth()->user()?->isAdmin(), 403);
	}

	public function updatedSearch(): void
	{
		$this->resetPage();
	}

	public function updatedPublish(): void
	{
		$this->resetPage();
	}

	public function updatedStatus(): void
	{
		$this->resetPage();
	}

	public function updatedCategoryId(): void
	{
		$this->resetPage();
	}

	public function updatedCreatorId(): void
	{
		$this->resetPage();
	}

	public function resetFilters(): void
	{
		$this->reset('search', 'publish', 'status', 'categoryId', 'creatorId');
		$this->resetPage();
	}

	public function updateCategory(int $id, int|string|null $categoryId): void
	{
		$image = GeneratedMedia::query()->findOrFail($id);
		$categoryId = ctype_digit((string) $categoryId) ? (int) $categoryId : null;

		if ($categoryId && !Category::query()->whereKey($categoryId)->exists()) {
			return;
		}

		$image->update(['category_id' => $categoryId]);
		unset($this->images);

		Flux::toast(variant: 'success', text: __('Category updated.'));
	}

	public function publishImage(int $id, GeneratedMediaService $editor): void
	{
		$image = GeneratedMedia::query()->findOrFail($id);

		try {
			$editor->publish($image, request(), requireOwner: false);
		} catch (InvalidArgumentException $e) {
			Flux::toast(text: $e->getMessage());

			return;
		}

		$this->refreshData();
		Flux::toast(variant: 'success', text: __('Image published.'));
	}

	public function unpublishImage(int $id): void
	{
		GeneratedMedia::query()->findOrFail($id)->update(['is_published' => false]);

		$this->refreshData();
		Flux::toast(variant: 'success', text: __('Image unpublished.'));
	}

	#[Computed]
	public function categories()
	{
		return Category::query()->ordered()->get();
	}

	#[Computed]
	public function creators()
	{
		return User::query()
			->whereIn('id', GeneratedMedia::query()->select('user_id')->whereNotNull('user_id'))
			->orderBy('name')
			->get();
	}

	#[Computed]
	public function stats(): array
	{
		return [
			'total' => GeneratedMedia::query()->count(),
			'published' => GeneratedMedia::query()->where('is_published', true)->count(),
			'unpublished' => GeneratedMedia::query()
				->where('is_published', false)
				->where('status', 'succeeded')
				->whereNotNull('result_path')
				->count(),
			'failed' => GeneratedMedia::query()->where('status', 'failed')->count(),
		];
	}

	#[Computed]
	public function dailyStats(): array
	{
		$from = today()->subDays(29);
		$images = GeneratedMedia::query()
			->where('created_at', '>=', $from)
			->selectRaw("DATE(created_at) as date, COUNT(*) as total, SUM(is_published = 1) as published, SUM(is_published = 0 AND status = 'succeeded' AND result_path IS NOT NULL) as unpublished, SUM(status = 'failed') as failed")
			->groupByRaw('DATE(created_at)')
			->get()
			->keyBy('date');

		return collect(range(0, 29))->map(function (int $offset) use ($from, $images): array {
			$date = $from->copy()->addDays($offset);
			$image = $images->get($date->toDateString());

			return [
				'date' => $date,
				'total' => (int) ($image->total ?? 0),
				'published' => (int) ($image->published ?? 0),
				'unpublished' => (int) ($image->unpublished ?? 0),
				'failed' => (int) ($image->failed ?? 0),
			];
		})->all();
	}

	#[Computed]
	public function images()
	{
		return GeneratedMedia::query()
			->with(['category', 'user'])
			->when($this->search !== '', function ($query): void {
				$search = trim($this->search);
				$like = '%' . $search . '%';

				$query->where(function ($query) use ($search, $like): void {
					$query->where('title->vi', 'like', $like)
						->orWhere('prompt', 'like', $like)
						->orWhere('custom_prompt', 'like', $like)
						->orWhere('visitor_key', 'like', $like)
						->orWhereHas('user', function ($query) use ($like): void {
							$query->where('name', 'like', $like)
								->orWhere('email', 'like', $like);
						});

					if (ctype_digit($search)) {
						$query->orWhereKey((int) $search);
					}
				});
			})
			->when($this->publish === 'published', fn($query) => $query->where('is_published', true))
			->when($this->publish === 'unpublished', fn($query) => $query->where('is_published', false))
			->when($this->status !== 'all', fn($query) => $query->where('status', $this->status))
			->when($this->categoryId === 'none', fn($query) => $query->whereNull('category_id'))
			->when(ctype_digit($this->categoryId), fn($query) => $query->where('category_id', (int) $this->categoryId))
			->when($this->creatorId === 'guest', fn($query) => $query->whereNull('user_id'))
			->when(ctype_digit($this->creatorId), fn($query) => $query->where('user_id', (int) $this->creatorId))
			->latest()
			->paginate(20);
	}

	public function imageUrl(GeneratedMedia $image, string $size = 'original'): ?string
	{
		return app(GeneratedMediaService::class)->imageUrl($image, $size);
	}

	public function imageSize(GeneratedMedia $image, string $size = 'original'): ?array
	{
		return app(GeneratedMediaService::class)->imageSize($image, $size);
	}

	private function refreshData(): void
	{
		unset($this->images, $this->stats, $this->dailyStats);
	}
}; ?>

<section class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div class="space-y-1">
			<flux:heading size="xl">{{ __('Manage created images') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Filter, categorize, publish, or unpublish AI images.') }}</flux:text>
		</div>
		<flux:button :href="route('manage.index')" variant="filled" wire:navigate>{{ __('Manage') }}</flux:button>
	</div>

	<div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
		<flux:card class="h-full p-3! sm:p-5!">
			<div class="flex items-start justify-between gap-4">
				<div class="space-y-3">
					<div class="flex size-9 items-center justify-center rounded-xl bg-zinc-500/10 text-zinc-600 sm:size-11 sm:rounded-2xl dark:text-zinc-300">
						<x-iconsax-two-gallery class="size-5" />
					</div>
					<div>
						<flux:text variant="subtle">{{ __('Total images') }}</flux:text>
						<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['total']) }}</div>
					</div>
				</div>
			</div>
			<flux:text class="mt-3 hidden text-sm! sm:mt-5 sm:block" variant="subtle">{{ __('All created images in the system.') }}</flux:text>
		</flux:card>

		<flux:card class="h-full p-3! sm:p-5!">
			<div class="flex items-start justify-between gap-4">
				<div class="space-y-3">
					<div class="flex size-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 sm:size-11 sm:rounded-2xl dark:text-emerald-300">
						<x-iconsax-two-tick-circle class="size-5" />
					</div>
					<div>
						<flux:text variant="subtle">{{ __('Published') }}</flux:text>
						<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['published']) }}</div>
					</div>
				</div>
			</div>
			@php($publishedShare = $this->stats['total'] > 0 ? round($this->stats['published'] / $this->stats['total'] * 100) : 0)
			<flux:badge class="mt-3 max-w-full sm:mt-5" color="emerald" size="sm">
				{{ __(':percent of total', ['percent' => $publishedShare.'%']) }}
			</flux:badge>
		</flux:card>

		<flux:card class="h-full p-3! sm:p-5!">
			<div class="flex items-start justify-between gap-4">
				<div class="space-y-3">
					<div class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 sm:size-11 sm:rounded-2xl dark:text-amber-300">
						<x-iconsax-two-clock class="size-5" />
					</div>
					<div>
						<flux:text variant="subtle">{{ __('Unpublished') }}</flux:text>
						<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['unpublished']) }}</div>
					</div>
				</div>
			</div>
			<flux:badge class="mt-3 max-w-full sm:mt-5" :color="$this->stats['unpublished'] > 0 ? 'amber' : 'zinc'" size="sm">
				{{ __(':count awaiting publication', ['count' => number_format($this->stats['unpublished'])]) }}
			</flux:badge>
		</flux:card>

		<flux:card class="h-full p-3! sm:p-5!">
			<div class="flex items-start justify-between gap-4">
				<div class="space-y-3">
					<div class="flex size-9 items-center justify-center rounded-xl bg-red-500/10 text-red-600 sm:size-11 sm:rounded-2xl dark:text-red-300">
						<x-iconsax-two-close-circle class="size-5" />
					</div>
					<div>
						<flux:text variant="subtle">{{ __('Failed') }}</flux:text>
						<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['failed']) }}</div>
					</div>
				</div>
			</div>
			<flux:badge class="mt-3 max-w-full sm:mt-5" :color="$this->stats['failed'] > 0 ? 'red' : 'zinc'" size="sm">
				{{ __(':count failed generations', ['count' => number_format($this->stats['failed'])]) }}
			</flux:badge>
		</flux:card>
	</div>

	@php($dailyStats = $this->dailyStats)
	@php($maxDailyImages = max(1, collect($dailyStats)->max('total')))
	@php($periodImages = collect($dailyStats)->sum('total'))
	@php($periodPublished = collect($dailyStats)->sum('published'))
	@php($periodUnpublished = collect($dailyStats)->sum('unpublished'))
	@php($periodFailed = collect($dailyStats)->sum('failed'))

	<flux:card class="space-y-5">
		<div class="flex items-start justify-between gap-4">
			<div>
				<flux:text variant="subtle">{{ __('Created images') }}</flux:text>
				<div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($periodImages) }}</div>
				<flux:text class="mt-1 text-xs!" variant="subtle">{{ __('Last 30 days') }}</flux:text>
			</div>
			<div class="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
				<x-iconsax-two-gallery class="size-5" />
			</div>
		</div>

		<div class="flex h-40 items-end gap-1 border-b border-zinc-200 dark:border-white/10" role="img" aria-label="{{ __('Daily published, unpublished, and failed images for the last 30 days') }}">
			@foreach ($dailyStats as $day)
				@php($imageHeight = $day['total'] / $maxDailyImages * 100)
				@php($publishedHeight = $day['total'] > 0 ? $day['published'] / $day['total'] * 100 : 0)
				@php($unpublishedHeight = $day['total'] > 0 ? $day['unpublished'] / $day['total'] * 100 : 0)
				@php($failedHeight = $day['total'] > 0 ? $day['failed'] / $day['total'] * 100 : 0)
				<div class="group relative flex h-full min-w-0 flex-1 items-end" wire:key="daily-manage-images-{{ $day['date']->toDateString() }}">
					<div class="flex w-full flex-col-reverse overflow-hidden rounded-t bg-zinc-200 dark:bg-zinc-800" style="height: {{ $day['total'] > 0 ? max(4, $imageHeight) : 0 }}%">
						<div class="bg-emerald-500" style="height: {{ $publishedHeight }}%"></div>
						<div class="bg-amber-500" style="height: {{ $unpublishedHeight }}%"></div>
						<div class="bg-red-500" style="height: {{ $failedHeight }}%"></div>
					</div>
					<div class="pointer-events-none absolute bottom-full inset-s-1/2 z-10 mb-2 hidden w-max max-w-56 -translate-x-1/2 rounded-lg bg-zinc-950 px-2 py-1.5 text-xs text-white shadow-lg group-hover:block group-focus-within:block">
						{{ $day['date']->format('d/m') }} · {{ __(':published published, :unpublished unpublished, :failed failed', ['published' => number_format($day['published']), 'unpublished' => number_format($day['unpublished']), 'failed' => number_format($day['failed'])]) }}
					</div>
				</div>
			@endforeach
		</div>

		<div class="flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400">
			<span>{{ $dailyStats[0]['date']->format('d/m') }}</span>
			<span>{{ __('30 days') }}</span>
			<span>{{ $dailyStats[29]['date']->format('d/m') }}</span>
		</div>
		<div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
			<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-emerald-500"></span>{{ __('Published') }} · {{ number_format($periodPublished) }}</span>
			<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-amber-500"></span>{{ __('Unpublished') }} · {{ number_format($periodUnpublished) }}</span>
			<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-red-500"></span>{{ __('Failed') }} · {{ number_format($periodFailed) }}</span>
		</div>
	</flux:card>

	<flux:card class="space-y-4">
		<div class="grid gap-3 lg:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_11rem_11rem_13rem_16rem_auto]">
			<flux:input wire:model.live.debounce.300ms="search" :label="__('Search images')" placeholder="ID, prompt, user, visitor key" />
			<flux:select wire:model.live="publish" variant="listbox" :label="__('Publish')">
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="published">{{ __('Published') }}</flux:select.option>
				<flux:select.option value="unpublished">{{ __('Unpublished') }}</flux:select.option>
			</flux:select>
			<flux:select wire:model.live="status" variant="listbox" :label="__('Creation status')">
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="succeeded">{{ __('Succeeded') }}</flux:select.option>
				<flux:select.option value="pending">{{ __('Creating') }}</flux:select.option>
				<flux:select.option value="failed">{{ __('Failed') }}</flux:select.option>
			</flux:select>
			<flux:select wire:model.live="categoryId" variant="listbox" :label="__('Category')">
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="none">{{ __('Uncategorized') }}</flux:select.option>
				@foreach ($this->categories as $category)
					<flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
				@endforeach
			</flux:select>
			<flux:select wire:model.live="creatorId" variant="listbox" searchable :label="__('Image creator')">
				<x-slot name="search">
					<flux:select.search :placeholder="__('Search users...')" />
				</x-slot>
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="guest">{{ __('Guest') }}</flux:select.option>
				@foreach ($this->creators as $creator)
					<flux:select.option value="{{ $creator->id }}" wire:key="creator-filter-{{ $creator->id }}">
						<div class="flex items-center gap-2 whitespace-nowrap">
							<flux:avatar size="xs" circle :name="$creator->name" :initials="$creator->initials()" :src="$creator->avatar_path ? Storage::url($creator->avatar_path) : null" />
							<div class="min-w-0">
								<div class="truncate">{{ $creator->name }}</div>
								<div class="truncate text-xs text-zinc-500">{{ $creator->email }}</div>
							</div>
						</div>
					</flux:select.option>
				@endforeach
			</flux:select>
			<div class="flex items-end">
				<flux:button type="button" variant="ghost" wire:click="resetFilters">Reset</flux:button>
			</div>
		</div>
	</flux:card>

	<flux:card class="space-y-4">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<flux:heading size="lg">{{ __('Image list') }}</flux:heading>
			<flux:text variant="subtle">{{ __(':count images', ['count' => number_format($this->images->total())]) }}</flux:text>
		</div>

		<div class="overflow-x-auto">
			<table class="w-full min-w-6xl text-left text-sm">
				<thead class="text-zinc-400">
					<tr class="border-b border-white/10">
						<th class="px-3 py-2 font-medium">{{ __('Image') }}</th>
						<th class="px-3 py-2 font-medium">Prompt</th>
						<th class="px-3 py-2 font-medium">User</th>
						<th class="px-3 py-2 font-medium">{{ __('Category') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Time') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Actions') }}</th>
					</tr>
				</thead>
				<tbody>
					@forelse ($this->images as $image)
					@php($url = $this->imageUrl($image, 'xs'))
					@php($imageSize = $this->imageSize($image, 'xs'))
					<tr class="border-b border-white/10" wire:key="manage-image-{{ $image->id }}">
						<td class="px-3 py-3 align-top">
							<button class="block text-left" type="button" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }}, preview: @js($url) })" aria-label="{{ __('View image details') }}">
								@if ($url)
									<img class="size-20 rounded-xl bg-white/5 object-cover" src="{{ $url }}" alt="{{ Str::limit($image->title ?: __('Image #:id', ['id' => $image->id]), 80) }}" @if ($imageSize) width="{{ $imageSize['width'] }}" height="{{ $imageSize['height'] }}" @endif loading="lazy" />
								@else
									<div class="flex size-20 items-center justify-center rounded-xl bg-white/5 text-zinc-500">
										<x-iconsax-two-gallery class="size-6" />
									</div>
								@endif
							</button>
							<div class="mt-2 text-xs text-zinc-400">#{{ $image->id }}</div>
						</td>
						<td class="max-w-md px-3 py-3 align-top">
							<div class="line-clamp-3 font-medium">{{ $image->title ?: $image->prompt }}</div>
							@if ($image->custom_prompt)
								<flux:text class="mt-1 line-clamp-2 text-xs" variant="subtle">{{ $image->custom_prompt }}</flux:text>
							@endif
							<flux:text class="mt-2 text-xs" variant="subtle">{{ $image->provider }} · {{ $image->model }}</flux:text>
						</td>
						<td class="px-3 py-3 align-top">
							<div>{{ $image->user?->name ?? 'Guest' }}</div>
							<flux:text class="text-xs" variant="subtle">{{ $image->user?->email ?? Str::limit($image->visitor_key, 12) }}</flux:text>
						</td>
						<td class="w-56 px-3 py-3 align-top">
							<flux:select size="sm" variant="listbox" :label="__('Category')" wire:change="updateCategory({{ $image->id }}, $event.target.value)">
								<flux:select.option value="none" :selected="$image->category_id === null">{{ __('Uncategorized') }}</flux:select.option>
								@foreach ($this->categories as $category)
									<flux:select.option value="{{ $category->id }}" :selected="$image->category_id === $category->id">{{ $category->name }}</flux:select.option>
								@endforeach
							</flux:select>
						</td>
						<td class="space-y-2 px-3 py-3 align-top">
							<div>
								@if ($image->is_published)
									<flux:badge size="sm">Published</flux:badge>
								@else
									<flux:badge size="sm">Unpublish</flux:badge>
								@endif
							</div>
							<flux:text class="text-xs" variant="subtle">{{ $image->status }}</flux:text>
							@if ($image->error)
								<div class="max-w-48 text-xs text-red-300">{{ Str::limit($image->error, 100) }}</div>
							@endif
						</td>
						<td class="px-3 py-3 align-top">
							<div>{{ __('Created:') }} {{ $image->created_at?->format('Y-m-d H:i') }}</div>
							<flux:text class="text-xs" variant="subtle">{{ __('Publish:') }} {{ $image->published_at?->format('Y-m-d H:i') ?? __('Never') }}</flux:text>
						</td>
						<td class="px-3 py-3 align-top">
							<div class="flex flex-wrap gap-2">
								<flux:button type="button" size="sm" variant="filled" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }} })">{{ __('Open') }}</flux:button>
								@if ($image->is_published)
									<flux:button type="button" size="sm" variant="danger" wire:click="unpublishImage({{ $image->id }})" wire:confirm="{{ __('Unpublish this image?') }}">Unpublish</flux:button>
								@else
									<flux:button type="button" size="sm" variant="primary" wire:click="publishImage({{ $image->id }})" :disabled="$image->status !== 'succeeded' || ! $image->result_path">Publish</flux:button>
								@endif
							</div>
						</td>
					</tr>
					@empty
					<tr>
						<td class="px-3 py-6 text-center text-zinc-400" colspan="7">{{ __('No images.') }}</td>
					</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div>{{ $this->images->links() }}</div>
	</flux:card>
</section>