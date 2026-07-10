<?php

use App\Models\AiApiKey;
use App\Models\AiImage;
use App\Models\Category;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage')] class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'users' => User::query()->count(),
            'banned_users' => User::query()->whereNotNull('banned_at')->count(),
            'api_keys' => AiApiKey::query()->count(),
            'categories' => Category::query()->count(),
            'published_images' => AiImage::query()->where('is_published', true)->count(),
            'unpublished_images' => AiImage::query()->where('is_published', false)->where('status', 'succeeded')->whereNotNull('result_path')->count(),
        ];
    }

    #[Computed]
    public function dailyStats(): array
    {
        $from = today()->subDays(29);
        $users = User::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(email_verified_at IS NOT NULL) as verified')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');
        $images = AiImage::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(is_published = 1) as published')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');

        return collect(range(0, 29))->map(function (int $offset) use ($from, $users, $images): array {
            $date = $from->copy()->addDays($offset);
            $user = $users->get($date->toDateString());
            $image = $images->get($date->toDateString());

            return [
                'date' => $date,
                'users' => (int) ($user->total ?? 0),
                'verified_users' => (int) ($user->verified ?? 0),
                'images' => (int) ($image->total ?? 0),
                'published_images' => (int) ($image->published ?? 0),
            ];
        })->all();
    }
}; ?>

