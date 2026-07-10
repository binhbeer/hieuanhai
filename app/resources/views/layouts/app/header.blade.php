<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

	<head>
		@include('partials.head')
	</head>

	<body class="min-h-screen bg-white dark:bg-zinc-800">
		<flux:header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" container>
			<flux:sidebar.toggle class="mr-2 lg:hidden" :icon="svg('iconsax-two-menu', 'size-5')" inset="left" />

			@persist('header-logo')
				<x-app-logo href="{{ route('home') }}" wire:navigate />
			@endpersist

			<flux:navbar class="-mb-px max-lg:hidden">
				<flux:navbar.item :icon="svg('iconsax-bul-gallery', 'size-5')" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
					{{ __('Create image') }}
				</flux:navbar.item>
			</flux:navbar>

			<flux:spacer />
			a

			<flux:navbar class="py-0! me-1.5 space-x-0.5 rtl:space-x-reverse">
				<flux:tooltip :content="__('Search')" position="bottom">
					<flux:navbar.item class="!h-10 [&>div>svg]:size-5" :icon="svg('iconsax-bul-search-normal', 'size-5')" href="#" :label="__('Search')" />
				</flux:tooltip>
				<flux:tooltip :content="__('Repository')" position="bottom">
					<flux:navbar.item
						class="h-10 max-lg:hidden [&>div>svg]:size-5" :icon="svg('iconsax-bul-folder-open', 'size-5')"
						href="https://github.com/laravel/livewire-starter-kit" target="_blank" :label="__('Repository')" />
				</flux:tooltip>
				<flux:tooltip :content="__('Documentation')" position="bottom">
					<flux:navbar.item
						class="h-10 max-lg:hidden [&>div>svg]:size-5" :icon="svg('iconsax-bul-book', 'size-5')"
						href="https://laravel.com/docs/starter-kits#livewire" target="_blank" :label="__('Documentation')" />
				</flux:tooltip>
			</flux:navbar>

			<x-user-menu />
		</flux:header>

		<!-- Mobile Menu -->
		<flux:sidebar class="border-e border-zinc-200 bg-zinc-50 lg:hidden dark:border-zinc-700 dark:bg-zinc-900"
			collapsible="mobile" sticky>
			<flux:sidebar.header>
				<x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
				<flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
			</flux:sidebar.header>

			<flux:sidebar.nav>
				<flux:sidebar.group :heading="__('Platform')">
					<flux:sidebar.item :icon="svg('iconsax-bul-gallery', 'size-5')" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
						{{ __('Create image') }}
					</flux:sidebar.item>
				</flux:sidebar.group>
			</flux:sidebar.nav>

			<flux:spacer />

			<flux:sidebar.nav>
				<flux:sidebar.item :icon="svg('iconsax-bul-folder-open', 'size-5')" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
					{{ __('Repository') }}
				</flux:sidebar.item>
				<flux:sidebar.item :icon="svg('iconsax-bul-book', 'size-5')" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
					{{ __('Documentation') }}
				</flux:sidebar.item>
			</flux:sidebar.nav>
		</flux:sidebar>

		{{ $slot }}

		@persist('toast')
			<flux:toast.group>
				<flux:toast />
			</flux:toast.group>
		@endpersist

		@fluxScripts
	</body>

</html>
