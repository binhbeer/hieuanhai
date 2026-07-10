<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

@php($siteName = \App\Support\AppSettings::string('site.name', config('app.name', 'Laravel')))
@php($siteDescription = \App\Support\AppSettings::string('site.description', ''))
@php($siteKeywords = \App\Support\AppSettings::string('site.keywords', ''))
@php($routeImage = request()->route('image'))
@php($metaImage = $routeImage instanceof \App\Models\AiImage && $routeImage->is_published && $routeImage->status === 'succeeded' && filled($routeImage->result_path) ? $routeImage : null)
@php($metaTitle = $metaImage ? \Illuminate\Support\Str::limit($metaImage->title ?: $metaImage->prompt, 70, '') : ($title ?? null))
@php($metaDescription = $metaImage ? \Illuminate\Support\Str::limit($metaImage->prompt, 160, '') : $siteDescription)
@php($metaUrl = $metaImage ? route('images.show', $metaImage) : url()->current())
@php($metaImageUrl = $metaImage ? app(\App\Services\AiImageEditor::class)->resultUrl($metaImage) : null)
@php($metaImageUrl = $metaImageUrl && ! \Illuminate\Support\Str::startsWith($metaImageUrl, ['http://', 'https://']) ? url($metaImageUrl) : $metaImageUrl)

<title>
    {{ filled($metaTitle) ? $metaTitle.' - '.$siteName : $siteName }}
</title>

@if (filled($metaDescription))
    <meta name="description" content="{{ $metaDescription }}">
@endif
@if ($siteKeywords !== '')
    <meta name="keywords" content="{{ $siteKeywords }}">
@endif
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:type" content="{{ $metaImage ? 'article' : 'website' }}">
<meta property="og:title" content="{{ filled($metaTitle) ? $metaTitle : $siteName }}">
@if (filled($metaDescription))
    <meta property="og:description" content="{{ $metaDescription }}">
@endif
<meta property="og:url" content="{{ $metaUrl }}">
@if ($metaImageUrl)
    <meta property="og:image" content="{{ $metaImageUrl }}">
@endif
<meta name="twitter:card" content="{{ $metaImageUrl ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ filled($metaTitle) ? $metaTitle : $siteName }}">
@if (filled($metaDescription))
    <meta name="twitter:description" content="{{ $metaDescription }}">
@endif
@if ($metaImageUrl)
    <meta name="twitter:image" content="{{ $metaImageUrl }}">
@endif
<meta name="theme-color" content="#7c3aed">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ $siteName }}">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="/icons/manifest.json">
<link rel="preload" as="image" href="{{ asset('logo.png') }}" fetchpriority="high">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
