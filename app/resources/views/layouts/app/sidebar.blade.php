<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
	@include('partials.head')
</head>

<body class="min-h-dvh bg-white dark:bg-zinc-800 lg:h-dvh lg:overflow-hidden">
	<flux:sidebar class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" sticky collapsible="mobile">
		<flux:sidebar.header>
			<x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
			<flux:sidebar.collapse class="lg:hidden" />
		</flux:sidebar.header>

		@php($sidebarCategories = \App\Models\Category::query()->active()->when(app()->getLocale() === 'en', fn($query) => $query->englishReady())->ordered()->get())
		@php($selectedSidebarCategory = request()->route('category'))
		<div class="min-h-0 flex-1 overflow-y-auto">
			@if (auth()->user()?->isAdmin() && \App\Support\LocalizedRoute::is('manage.*'))
				<flux:sidebar.nav>
					<flux:sidebar.group class="grid">
						<flux:sidebar.item :href="route('home')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-arrow-left class="size-5" /></x-slot>
						</flux:sidebar.item>
					</flux:sidebar.group>
				</flux:sidebar.nav>

				<flux:sidebar.nav>
					<flux:sidebar.group class="grid">
						<flux:sidebar.item :href="route('manage.index')" :current="\App\Support\LocalizedRoute::is('manage.index')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-chart class="size-5" /></x-slot>
							{{ __('Overview') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.users.index')" :current="\App\Support\LocalizedRoute::is('manage.users.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-people class="size-5" /></x-slot>
							{{ __('Users') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.api-keys.index')" :current="\App\Support\LocalizedRoute::is('manage.api-keys.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-key class="size-5" /></x-slot>
							{{ __('API keys') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.images.index')" :current="\App\Support\LocalizedRoute::is('manage.images.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-gallery class="size-5" /></x-slot>
							{{ __('Images') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.studio.index')" :current="\App\Support\LocalizedRoute::is('manage.studio.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-star class="size-5" /></x-slot>
							{{ __('AI tools') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.categories.index')" :current="\App\Support\LocalizedRoute::is('manage.categories.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-category class="size-5" /></x-slot>
							{{ __('Categories') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.languages.index')" :current="\App\Support\LocalizedRoute::is('manage.languages.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-global class="size-5" /></x-slot>
							Ngôn ngữ
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.settings.index')" :current="\App\Support\LocalizedRoute::is('manage.settings.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-setting-2 class="size-5" /></x-slot>
							{{ __('Settings') }}
						</flux:sidebar.item>
					</flux:sidebar.group>
				</flux:sidebar.nav>
			@else
			@php($productTab = match (true) {
				\App\Support\LocalizedRoute::is('quick.*') => 'quick',
				\App\Support\LocalizedRoute::is('creator.*') => 'creator',
				\App\Support\LocalizedRoute::is('studio.*') => 'studio',
				default => null,
			})

			<div class="px-2 pb-3">
				<flux:tabs variant="segmented" size="sm" class="grid! h-auto! w-full grid-cols-3 gap-0.5 p-1" aria-label="{{ __('Create image') }}">
					<flux:tab :href="route('quick.index')" :selected="$productTab === 'quick'" wire:navigate class="h-auto! flex-col! justify-center gap-1! px-1! py-2! text-center text-xs leading-tight">
						<x-slot name="icon">
							<x-iconsax-two-flash class="size-5 text-zinc-500 dark:text-zinc-400 [[data-flux-tab][data-selected]_&]:text-zinc-800 dark:[[data-flux-tab][data-selected]_&]:text-white" />
						</x-slot>
						{{ __('Quick') }}
					</flux:tab>
					<flux:tab :href="route('creator.index')" :selected="$productTab === 'creator'" wire:navigate class="h-auto! flex-col! justify-center gap-1! px-1! py-2! text-center text-xs leading-tight">
						<x-slot name="icon">
							<x-iconsax-two-magicpen class="size-5 text-zinc-500 dark:text-zinc-400 [[data-flux-tab][data-selected]_&]:text-zinc-800 dark:[[data-flux-tab][data-selected]_&]:text-white" />
						</x-slot>
						{{ __('Creator') }}
					</flux:tab>
					<flux:tab :href="route('studio.index')" :selected="$productTab === 'studio'" wire:navigate class="h-auto! flex-col! justify-center gap-1! px-1! py-2! text-center text-xs leading-tight">
						<x-slot name="icon">
							<x-iconsax-two-layer class="size-5 text-zinc-500 dark:text-zinc-400 [[data-flux-tab][data-selected]_&]:text-zinc-800 dark:[[data-flux-tab][data-selected]_&]:text-white" />
						</x-slot>
						{{ __('Studio') }}
					</flux:tab>
				</flux:tabs>
			</div>

			<flux:sidebar.nav>
				<flux:sidebar.group class="grid">
					@auth
						<flux:sidebar.item :href="route('favorites.index')" :current="\App\Support\LocalizedRoute::is('favorites.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-two-gallery-favorite class="size-4" /></x-slot>
							{{ __('Favorite images') }}
						</flux:sidebar.item>
						<livewire:gallery.usage :button-only="true" />
					@else
						<flux:sidebar.item as="button" type="button" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">
							<x-slot name="icon"><x-iconsax-two-gallery-favorite class="size-4" /></x-slot>
							{{ __('Favorite images') }}
						</flux:sidebar.item>
					@endauth
				</flux:sidebar.group>
			</flux:sidebar.nav>

			<flux:sidebar.nav>
				<flux:sidebar.group class="grid" expandable heading="{{ __('Gallery') }}">
					<x-slot name="icon"><x-iconsax-two-image class="size-4" /></x-slot>
					<flux:sidebar.item :href="route('gallery.index')" :current="\App\Support\LocalizedRoute::is('gallery.index')" wire:navigate>
						{{ __('All') }}
					</flux:sidebar.item>
					@foreach ($sidebarCategories as $category)
						<flux:sidebar.item :href="route('categories.show', $category)" :current="$selectedSidebarCategory instanceof \App\Models\Category && $selectedSidebarCategory->is($category)" wire:navigate>
							{{ $category->name }}
						</flux:sidebar.item>
					@endforeach
				</flux:sidebar.group>
			</flux:sidebar.nav>
			@endif
		</div>

		<div class="shrink-0 space-y-3">
			@auth
				<livewire:gallery.usage />
			@endauth

			<x-user-menu />
		</div>
	</flux:sidebar>

	@if (!\App\Support\LocalizedRoute::is('images.show'))
		<flux:header class="sticky top-0 border-b border-zinc-200 bg-white/70 px-3! md:px-4! dark:border-zinc-700 dark:bg-zinc-900/70 backdrop-blur border-none">
			<flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
			<x-app-logo class="ms-1 lg:hidden" href="{{ route('home') }}" wire:navigate />

			<flux:spacer />
			<div class="flex items-center gap-2">
				@unless (auth()->user()?->isAdmin() && \App\Support\LocalizedRoute::is('manage.*'))
					<flux:dropdown position="bottom" align="end">
						<flux:button size="sm" variant="ghost" square aria-label="{{ __('Create image') }}" tooltip="{{ __('Create image') }}" tooltip:position="bottom">
							<x-slot name="icon">
								<x-iconsax-two-add class="size-5" />
							</x-slot>
						</flux:button>

						<flux:menu class="min-w-40">
							<flux:menu.item :href="route('quick.index', ['composer' => 1])" wire:navigate x-data x-on:click="$dispatch('open-quick-composer')">
								<x-slot name="icon"><x-iconsax-two-flash class="mr-1.5 size-5" /></x-slot>
								{{ __('Quick') }}
							</flux:menu.item>
							<flux:menu.item :href="route('creator.index', ['composer' => 1])" wire:navigate x-data x-on:click="$dispatch('open-image-composer')">
								<x-slot name="icon"><x-iconsax-two-magicpen class="mr-1.5 size-5" /></x-slot>
								{{ __('Creator') }}
							</flux:menu.item>
							<flux:menu.item :href="route('studio.index', ['wizard' => 1])" wire:navigate x-data x-on:click="$dispatch('open-studio-wizard')">
								<x-slot name="icon"><x-iconsax-two-layer class="mr-1.5 size-5" /></x-slot>
								{{ __('Studio') }}
							</flux:menu.item>
						</flux:menu>
					</flux:dropdown>
				@endunless
				<x-appearance-switcher />
				<x-language-switcher />
			</div>
		</flux:header>
	@endif

	{{ $slot }}

	@unless (\App\Support\LocalizedRoute::is('login', 'register', 'password.*', 'verification.*', 'two-factor.*', 'profile.edit', 'security.edit', 'api-key.edit', 'appearance.edit'))
		<livewire:account-modal :initial="session('account-modal')" />
	@endunless

	@if (!\App\Support\LocalizedRoute::is('images.show', 'profile.edit', 'security.edit', 'api-key.edit', 'appearance.edit', 'manage.settings.*'))
		<livewire:gallery.detail />
	@endif

	@persist('quick-composer')
	<livewire:quick.composer />
	@endpersist

	@persist('gallery-creator')
	<livewire:gallery.creator />
	@endpersist

	@persist('toast')
	<flux:toast.group>
		<flux:toast />
	</flux:toast.group>
	@endpersist

	@fluxScripts
</body>

</html>