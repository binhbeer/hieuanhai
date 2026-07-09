@props([
	'image',
	'url',
	'detailUrl',
	'creator' => null,
])

<div {{ $attributes->class('group relative mb-3 break-inside-avoid overflow-hidden rounded-2xl bg-zinc-200 text-left opacity-0 shadow-sm transition duration-500 hover:-translate-y-0.5 hover:shadow-xl focus-within:-translate-y-0.5 focus-within:shadow-xl dark:bg-white/10') }} x-data="{ loaded: false }" x-bind:class="loaded && 'opacity-100!'">
	<a class="block" href="{{ $detailUrl }}" aria-label="{{ __('View image details') }}" x-on:click.prevent="$dispatch('open-image-detail', { id: {{ $image->id }} })">
		<img class="h-auto w-full object-cover transition duration-500 group-hover:scale-[1.02]" src="{{ $url }}" alt="{{ Str::limit($image->prompt, 80) }}" loading="lazy" decoding="async" x-on:load="loaded = true" x-init="$el.complete && (loaded = true)">
	</a>

	@if (isset($badge))
		<div class="absolute right-3 top-3">
			{{ $badge }}
		</div>
	@endif

	<div class="pointer-events-none absolute inset-x-0 bottom-0 bg-linear-to-t from-black/85 via-black/45 to-transparent p-3 opacity-0 transition duration-200 group-hover:opacity-100 group-focus-within:opacity-100">
		<div class="flex items-end justify-between gap-3">
			<div class="min-w-0 text-white">
				<div class="text-xs text-white/70">{{ __('Creator') }}</div>
				<div class="truncate text-sm font-semibold">{{ $creator ?: __('Guest') }}</div>
			</div>
			@if (isset($actions))
				<div class="pointer-events-auto flex shrink-0 gap-2">
					{{ $actions }}
				</div>
			@endif
		</div>
	</div>
</div>
