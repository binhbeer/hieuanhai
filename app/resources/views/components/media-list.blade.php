@props(['images'])

@if ($images->isEmpty())
	{{ $empty ?? '' }}
@else
	<div {{ $attributes->class('columns-2 gap-3 sm:columns-3 lg:columns-4 2xl:columns-6') }}>
		{{ $slot }}
	</div>
@endif
