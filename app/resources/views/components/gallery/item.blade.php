@props([
	'image',
	'url',
	'detailUrl',
	'creator' => null,
	'loading' => 'lazy',
	'imageSize' => null,
])

@php($detailTitle = Str::limit($image->title ?: $image->prompt, 70, ''))

<div wire:ignore.self {{ $attributes->class('group relative mb-3 break-inside-avoid overflow-hidden rounded-2xl bg-zinc-200 text-left shadow-sm transition duration-500 hover:-translate-y-0.5 hover:shadow-xl focus-within:-translate-y-0.5 focus-within:shadow-xl dark:bg-white/10') }}>
	<a class="relative block" href="{{ $detailUrl }}" aria-label="{{ __('View image details') }}" x-on:click.prevent="$dispatch('open-image-detail', { id: {{ $image->id }}, url: @js($detailUrl), title: @js($detailTitle), preview: @js($url) })">
		<img class="h-auto w-full object-cover transition duration-500 group-hover:scale-[1.02]" src="{{ $url }}" alt="{{ Str::limit($image->title ?: $image->prompt, 80) }}" @if ($imageSize) width="{{ $imageSize['width'] }}" height="{{ $imageSize['height'] }}" @endif loading="{{ $loading }}" decoding="async">

		@if (isset($badge))
			<div class="absolute right-3 bottom-3">
				{{ $badge }}
			</div>
		@endif
	</a>
</div>
