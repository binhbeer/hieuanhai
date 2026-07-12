<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

@php
    $siteName = \App\Support\AppSettings::string('site.name', config('app.name', 'GenAnh'));
    $homeTitle = \App\Support\AppSettings::string('site.home_title', '');
    $siteDescription = \App\Support\AppSettings::string('site.description', '');
    $siteKeywords = \App\Support\AppSettings::string('site.keywords', '');
    $googleMeasurementId = trim(\App\Support\AppSettings::string('analytics.google_measurement_id', ''));
    $routeImage = request()->route('image');
    $routeCategory = request()->route('category');
    $routeTag = request()->route('tag');
    $metaImage = $routeImage instanceof \App\Models\AiImage && $routeImage->is_published && $routeImage->status === 'succeeded' && filled($routeImage->result_path) ? $routeImage : null;
    $metaCategory = $routeCategory instanceof \App\Models\Category ? $routeCategory : null;
    $metaTag = $routeTag instanceof \App\Models\AiTag ? $routeTag : null;
    $isIndexable = request()->routeIs('home', 'categories.show', 'tags.show', 'images.show');

    $metaTitle = match (true) {
        $metaImage !== null => \Illuminate\Support\Str::limit($metaImage->title ?: $metaImage->prompt, 70, ''),
        $metaCategory !== null => $metaCategory->name,
        $metaTag !== null => '#'.$metaTag->name,
        request()->routeIs('home') => $homeTitle,
        default => $title ?? null,
    };

    $metaDescription = match (true) {
        $metaImage !== null => \Illuminate\Support\Str::limit($metaImage->prompt, 160, ''),
        $metaCategory !== null => \Illuminate\Support\Str::limit('Ảnh AI chủ đề '.$metaCategory->name.'. Khám phá các ảnh cộng đồng đã publish.', 160, ''),
        $metaTag !== null => \Illuminate\Support\Str::limit('Ảnh AI gắn thẻ #'.$metaTag->name.'. Khám phá các ảnh cộng đồng đã publish.', 160, ''),
        default => $siteDescription,
    };

    $metaUrl = match (true) {
        $metaImage !== null => route('images.show', $metaImage),
        $metaCategory !== null => route('categories.show', $metaCategory),
        $metaTag !== null => route('tags.show', $metaTag),
        request()->routeIs('home') => route('home'),
        default => url()->current(),
    };

    $imageEditor = app(\App\Services\AiImageEditor::class);
    $metaImageOriginalUrl = $metaImage ? $imageEditor->imageUrl($metaImage) : null;
    $metaImageOriginalUrl = $metaImageOriginalUrl && ! \Illuminate\Support\Str::startsWith($metaImageOriginalUrl, ['http://', 'https://']) ? url($metaImageOriginalUrl) : $metaImageOriginalUrl;
    $metaImageUrl = $metaImage ? $imageEditor->imageUrl($metaImage, 'og') : null;
    $metaImageUrl = $metaImageUrl && ! \Illuminate\Support\Str::startsWith($metaImageUrl, ['http://', 'https://']) ? url($metaImageUrl) : $metaImageUrl;
    $metaImageAlt = $metaImage ? \Illuminate\Support\Str::limit($metaImage->title ?: $metaImage->prompt, 120, '') : null;
    $metaRobots = $isIndexable ? 'index,follow,max-image-preview:large' : 'noindex,nofollow';
    $metaLocale = str_replace('-', '_', str_replace('_', '-', app()->getLocale()));
    $publishedAt = $metaImage?->published_at?->toIso8601String();
    $modifiedAt = $metaImage?->updated_at?->toIso8601String();
    $schema = [];

    if (request()->routeIs('home')) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => route('home'),
            'description' => $siteDescription,
        ];
    }

    if ($metaCategory || $metaTag) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => filled($metaTitle) ? $metaTitle : $siteName,
            'url' => $metaUrl,
            'description' => $metaDescription,
        ];
    }

    if ($metaImage && $metaImageOriginalUrl) {
        $imageSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            'contentUrl' => $metaImageOriginalUrl,
            'thumbnailUrl' => $metaImageUrl,
            'url' => $metaUrl,
            'name' => filled($metaTitle) ? $metaTitle : $siteName,
            'description' => $metaDescription,
            'datePublished' => $publishedAt,
            'dateModified' => $modifiedAt,
        ];

        if ($metaImage->user?->name) {
            $imageSchema['author'] = [
                '@type' => 'Person',
                'name' => $metaImage->user->name,
            ];
        }

        $schema[] = array_filter($imageSchema);
    }
@endphp

<title>
    {{ filled($metaTitle) ? $metaTitle.' - '.$siteName : $siteName }}
</title>

<link rel="canonical" href="{{ $metaUrl }}">
<meta name="robots" content="{{ $metaRobots }}">
@if (filled($metaDescription))
    <meta name="description" content="{{ $metaDescription }}">
@endif
@if ($siteKeywords !== '')
    <meta name="keywords" content="{{ $siteKeywords }}">
@endif
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="{{ $metaLocale }}">
<meta property="og:type" content="{{ $metaImage ? 'article' : 'website' }}">
<meta property="og:title" content="{{ filled($metaTitle) ? $metaTitle : $siteName }}">
@if (filled($metaDescription))
    <meta property="og:description" content="{{ $metaDescription }}">
@endif
<meta property="og:url" content="{{ $metaUrl }}">
@if ($metaImageUrl)
    <meta property="og:image" content="{{ $metaImageUrl }}">
    <meta property="og:image:secure_url" content="{{ $metaImageUrl }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    @if ($metaImageAlt)
        <meta property="og:image:alt" content="{{ $metaImageAlt }}">
    @endif
@endif
@if ($publishedAt)
    <meta property="article:published_time" content="{{ $publishedAt }}">
@endif
@if ($modifiedAt)
    <meta property="article:modified_time" content="{{ $modifiedAt }}">
@endif
<meta name="twitter:card" content="{{ $metaImageUrl ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ filled($metaTitle) ? $metaTitle : $siteName }}">
<meta name="twitter:url" content="{{ $metaUrl }}">
@if (filled($metaDescription))
    <meta name="twitter:description" content="{{ $metaDescription }}">
@endif
@if ($metaImageUrl)
    <meta name="twitter:image" content="{{ $metaImageUrl }}">
    @if ($metaImageAlt)
        <meta name="twitter:image:alt" content="{{ $metaImageAlt }}">
    @endif
@endif
@if ($schema !== [])
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
@if (! app()->isLocal() && filled($googleMeasurementId) && ! request()->routeIs('manage.*'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleMeasurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @js($googleMeasurementId));
    </script>
@endif
<meta name="theme-color" content="#f59e0b">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ $siteName }}">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="/icons/manifest.json">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192.png">
<link rel="preload" as="image" href="{{ asset('logo.png') }}" fetchpriority="high">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
