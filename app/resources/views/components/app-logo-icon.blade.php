@php($assetVersion = is_file(public_path('build/manifest.json')) ? filemtime(public_path('build/manifest.json')) : filemtime(public_path('logo.png')))

<img
	src="{{ asset('logo.png') }}?v={{ $assetVersion }}" alt="{{ config('app.name', 'GenAnh') }}" width="1254" height="1254"
	decoding="sync" fetchpriority="high" loading="eager"
	{{ $attributes->merge(['class' => 'size-6 object-contain']) }} />
