<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

@php
    $locale = app()->getLocale() === 'en' ? 'en' : 'vi';
    $siteName = \App\Support\AppSettings::string('site.name', config('app.name', 'GenAnh'));
    $homeTitle = \App\Support\AppSettings::string($locale === 'en' ? 'site.home_title.en' : 'site.home_title', '');
    $siteDescription = \App\Support\AppSettings::string($locale === 'en' ? 'site.description.en' : 'site.description', '');
    $siteKeywords = \App\Support\AppSettings::string($locale === 'en' ? 'site.keywords.en' : 'site.keywords', '');
    $googleMeasurementId = trim(\App\Support\AppSettings::string('analytics.google_measurement_id', ''));
    $assetVersion = is_file(public_path('build/manifest.json')) ? filemtime(public_path('build/manifest.json')) : filemtime(public_path('logo.png'));
    $routeImage = request()->route('image');
    $routeCategory = request()->route('category');
    $routeTag = request()->route('tag');
    $metaImage = $routeImage instanceof \App\Models\GeneratedMedia && $routeImage->is_published && $routeImage->status === 'succeeded' && filled($routeImage->result_path) ? $routeImage : null;
    $metaCategory = $routeCategory instanceof \App\Models\Category ? $routeCategory : null;
    $metaTag = $routeTag instanceof \App\Models\Tag ? $routeTag : null;
    $baseRouteName = \App\Support\LocalizedRoute::name();
    $isPrivateSkillsView = $baseRouteName === 'skills.index' && (request()->query('view') === 'projects' || request()->filled('project'));
    $isGuide = $baseRouteName !== null && \Illuminate\Support\Str::is('guide.*', $baseRouteName);
    $isIndexable = (in_array($baseRouteName, ['home', 'skills.index', 'categories.show', 'tags.show', 'images.show'], true) || $isGuide) && ! $isPrivateSkillsView;
    $englishEnabled = \App\Support\AppSettings::bool('locales.en.enabled');
    $englishReady = match (true) {
        $metaImage !== null => $metaImage->englishReady(),
        $metaCategory !== null => $metaCategory->englishReady(),
        $metaTag !== null => $metaTag->englishReady(),
        default => true,
    };
    $routeParameters = array_filter(['image' => $metaImage, 'category' => $metaCategory, 'tag' => $metaTag]);

    $metaTitle = match (true) {
        $metaImage !== null => \Illuminate\Support\Str::limit($metaImage->title ?: $metaImage->prompt, 70, ''),
        $metaCategory !== null => $metaCategory->name,
        $metaTag !== null => '#'.$metaTag->name,
        \App\Support\LocalizedRoute::is('home') => $homeTitle,
        \App\Support\LocalizedRoute::is('skills.index') => __('AI tools'),
        \App\Support\LocalizedRoute::is('guide.index') => __('User guide'),
        \App\Support\LocalizedRoute::is('guide.getting-started') => __('Create your first AI image'),
        \App\Support\LocalizedRoute::is('guide.web') => __('Manage your complete image workflow'),
        \App\Support\LocalizedRoute::is('guide.api') => __('Create images through the API'),
        \App\Support\LocalizedRoute::is('guide.faq') => __('Frequently asked questions'),
        default => isset($title) ? __($title) : null,
    };

    $metaDescription = match (true) {
        $metaImage !== null => \Illuminate\Support\Str::limit(
            filled($metaImage->description) ? $metaImage->description : ($metaImage->title ?: $metaImage->prompt),
            160,
            '',
        ),
        $metaCategory !== null => \Illuminate\Support\Str::limit(
            filled($metaCategory->description) ? $metaCategory->description : __('AI images about :name. Browse published community images.', ['name' => $metaCategory->name]),
            160,
            '',
        ),
        $metaTag !== null => \Illuminate\Support\Str::limit(
            filled($metaTag->description) ? $metaTag->description : __('AI images tagged #:name. Browse published community images.', ['name' => $metaTag->name]),
            160,
            '',
        ),
        \App\Support\LocalizedRoute::is('guide.index') => __('Step-by-step guides for image creation, workflow management, publishing, account security, and API integration.'),
        \App\Support\LocalizedRoute::is('guide.getting-started') => __('From signing in to downloading a finished image, follow this practical workflow.'),
        \App\Support\LocalizedRoute::is('guide.web') => __('Track generations, improve results, publish your best work, and collect ideas from the community.'),
        \App\Support\LocalizedRoute::is('guide.api') => __('Generate a key, send a secure request, and understand quota and error responses.'),
        \App\Support\LocalizedRoute::is('guide.faq') => __('Quick answers about quota, privacy, account access, and common generation problems.'),
        default => $siteDescription,
    };

    $metaUrl = match (true) {
        $metaImage !== null => route('images.show', $metaImage),
        $metaCategory !== null => route('categories.show', $metaCategory),
        $metaTag !== null => route('tags.show', $metaTag),
        \App\Support\LocalizedRoute::is('home') => route('home'),
        \App\Support\LocalizedRoute::is('skills.index') => route('skills.index'),
        $isGuide && $baseRouteName !== null => route($baseRouteName),
        default => url()->current(),
    };

    $imageEditor = app(\App\Services\AiImageEditor::class);
    $metaImageOriginalUrl = $metaImage ? $imageEditor->imageUrl($metaImage) : null;
    $metaImageOriginalUrl = $metaImageOriginalUrl && ! \Illuminate\Support\Str::startsWith($metaImageOriginalUrl, ['http://', 'https://']) ? url($metaImageOriginalUrl) : $metaImageOriginalUrl;
    $metaImageUrl = $metaImage ? $imageEditor->imageUrl($metaImage, 'og') : null;
    $metaImageUrl = $metaImageUrl && ! \Illuminate\Support\Str::startsWith($metaImageUrl, ['http://', 'https://']) ? url($metaImageUrl) : $metaImageUrl;
    $metaImageAlt = $metaImage ? \Illuminate\Support\Str::limit($metaImage->title ?: $metaImage->prompt, 120, '') : null;
    $metaKeywords = $metaImage
        ? $metaImage->tags->map(fn (\App\Models\Tag $tag): string => (string) $tag->getTranslationWithoutFallback('name', $locale))->filter()->implode(', ')
        : $siteKeywords;
    $metaRobots = $isIndexable ? 'index,follow,max-image-preview:large' : 'noindex,nofollow';
    $metaLocale = $locale === 'en' ? 'en_US' : 'vi_VN';
    $alternateLocale = $locale === 'en' ? 'vi_VN' : 'en_US';
    $viUrl = $isIndexable && $baseRouteName ? \App\Support\LocalizedRoute::url($baseRouteName, $routeParameters, 'vi') : null;
    $enUrl = $isIndexable && $baseRouteName && $englishEnabled && $englishReady ? \App\Support\LocalizedRoute::url($baseRouteName, $routeParameters, 'en') : null;
    $publishedAt = $metaImage?->published_at?->toIso8601String();
    $modifiedAt = $metaImage?->updated_at?->toIso8601String();
    $schema = [];

    if (\App\Support\LocalizedRoute::is('home')) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => route('home'),
            'description' => $siteDescription,
            'inLanguage' => $locale,
        ];
    }

    if ($metaCategory || $metaTag) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => filled($metaTitle) ? $metaTitle : $siteName,
            'url' => $metaUrl,
            'description' => $metaDescription,
            'inLanguage' => $locale,
        ];
    }

    if ($isGuide) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => filled($metaTitle) ? $metaTitle : __('User guide'),
            'url' => $metaUrl,
            'description' => $metaDescription,
            'inLanguage' => $locale,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => route('home'),
            ],
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
            'inLanguage' => $locale,
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
@if ($viUrl)
    <link rel="alternate" hreflang="vi" href="{{ $viUrl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $viUrl }}">
@endif
@if ($enUrl)
    <link rel="alternate" hreflang="en" href="{{ $enUrl }}">
@endif
<meta name="robots" content="{{ $metaRobots }}">
@if (filled($metaDescription))
    <meta name="description" content="{{ $metaDescription }}">
@endif
@if ($metaKeywords !== '')
    <meta name="keywords" content="{{ $metaKeywords }}">
@endif
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="{{ $metaLocale }}">
@if ($enUrl)
    <meta property="og:locale:alternate" content="{{ $alternateLocale }}">
@endif
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
@if (! app()->isLocal() && filled($googleMeasurementId) && ! \App\Support\LocalizedRoute::is('manage.*'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleMeasurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @js($googleMeasurementId));
    </script>
@endif
<meta name="theme-color" content="#000000">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ $siteName }}">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="/icons/manifest.json?v={{ $assetVersion }}">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png?v={{ $assetVersion }}">
<link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192.png?v={{ $assetVersion }}">
<link rel="preload" as="image" href="{{ asset('logo.png') }}?v={{ $assetVersion }}" fetchpriority="high">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
