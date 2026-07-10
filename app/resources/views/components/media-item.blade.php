@props([
	'image',
	'url',
	'detailUrl',
	'creator' => null,
])

@php($detailTitle = Str::limit($image->title ?: $image->prompt, 70, ''))
@php($imageSize = $image->result_path ? @getimagesize(Illuminate\Support\Facades\Storage::disk('public')->path($image->result_path)) : false)

<div {{ $attributes->class('group relative mb-3 break-inside-avoid overflow-hidden rounded-2xl bg-zinc-200 text-left shadow-sm transition duration-500 hover:-translate-y-0.5 hover:shadow-xl focus-within:-translate-y-0.5 focus-within:shadow-xl dark:bg-white/10') }}>
	<flux:skeleton class="pointer-events-none absolute inset-0 size-full rounded-2xl" animate="shimmer" />

	<a class="relative block" href="{{ $detailUrl }}" aria-label="{{ __('View image details') }}" x-on:click.prevent="$dispatch('open-image-detail', { id: {{ $image->id }}, url: @js($detailUrl), title: @js($detailTitle) })">
		<img class="h-auto w-full object-cover transition duration-500 group-hover:scale-[1.02]" src="{{ $url }}" alt="{{ Str::limit($image->title ?: $image->prompt, 80) }}" @if ($imageSize) width="{{ $imageSize[0] }}" height="{{ $imageSize[1] }}" @endif loading="lazy" decoding="async">
	</a>

	@if (isset($badge))
		<div class="absolute right-3 bottom-3">
			{{ $badge }}
		</div>
	@endif

	<div class="pointer-events-none absolute inset-x-0 bottom-0 bg-linear-to-t from-black/85 via-black/45 to-transparent p-3 opacity-0 transition duration-200 group-hover:opacity-100 group-focus-within:opacity-100">
		<div class="flex items-end justify-between gap-3">
			<div class="min-w-0 text-white">
				<div class="truncate text-sm font-semibold">{{ $creator ?: __('Guest') }}</div>
			</div>
		</div>
	</div>
</div>
