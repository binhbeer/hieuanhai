<?php

use App\Models\GeneratedMedia;
use App\Models\StudioProject;
use App\Models\User;
use App\Services\GeneratedMediaService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manage AI Studio')] class extends Component {
	use WithPagination;

	public string $search = '';

	public string $tool = 'all';

	public string $status = 'all';

	public string $creatorId = 'all';

	public function mount(): void
	{
		abort_unless(auth()->user()?->isAdmin(), 403);
	}

	public function updatedSearch(): void
	{
		$this->resetPage();
	}

	public function updatedTool(): void
	{
		$this->resetPage();
	}

	public function updatedStatus(): void
	{
		$this->resetPage();
	}

	public function updatedCreatorId(): void
	{
		$this->resetPage();
	}

	public function resetFilters(): void
	{
		$this->reset('search', 'tool', 'status', 'creatorId');
		$this->resetPage();
	}

	#[Computed]
	public function creators()
	{
		return User::query()
			->whereIn('id', StudioProject::query()->select('user_id'))
			->orderBy('name')
			->get();
	}

	#[Computed]
	public function stats(): array
	{
		return [
			'total' => StudioProject::query()->count(),
			'draft' => StudioProject::query()->whereNull('submitted_at')->count(),
			'submitted' => StudioProject::query()->whereNotNull('submitted_at')->count(),
			'media' => GeneratedMedia::query()->whereNotNull('studio_project_id')->count(),
		];
	}

	#[Computed]
	public function dailyStats(): array
	{
		$from = today()->subDays(29);
		$projects = StudioProject::query()
			->where('created_at', '>=', $from)
			->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(submitted_at IS NULL) as draft, SUM(submitted_at IS NOT NULL) as submitted')
			->groupByRaw('DATE(created_at)')
			->get()
			->keyBy('date');

		return collect(range(0, 29))->map(function (int $offset) use ($from, $projects): array {
			$date = $from->copy()->addDays($offset);
			$project = $projects->get($date->toDateString());

			return [
				'date' => $date,
				'total' => (int) ($project->total ?? 0),
				'draft' => (int) ($project->draft ?? 0),
				'submitted' => (int) ($project->submitted ?? 0),
			];
		})->all();
	}

	#[Computed]
	public function projects()
	{
		return StudioProject::query()
			->with([
				'user',
				'media' => fn ($query) => $query->latest(),
			])
			->withCount([
				'media',
				'media as pending_media_count' => fn ($query) => $query->where('status', 'pending'),
			])
			->when($this->search !== '', function ($query): void {
				$search = trim($this->search);
				$like = '%' . $search . '%';

				$query->where(function ($query) use ($search, $like): void {
					$query->where('name', 'like', $like)
						->orWhere('tool', 'like', $like)
						->orWhereHas('user', function ($query) use ($like): void {
							$query->where('name', 'like', $like)
								->orWhere('email', 'like', $like);
						});

					if (ctype_digit($search)) {
						$query->orWhere('id', (int) $search);
					}
				});
			})
			->when($this->tool !== 'all', fn ($query) => $query->where('tool', $this->tool))
			->when($this->status === 'draft', fn ($query) => $query->whereNull('submitted_at'))
			->when($this->status === 'submitted', fn ($query) => $query->whereNotNull('submitted_at'))
			->when($this->status === 'creating', function ($query): void {
				$query->whereNotNull('submitted_at')
					->whereHas('media', fn ($media) => $media->where('status', 'pending'));
			})
			->when($this->status === 'completed', function ($query): void {
				$query->whereNotNull('submitted_at')
					->whereHas('media')
					->whereDoesntHave('media', fn ($media) => $media->where('status', 'pending'));
			})
			->when(ctype_digit($this->creatorId), fn ($query) => $query->where('user_id', (int) $this->creatorId))
			->latest('updated_at')
			->paginate(20);
	}

	public function toolLabel(string $tool): string
	{
		return match ($tool) {
			'product-detail' => __('Product detail images'),
			'marketing-poster' => __('Marketing poster'),
			default => $tool,
		};
	}

	public function projectProgress(StudioProject $project): string
	{
		if ($project->submitted_at === null) {
			return __('Draft');
		}

		$total = $project->media->count();
		$done = $project->media->whereIn('status', ['succeeded', 'failed'])->count();

		if ($total === 0) {
			return __('Submitted');
		}

		return $done === $total ? __('Completed') : __('Creating :done/:total', ['done' => $done, 'total' => $total]);
	}

	public function imageUrl(GeneratedMedia $image, string $size = 'xs'): ?string
	{
		return app(GeneratedMediaService::class)->imageUrl($image, $size);
	}

	public function imageSize(GeneratedMedia $image, string $size = 'xs'): ?array
	{
		return app(GeneratedMediaService::class)->imageSize($image, $size);
	}
}; ?>

