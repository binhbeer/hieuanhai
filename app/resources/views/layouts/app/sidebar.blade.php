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
			<flux:sidebar.nav>
				<flux:sidebar.group class="grid">
					<flux:sidebar.item icon="home" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
						{{ __('Home') }}
					</flux:sidebar.item>
					<flux:sidebar.item icon="magnifying-glass" :href="route('search.index')" :current="request()->routeIs('search.*')" wire:navigate>
						{{ __('Search') }}
					</flux:sidebar.item>
					<flux:sidebar.item icon="heart" :href="route('favorites.index')" :current="request()->routeIs('favorites.*')" wire:navigate>
						{{ __('Favorite images') }}
					</flux:sidebar.item>
					@auth
						<livewire:gallery.usage :button-only="true" />
					@endauth
				</flux:sidebar.group>
			</flux:sidebar.nav>

			<flux:sidebar.nav>
				<flux:sidebar.group class="grid" expandable heading="{{ __('Categories') }}">
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
		</div>

		<div class="shrink-0 space-y-3">
			@auth
				<livewire:gallery.usage />
			@endauth

			<x-user-menu />
		</div>
	</flux:sidebar>

	@if (!request()->routeIs('images.show'))
	<flux:header class="sticky top-0 px-3! md:px-4!">
		<flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

		@if (request()->routeIs('home', 'categories.show') || (request()->routeIs('search.*') && $gallerySearch !== ''))
			<flux:tabs class="ms-2 hidden sm:inline-flex" variant="segmented" size="sm">
				<flux:tab :href="$galleryTabUrl('new')" :selected="$gallerySort === 'new'" wire:navigate>
					{{ __('Mới') }}
				</flux:tab>
				<flux:tab :href="$galleryTabUrl('popular')" :selected="$gallerySort === 'popular'" wire:navigate>
					{{ __('Phổ biến') }}
				</flux:tab>
				<flux:tab :href="$galleryTabUrl('featured')" :selected="$gallerySort === 'featured'" wire:navigate>
					{{ __('Nổi bật') }}
				</flux:tab>
			</flux:tabs>
		@endif

		<flux:spacer />

		@auth
			<flux:modal.trigger name="image-composer">
				<flux:button size="sm" type="button" variant="primary" icon="sparkles" x-data x-on:click="$dispatch('open-image-composer')" />
			</flux:modal.trigger>
		@else
			<flux:button size="sm" :href="route('login')" variant="primary" icon="sparkles" wire:navigate />
		@endauth
	</flux:header>
	@endif

	{{ $slot }}

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