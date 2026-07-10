@props(['images'])

@if ($images->isEmpty())
	{{ $empty ?? '' }}
@else
	<div {{ $attributes->class('columns-2 gap-4 md:columns-4 xl:columns-6 2xl:columns-8') }}>
		{{ $slot }}
	</div>
@endif