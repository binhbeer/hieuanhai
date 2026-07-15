<?php

use App\Models\GeneratedMedia;
use App\Models\Category;
use App\Services\AiImageEditor;
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

	public function resetFilters(): void
	{
		$this->reset('search', 'publish', 'status', 'categoryId');
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

	public function publishImage(int $id, AiImageEditor $editor): void
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
		return Category::query()->orderBy('sort_order')->orderBy('name')->get();
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
	public function images()
	{
		return GeneratedMedia::query()
			->with(['category', 'user'])
			->when($this->search !== '', function ($query): void {
				$search = trim($this->search);
				$like = '%' . $search . '%';

				$query->where(function ($query) use ($search, $like): void {
					$query->where('title', 'like', $like)
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
			->latest()
			->paginate(20);
	}

	public function imageUrl(GeneratedMedia $image, string $size = 'original'): ?string
	{
		return app(AiImageEditor::class)->imageUrl($image, $size);
	}

	public function imageSize(GeneratedMedia $image, string $size = 'original'): ?array
	{
		return app(AiImageEditor::class)->imageSize($image, $size);
	}

	private function refreshData(): void
	{
		unset($this->images, $this->stats);
	}
}; ?>

<section class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div class="space-y-1">
			<flux:heading size="xl">{{ __('Manage created images') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Filter, categorize, publish, or unpublish AI images.') }}</flux:text>
		</div>
		<flux:button :href="route('manage.index')" variant="filled" wire:navigate>{{ __('Manage') }}</flux:button>
	</div>

	<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">{{ __('Total images') }}</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['total']) }}</div>
		</div>
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">Published</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['published']) }}</div>
		</div>
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">Unpublish</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['unpublished']) }}</div>
		</div>
		<div class="rounded-xl bg-white/5 p-4">
			<flux:text variant="subtle">Failed</flux:text>
			<div class="text-2xl font-semibold tabular-nums">{{ number_format($this->stats['failed']) }}</div>
		</div>
	</div>

	<flux:card class="space-y-4">
		<div class="grid gap-3 lg:grid-cols-[1fr_12rem_12rem_14rem_auto]">
			<flux:input wire:model.live.debounce.300ms="search" :label="__('Search images')" placeholder="ID, prompt, user, visitor key" />
			<flux:select wire:model.live="publish" label="Publish">
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="published">Published</flux:select.option>
				<flux:select.option value="unpublished">Unpublish</flux:select.option>
			</flux:select>
			<flux:select wire:model.live="status" :label="__('Creation status')">
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="succeeded">Succeeded</flux:select.option>
				<flux:select.option value="pending">Pending</flux:select.option>
				<flux:select.option value="failed">Failed</flux:select.option>
			</flux:select>
			<flux:select wire:model.live="categoryId" :label="__('Category')">
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="none">{{ __('Uncategorized') }}</flux:select.option>
				@foreach ($this->categories as $category)
					<flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
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
							<button class="block text-left" type="button" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }} })" aria-label="{{ __('View image details') }}">
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
							<flux:select size="sm" :label="__('Category')" wire:change="updateCategory({{ $image->id }}, $event.target.value)">
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