<section class="mx-auto w-full max-w-7xl space-y-8 p-4 sm:p-6 lg:p-8">
	<header class="relative overflow-hidden rounded-3xl border border-zinc-200 bg-linear-to-br from-white via-white to-violet-50 p-6 shadow-sm dark:border-white/10 dark:from-zinc-950 dark:via-zinc-900 dark:to-violet-950 sm:p-8">
		<div class="pointer-events-none absolute -inset-e-20 -top-24 size-72 rounded-full bg-violet-500/15 blur-3xl" aria-hidden="true"></div>
		<div class="pointer-events-none absolute -bottom-28 inset-s-1/3 size-64 rounded-full bg-blue-500/10 blur-3xl" aria-hidden="true"></div>

		<div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
			<div class="max-w-2xl space-y-3">
				<flux:badge color="violet" :icon="svg('iconsax-two-setting-2', 'size-5')" rounded>{{ __('Admin workspace') }}</flux:badge>
				<div class="space-y-2">
					<flux:heading class="text-3xl! font-bold tracking-tight sm:text-4xl!" level="1">{{ __('Control center') }}</flux:heading>
					<flux:text class="max-w-xl text-base!" variant="subtle">{{ __('Monitor activity and jump straight to common admin tasks.') }}</flux:text>
				</div>
			</div>

			<div class="flex flex-wrap gap-3">
				<flux:button :href="route('home')" :icon="svg('iconsax-two-export-1', 'size-5')" variant="filled" wire:navigate>{{ __('Open gallery') }}</flux:button>
				<flux:button :href="route('manage.images.index')" :icon="svg('iconsax-two-gallery', 'size-5')" variant="primary" wire:navigate>{{ __('Review images') }}</flux:button>
			</div>
		</div>

		<flux:tabs class="relative mt-6" scrollable scrollable:fade size="sm" variant="pills">
			<flux:tab :href="route('manage.index')" :selected="true" :icon="svg('iconsax-bul-chart', 'size-5')" wire:navigate>{{ __('Overview') }}</flux:tab>
			<flux:tab :href="route('manage.users.index')" :icon="svg('iconsax-bul-people', 'size-5')" wire:navigate>{{ __('Users') }}</flux:tab>
			<flux:tab :href="route('manage.api-keys.index')" :icon="svg('iconsax-bul-key', 'size-5')" wire:navigate>{{ __('API keys') }}</flux:tab>
			<flux:tab :href="route('manage.images.index')" :icon="svg('iconsax-bul-gallery', 'size-5')" wire:navigate>{{ __('Images') }}</flux:tab>
			<flux:tab :href="route('manage.categories.index')" :icon="svg('iconsax-bul-category', 'size-5')" wire:navigate>{{ __('Categories') }}</flux:tab>
			<flux:tab :href="route('manage.settings.index')" :icon="svg('iconsax-bul-setting-2', 'size-5')" wire:navigate>{{ __('Settings') }}</flux:tab>
		</flux:tabs>
	</header>

	<div class="space-y-4">
		<div class="flex flex-wrap items-end justify-between gap-2">
			<div>
				<flux:heading size="lg">{{ __('Overall stats') }}</flux:heading>
				<flux:text variant="subtle">{{ __('Current system snapshot.') }}</flux:text>
			</div>
			<flux:badge color="zinc" :icon="svg('iconsax-two-lock', 'size-5')" rounded>{{ __('Admin access') }}</flux:badge>
		</div>

		<div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
			<a class="group rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-800" href="{{ route('manage.users.index') }}" wire:navigate>
				<flux:card class="h-full p-3! transition duration-200 group-hover:-translate-y-0.5 sm:p-5! group-hover:border-violet-400/50 group-hover:shadow-lg">
					<div class="flex items-start justify-between gap-4">
						<div class="space-y-3">
							<div class="flex size-9 items-center justify-center rounded-xl sm:size-11 sm:rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-300">
								<x-iconsax-two-people class="size-5" />
							</div>
							<div>
								<flux:text variant="subtle">{{ __('Users') }}</flux:text>
								<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['users']) }}</div>
							</div>
						</div>
						<x-iconsax-two-export-1 class="size-4 text-zinc-400 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-violet-500" />
					</div>
					<flux:badge class="mt-3 max-w-full sm:mt-5" :color="$this->stats['banned_users'] > 0 ? 'red' : 'zinc'" size="sm">
						{{ __(':count banned accounts', ['count' => number_format($this->stats['banned_users'])]) }}
					</flux:badge>
				</flux:card>
			</a>

			<a class="group rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-800" href="{{ route('manage.api-keys.index') }}" wire:navigate>
				<flux:card class="h-full p-3! transition duration-200 group-hover:-translate-y-0.5 sm:p-5! group-hover:border-blue-400/50 group-hover:shadow-lg">
					<div class="flex items-start justify-between gap-4">
						<div class="space-y-3">
							<div class="flex size-9 items-center justify-center rounded-xl sm:size-11 sm:rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-300">
								<x-iconsax-two-key class="size-5" />
							</div>
							<div>
								<flux:text variant="subtle">{{ __('API keys') }}</flux:text>
								<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['api_keys']) }}</div>
							</div>
						</div>
						<x-iconsax-two-export-1 class="size-4 text-zinc-400 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-blue-500" />
					</div>
					<flux:text class="mt-3 hidden text-sm! sm:mt-5 sm:block" variant="subtle">{{ __('Manage quotas and access tokens.') }}</flux:text>
				</flux:card>
			</a>

			<a class="group rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-800" href="{{ route('manage.images.index') }}" wire:navigate>
				<flux:card class="h-full p-3! transition duration-200 group-hover:-translate-y-0.5 sm:p-5! group-hover:border-emerald-400/50 group-hover:shadow-lg">
					<div class="flex items-start justify-between gap-4">
						<div class="space-y-3">
							<div class="flex size-9 items-center justify-center rounded-xl sm:size-11 sm:rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
								<x-iconsax-two-gallery class="size-5" />
							</div>
							<div>
								<flux:text variant="subtle">{{ __('Published images') }}</flux:text>
								<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['published_images']) }}</div>
							</div>
						</div>
						<x-iconsax-two-export-1 class="size-4 text-zinc-400 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-emerald-500" />
					</div>
					<flux:badge class="mt-3 max-w-full sm:mt-5" :color="$this->stats['unpublished_images'] > 0 ? 'amber' : 'zinc'" size="sm">
						{{ __(':count awaiting publication', ['count' => number_format($this->stats['unpublished_images'])]) }}
					</flux:badge>
				</flux:card>
			</a>

			<a class="group rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-800" href="{{ route('manage.categories.index') }}" wire:navigate>
				<flux:card class="h-full p-3! transition duration-200 group-hover:-translate-y-0.5 sm:p-5! group-hover:border-amber-400/50 group-hover:shadow-lg">
					<div class="flex items-start justify-between gap-4">
						<div class="space-y-3">
							<div class="flex size-9 items-center justify-center rounded-xl sm:size-11 sm:rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-300">
								<x-iconsax-two-category class="size-5" />
							</div>
							<div>
								<flux:text variant="subtle">{{ __('Categories') }}</flux:text>
								<div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">{{ number_format($this->stats['categories']) }}</div>
							</div>
						</div>
						<x-iconsax-two-export-1 class="size-4 text-zinc-400 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-amber-500" />
					</div>
					<flux:text class="mt-3 hidden text-sm! sm:mt-5 sm:block" variant="subtle">{{ __('Organize the public gallery.') }}</flux:text>
				</flux:card>
			</a>
		</div>
	</div>

	@php($dailyStats = $this->dailyStats)
	@php($maxDailyUsers = max(1, collect($dailyStats)->max('users')))
	@php($maxDailyImages = max(1, collect($dailyStats)->max('images')))
	@php($periodUsers = collect($dailyStats)->sum('users'))
	@php($periodVerifiedUsers = collect($dailyStats)->sum('verified_users'))
	@php($periodImages = collect($dailyStats)->sum('images'))
	@php($periodPublishedImages = collect($dailyStats)->sum('published_images'))

	<div class="grid gap-4 md:grid-cols-2">
		<flux:card class="space-y-5">
			<div class="flex items-start justify-between gap-4">
				<div>
					<flux:text variant="subtle">{{ __('Registered users') }}</flux:text>
					<div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($periodUsers) }}</div>
					<flux:text class="mt-1 text-xs!" variant="subtle">{{ __('Last 30 days') }}</flux:text>
				</div>
				<div class="flex size-10 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-300">
					<x-iconsax-two-people class="size-5" />
				</div>
			</div>

			<div class="flex h-40 items-end gap-1 border-b border-zinc-200 dark:border-white/10" role="img" aria-label="{{ __('Daily registered and verified users for the last 30 days') }}">
				@foreach ($dailyStats as $day)
					@php($userHeight = $day['users'] / $maxDailyUsers * 100)
					@php($verifiedHeight = $day['users'] > 0 ? $day['verified_users'] / $day['users'] * 100 : 0)
					<div class="group relative flex h-full min-w-0 flex-1 items-end" wire:key="daily-users-{{ $day['date']->toDateString() }}">
						<div class="flex w-full flex-col-reverse overflow-hidden rounded-t bg-violet-200 dark:bg-violet-950" style="height: {{ $day['users'] > 0 ? max(4, $userHeight) : 0 }}%">
							<div class="bg-violet-500" style="height: {{ $verifiedHeight }}%"></div>
						</div>
						<div class="pointer-events-none absolute bottom-full inset-s-1/2 z-10 mb-2 hidden w-max max-w-40 -translate-x-1/2 rounded-lg bg-zinc-950 px-2 py-1.5 text-xs text-white shadow-lg group-hover:block group-focus-within:block">
							{{ $day['date']->format('d/m') }} · {{ __(':total registered, :part verified', ['total' => number_format($day['users']), 'part' => number_format($day['verified_users'])]) }}
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
				<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-violet-500"></span>{{ __('Verified') }} · {{ number_format($periodVerifiedUsers) }}</span>
				<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-violet-200 dark:bg-violet-950"></span>{{ __('Not verified') }} · {{ number_format($periodUsers - $periodVerifiedUsers) }}</span>
			</div>
		</flux:card>

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

			<div class="flex h-40 items-end gap-1 border-b border-zinc-200 dark:border-white/10" role="img" aria-label="{{ __('Daily created and published images for the last 30 days') }}">
				@foreach ($dailyStats as $day)
					@php($imageHeight = $day['images'] / $maxDailyImages * 100)
					@php($publishedHeight = $day['images'] > 0 ? $day['published_images'] / $day['images'] * 100 : 0)
					<div class="group relative flex h-full min-w-0 flex-1 items-end" wire:key="daily-images-{{ $day['date']->toDateString() }}">
						<div class="flex w-full flex-col-reverse overflow-hidden rounded-t bg-emerald-200 dark:bg-emerald-950" style="height: {{ $day['images'] > 0 ? max(4, $imageHeight) : 0 }}%">
							<div class="bg-emerald-500" style="height: {{ $publishedHeight }}%"></div>
						</div>
						<div class="pointer-events-none absolute bottom-full inset-s-1/2 z-10 mb-2 hidden w-max max-w-40 -translate-x-1/2 rounded-lg bg-zinc-950 px-2 py-1.5 text-xs text-white shadow-lg group-hover:block group-focus-within:block">
							{{ $day['date']->format('d/m') }} · {{ __(':total created, :part published', ['total' => number_format($day['images']), 'part' => number_format($day['published_images'])]) }}
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
				<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-emerald-500"></span>{{ __('Published') }} · {{ number_format($periodPublishedImages) }}</span>
				<span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-emerald-200 dark:bg-emerald-950"></span>{{ __('Unpublished') }} · {{ number_format($periodImages - $periodPublishedImages) }}</span>
			</div>
		</flux:card>
	</div>
</section>
