@props([
    'sidebar' => false,
])

@php($siteName = \App\Support\AppSettings::string('site.name', config('app.name', 'GenAnh')))

@if ($sidebar)
	<a {{ $attributes->merge(['class' => 'flex items-center gap-2']) }} style="height: 3rem;">
		<x-app-logo-icon style="display: block; height: 2rem; max-height: none; max-width: none; object-fit: contain; width: auto;" />
		<span class="truncate text-lg font-semibold in-data-flux-sidebar-collapsed-desktop:hidden">
			{{ $siteName }}
		</span>
	</a>
@else
	<flux:brand :name="$siteName" {{ $attributes }}>
		<x-slot
			class="bg-accent-content text-accent-foreground flex aspect-square size-8 items-center justify-center rounded-md"
			name="logo">
			<x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
		</x-slot>
	</flux:brand>
@endif
