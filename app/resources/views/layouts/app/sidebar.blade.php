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

		@php($sidebarCategories = \App\Models\Category::query()->active()->ordered()->get())
		@php($selectedSidebarCategory = request()->route('category'))
		@php($gallerySearch = request()->routeIs('search.*') && is_string(request('q')) ? request('q') : '')
		@php($gallerySort = is_string(request('sort')) && in_array(request('sort'), ['featured', 'new', 'popular'], true) ? request('sort') : 'new')
		@php($galleryBaseUrl = $selectedSidebarCategory instanceof \App\Models\Category ? route('categories.show', $selectedSidebarCategory) : (request()->routeIs('search.*') ? route('search.index') : route('home')))
		@php($galleryTabUrl = fn(string $sort) => ($query = array_filter(['q' => $gallerySearch, 'sort' => $sort === 'new' ? null : $sort], fn($value) => filled($value))) === [] ? $galleryBaseUrl : $galleryBaseUrl . '?' . http_build_query($query))

		<div class="min-h-0 flex-1 overflow-y-auto">
			@if (auth()->user()?->isAdmin() && request()->routeIs('manage.*'))
				<flux:sidebar.nav>
					<flux:sidebar.group class="grid">
						<flux:sidebar.item :href="route('home')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-arrow-left class="size-5" /></x-slot>
						</flux:sidebar.item>
					</flux:sidebar.group>
				</flux:sidebar.nav>

				<flux:sidebar.nav>
					<flux:sidebar.group class="grid">
						<flux:sidebar.item :href="route('manage.index')" :current="request()->routeIs('manage.index')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-chart class="size-5" /></x-slot>
							{{ __('Overview') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.users.index')" :current="request()->routeIs('manage.users.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-people class="size-5" /></x-slot>
							{{ __('Users') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.api-keys.index')" :current="request()->routeIs('manage.api-keys.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-key class="size-5" /></x-slot>
							{{ __('API keys') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.images.index')" :current="request()->routeIs('manage.images.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-gallery class="size-5" /></x-slot>
							{{ __('Images') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.categories.index')" :current="request()->routeIs('manage.categories.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-category class="size-5" /></x-slot>
							{{ __('Categories') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('manage.settings.index')" :current="request()->routeIs('manage.settings.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-setting-2 class="size-5" /></x-slot>
							{{ __('Settings') }}
						</flux:sidebar.item>
					</flux:sidebar.group>
				</flux:sidebar.nav>
			@else
				<flux:sidebar.nav>
					<flux:sidebar.group class="grid">
						<flux:sidebar.item :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-home class="size-4" /></x-slot>
							{{ __('Home') }}
						</flux:sidebar.item>
						<flux:sidebar.item :href="route('skills.index')" :current="request()->routeIs('skills.*')" wire:navigate>
							<x-slot name="icon"><x-iconsax-bul-star class="size-4" /></x-slot>
							{{ __('AI tools') }}
						</flux:sidebar.item>
						@auth
							<flux:sidebar.item :href="route('search.index')" :current="request()->routeIs('search.*')" wire:navigate>
								<x-slot name="icon"><x-iconsax-bul-search-normal class="size-4" /></x-slot>
								{{ __('Search') }}
							</flux:sidebar.item>
							<flux:sidebar.item :href="route('favorites.index')" :current="request()->routeIs('favorites.*')" wire:navigate>
								<x-slot name="icon"><x-iconsax-bul-heart class="size-4" /></x-slot>
								{{ __('Favorite images') }}
							</flux:sidebar.item>
						@else
							<flux:sidebar.item as="button" type="button" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">
								<x-slot name="icon"><x-iconsax-bul-search-normal class="size-4" /></x-slot>
								{{ __('Search') }}
							</flux:sidebar.item>
							<flux:sidebar.item as="button" type="button" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">
								<x-slot name="icon"><x-iconsax-bul-heart class="size-4" /></x-slot>
								{{ __('Favorite images') }}
							</flux:sidebar.item>
						@endauth
						@auth
							<livewire:gallery.usage :button-only="true" />
						@endauth
					</flux:sidebar.group>
				</flux:sidebar.nav>

				<flux:sidebar.nav>
					<flux:sidebar.group class="grid" expandable heading="{{ __('Categories') }}">
						<x-slot name="icon"><x-iconsax-bul-folder-open class="size-4" /></x-slot>
						<flux:sidebar.item :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
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

	@if (!request()->routeIs('images.show'))
		<flux:header class="sticky top-0 border-b border-zinc-200 bg-white/70 px-3! md:px-4! dark:border-zinc-700 dark:bg-zinc-900/70 backdrop-blur">
			<flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

			@if (request()->routeIs('home', 'categories.show') || (request()->routeIs('search.*') && $gallerySearch !== ''))
				<flux:tabs class="ms-2 hidden sm:inline-flex" variant="segmented" size="sm">
					<flux:tab :href="$galleryTabUrl('new')" :selected="$gallerySort === 'new'" wire:navigate>
						{{ __('Mới') }}
					</flux:tab>
					{{-- <flux:tab :href="$galleryTabUrl('popular')" :selected="$gallerySort === 'popular'" wire:navigate>
																{{ __('Phổ biến') }}
					</flux:tab> --}}
					<flux:tab :href="$galleryTabUrl('featured')" :selected="$gallerySort === 'featured'" wire:navigate>
						{{ __('Nổi bật') }}
					</flux:tab>
				</flux:tabs>
			@endif

			<flux:spacer />

			@auth
				<flux:modal.trigger name="image-composer">
					<flux:button size="sm" type="button" variant="primary" x-data x-on:click="$dispatch('open-image-composer')" aria-label="{{ __('Create image') }}" tooltip="{{ __('Create image') }}" tooltip:position="left">
						<x-slot name="icon"><x-iconsax-two-magic-star class="size-5" /></x-slot>
					</flux:button>
				</flux:modal.trigger>
			@else
				<flux:button size="sm" type="button" variant="primary" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })" aria-label="{{ __('Create image') }}" tooltip="{{ __('Create image') }}" tooltip:position="left">
					<x-slot name="icon"><x-iconsax-two-magic-star class="size-5" /></x-slot>
				</flux:button>
			@endauth
		</flux:header>
	@endif

	{{ $slot }}

	@unless (request()->routeIs('login', 'register', 'password.*', 'verification.*', 'two-factor.*', 'profile.edit', 'security.edit', 'api-key.edit', 'appearance.edit'))
		<livewire:account-modal :initial="session('account-modal')" />
	@endunless

	@if (!request()->routeIs('images.show', 'profile.edit', 'security.edit', 'api-key.edit', 'appearance.edit', 'manage.settings.*'))
		<livewire:gallery.detail />
	@endif

	@persist('gallery-generator')
	<livewire:gallery.generator />
	@endpersist

	@persist('toast')
	<flux:toast.group>
		<flux:toast />
	</flux:toast.group>
	@endpersist

	@fluxScripts
</body>

</html>