<section class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div class="space-y-1">
			<flux:heading size="xl">{{ __('Manage AI Studio') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Filter and review Studio AI projects.') }}</flux:text>
		</div>
		<flux:button :href="route('manage.index')" variant="filled" wire:navigate>{{ __('Manage') }}</flux:button>
	</div>

	<div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
		<flux:card class="h-full p-3! sm:p-5!">
			<div class="flex items-start justify-between gap-4">
				<div class="space-y-3">
					<div class="flex size-9 items-center justify-center rounded-xl bg-zinc-500/10 text-zinc-600 sm:size-11 sm:rounded-2xl dark:text-zinc-300">
						<x-iconsax-two-star class="size-5" />
					</div>
					<div>
						<flux:text variant="subtle">{{ __('Total projects') }}</flux:text>
						<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['total']) }}</div>
					</div>
				</div>
			</div>
			<flux:text class="mt-3 hidden text-sm! sm:mt-5 sm:block" variant="subtle">{{ __('All Studio AI projects in the system.') }}</flux:text>
		</flux:card>

		<flux:card class="h-full p-3! sm:p-5!">
			<div class="flex items-start justify-between gap-4">
				<div class="space-y-3">
					<div class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 sm:size-11 sm:rounded-2xl dark:text-amber-300">
						<x-iconsax-two-clock class="size-5" />
					</div>
					<div>
						<flux:text variant="subtle">{{ __('Draft projects') }}</flux:text>
						<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['draft']) }}</div>
					</div>
				</div>
			</div>
			<flux:badge class="mt-3 max-w-full sm:mt-5" :color="$this->stats['draft'] > 0 ? 'amber' : 'zinc'" size="sm">
				{{ __(':count drafts', ['count' => number_format($this->stats['draft'])]) }}
			</flux:badge>
		</flux:card>

		<flux:card class="h-full p-3! sm:p-5!">
			<div class="flex items-start justify-between gap-4">
				<div class="space-y-3">
					<div class="flex size-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 sm:size-11 sm:rounded-2xl dark:text-emerald-300">
						<x-iconsax-two-tick-circle class="size-5" />
					</div>
					<div>
						<flux:text variant="subtle">{{ __('Submitted projects') }}</flux:text>
						<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['submitted']) }}</div>
					</div>
				</div>
			</div>
			@php($submittedShare = $this->stats['total'] > 0 ? round($this->stats['submitted'] / $this->stats['total'] * 100) : 0)
			<flux:badge class="mt-3 max-w-full sm:mt-5" color="emerald" size="sm">
				{{ __(':percent of total', ['percent' => $submittedShare.'%']) }}
			</flux:badge>
		</flux:card>

		<flux:card class="h-full p-3! sm:p-5!">
			<div class="flex items-start justify-between gap-4">
				<div class="space-y-3">
					<div class="flex size-9 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 sm:size-11 sm:rounded-2xl dark:text-blue-300">
						<x-iconsax-two-gallery class="size-5" />
					</div>
					<div>
						<flux:text variant="subtle">{{ __('Studio media') }}</flux:text>
						<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['media']) }}</div>
					</div>
				</div>
			</div>
			<flux:text class="mt-3 hidden text-sm! sm:mt-5 sm:block" variant="subtle">{{ __('Images generated from Studio projects.') }}</flux:text>
		</flux:card>
	</div>

	@php($dailyStats = $this->dailyStats)
	@php($maxDailyProjects = max(1, collect($dailyStats)->max('total')))
	@php($periodProjects = collect($dailyStats)->sum('total'))
	@php($periodDraft = collect($dailyStats)->sum('draft'))
	@php($periodSubmitted = collect($dailyStats)->sum('submitted'))

	<flux:card class="space-y-5">
		<div class="flex items-start justify-between gap-4">
			<div>
				<flux:text variant="subtle">{{ __('Created projects') }}</flux:text>
				<div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($periodProjects) }}</div>
				<flux:text class="mt-1 text-xs!" variant="subtle">{{ __('Last 30 days') }}</flux:text>
			</div>
			<div class="flex size-10 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-300">
				<x-iconsax-two-star class="size-5" />
			</div>
		</div>

		<div class="flex h-40 items-end gap-1 border-b border-zinc-200 dark:border-white/10" role="img" aria-label="{{ __('Daily draft and submitted Studio projects for the last 30 days') }}">
			@foreach ($dailyStats as $day)
				@php($projectHeight = $day['total'] / $maxDailyProjects * 100)
				@php($draftHeight = $day['total'] > 0 ? $day['draft'] / $day['total'] * 100 : 0)
				@php($submittedHeight = $day['total'] > 0 ? $day['submitted'] / $day['total'] * 100 : 0)
				<div class="group relative flex h-full min-w-0 flex-1 items-end" wire:key="daily-manage-studio-{{ $day['date']->toDateString() }}">
					<div class="flex w-full flex-col-reverse overflow-hidden rounded-t bg-zinc-200 dark:bg-zinc-800" style="height: {{ $day['total'] > 0 ? max(4, $projectHeight) : 0 }}%">
						<div class="bg-amber-500" style="height: {{ $draftHeight }}%"></div>
						<div class="bg-emerald-500" style="height: {{ $submittedHeight }}%"></div>
					</div>
					<div class="pointer-events-none absolute bottom-full inset-s-1/2 z-10 mb-2 hidden w-max max-w-56 -translate-x-1/2 rounded-lg bg-zinc-950 px-2 py-1.5 text-xs text-white shadow-lg group-hover:block group-focus-within:block">
						{{ $day['date']->format('d/m') }} · {{ __(':draft drafts, :submitted submitted', ['draft' => number_format($day['draft']), 'submitted' => number_format($day['submitted'])]) }}
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
			<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-amber-500"></span>{{ __('Draft') }} · {{ number_format($periodDraft) }}</span>
			<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-emerald-500"></span>{{ __('Submitted') }} · {{ number_format($periodSubmitted) }}</span>
		</div>
	</flux:card>

	<flux:card class="space-y-4">
		<div class="grid gap-3 lg:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_13rem_13rem_16rem_auto]">
			<flux:input wire:model.live.debounce.300ms="search" :label="__('Search projects')" placeholder="ID, name, user" />
			<flux:select wire:model.live="tool" variant="listbox" :label="__('Tool')">
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="product-detail">{{ __('Product detail images') }}</flux:select.option>
				<flux:select.option value="marketing-poster">{{ __('Marketing poster') }}</flux:select.option>
			</flux:select>
			<flux:select wire:model.live="status" variant="listbox" :label="__('Project status')">
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
				<flux:select.option value="submitted">{{ __('Submitted') }}</flux:select.option>
				<flux:select.option value="creating">{{ __('Creating') }}</flux:select.option>
				<flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
			</flux:select>
			<flux:select wire:model.live="creatorId" variant="listbox" searchable :label="__('Image creator')">
				<x-slot name="search">
					<flux:select.search :placeholder="__('Search users...')" />
				</x-slot>
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				@foreach ($this->creators as $creator)
					<flux:select.option value="{{ $creator->id }}" wire:key="studio-creator-filter-{{ $creator->id }}">
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
				<flux:button type="button" variant="ghost" wire:click="resetFilters">{{ __('Reset') }}</flux:button>
			</div>
		</div>
	</flux:card>

	<flux:card class="space-y-4">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<flux:heading size="lg">{{ __('Project list') }}</flux:heading>
			<flux:text variant="subtle">{{ __(':count projects', ['count' => number_format($this->projects->total())]) }}</flux:text>
		</div>

		<div class="overflow-x-auto">
			<table class="w-full min-w-5xl text-left text-sm">
				<thead class="text-zinc-400">
					<tr class="border-b border-white/10">
						<th class="px-3 py-2 font-medium">{{ __('Project') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Tool') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('User') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Media') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Time') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Actions') }}</th>
					</tr>
				</thead>
				<tbody>
					@forelse ($this->projects as $project)
						@php($previewMedia = $project->media->where('status', 'succeeded')->whereNotNull('result_path')->take(3))
						@php($firstMedia = $previewMedia->first() ?? $project->media->first())
						@php($firstUrl = $firstMedia ? $this->imageUrl($firstMedia, 'xs') : null)
						<tr class="border-b border-white/10" wire:key="manage-studio-{{ $project->id }}">
							<td class="px-3 py-3 align-top">
								<div class="font-medium">{{ $project->name }}</div>
								<div class="mt-1 text-xs text-zinc-400">#{{ $project->id }}</div>
							</td>
							<td class="px-3 py-3 align-top">
								{{ $this->toolLabel($project->tool) }}
							</td>
							<td class="px-3 py-3 align-top">
								<div>{{ $project->user?->name ?? __('Guest') }}</div>
								<flux:text class="text-xs" variant="subtle">{{ $project->user?->email }}</flux:text>
							</td>
							<td class="px-3 py-3 align-top">
								<flux:badge size="sm">{{ $this->projectProgress($project) }}</flux:badge>
							</td>
							<td class="px-3 py-3 align-top">
								<div class="flex items-center gap-2">
									@foreach ($previewMedia as $image)
										@php($url = $this->imageUrl($image, 'xs'))
										@php($imageSize = $this->imageSize($image, 'xs'))
										@if ($url)
											<button class="block" type="button" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $image->id }}, preview: @js($url) })" aria-label="{{ __('View image details') }}">
												<img class="size-12 rounded-lg bg-white/5 object-cover" src="{{ $url }}" alt="{{ Str::limit($image->title ?: $project->name, 80) }}" @if ($imageSize) width="{{ $imageSize['width'] }}" height="{{ $imageSize['height'] }}" @endif loading="lazy" />
											</button>
										@endif
									@endforeach
									@if ($previewMedia->isEmpty())
										<div class="flex size-12 items-center justify-center rounded-lg bg-white/5 text-zinc-500">
											<x-iconsax-two-gallery class="size-5" />
										</div>
									@endif
								</div>
								<flux:text class="mt-2 text-xs" variant="subtle">{{ __(':count images', ['count' => number_format($project->media_count)]) }}</flux:text>
							</td>
							<td class="px-3 py-3 align-top">
								<div>{{ __('Created:') }} {{ $project->created_at?->format('Y-m-d H:i') }}</div>
								<flux:text class="text-xs" variant="subtle">{{ __('Submitted:') }} {{ $project->submitted_at?->format('Y-m-d H:i') ?? __('Never') }}</flux:text>
							</td>
							<td class="px-3 py-3 align-top">
								@if ($firstMedia)
									<flux:button type="button" size="sm" variant="filled" x-data x-on:click="$dispatch('open-image-detail', { id: {{ $firstMedia->id }}, preview: @js($firstUrl) })">{{ __('Open') }}</flux:button>
								@else
									<flux:button type="button" size="sm" variant="filled" disabled>{{ __('Open') }}</flux:button>
								@endif
							</td>
						</tr>
					@empty
						<tr>
							<td class="px-3 py-6 text-center text-zinc-400" colspan="7">{{ __('No projects.') }}</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div>{{ $this->projects->links() }}</div>
	</flux:card>
</section>
