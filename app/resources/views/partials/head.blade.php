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
    $routeStudioSample = request()->route('sample');
    $metaImage = $routeImage instanceof \App\Models\GeneratedMedia && $routeImage->is_published && $routeImage->status === 'succeeded' && filled($routeImage->result_path) ? $routeImage : null;
    $metaCategory = $routeCategory instanceof \App\Models\Category ? $routeCategory : null;
    $metaTag = $routeTag instanceof \App\Models\Tag ? $routeTag : null;
    $studioSample = is_string($routeStudioSample) ? \App\Support\StudioSamples::get($routeStudioSample) : null;
    $baseRouteName = \App\Support\LocalizedRoute::name();
    $isHome = \App\Support\LocalizedRoute::is('home');
    $isPrivateStudioView = $baseRouteName === 'studio.index' && (request()->query('view') === 'projects' || request()->filled('project'));
    $isGuide = $baseRouteName !== null && \Illuminate\Support\Str::is('guide.*', $baseRouteName);
    $isLegal = $baseRouteName !== null && \Illuminate\Support\Str::is('legal.*', $baseRouteName);
    $isQuickEdit = $baseRouteName !== null && \Illuminate\Support\Str::is('quick.*', $baseRouteName);
    $isStudioSample = $baseRouteName === 'studio.sample' && $studioSample !== null;
    $isIndexable = (in_array($baseRouteName, ['home', 'gallery.index', 'creator.index', 'studio.index', 'categories.show', 'tags.show', 'images.show'], true) || $isQuickEdit || $isGuide || $isLegal || $isStudioSample) && ! $isPrivateStudioView;
    $englishEnabled = \App\Support\AppSettings::bool('locales.en.enabled');
    $englishReady = match (true) {
        $metaImage !== null => $metaImage->englishReady(),
        $metaCategory !== null => $metaCategory->englishReady(),
        $metaTag !== null => $metaTag->englishReady(),
        default => true,
    };
    $routeParameters = array_filter(['image' => $metaImage, 'category' => $metaCategory, 'tag' => $metaTag, 'sample' => $isStudioSample ? $routeStudioSample : null]);

    $quickEditTool = is_string(request()->route('tool')) ? \App\Support\QuickEditTools::get(request()->route('tool')) : null;

    $metaTitle = match (true) {
        $metaImage !== null => \Illuminate\Support\Str::limit($metaImage->title ?: $metaImage->prompt, 70, ''),
        $metaCategory !== null => $metaCategory->name,
        $metaTag !== null => '#'.$metaTag->name,
        \App\Support\LocalizedRoute::is('home') => $homeTitle,
        \App\Support\LocalizedRoute::is('gallery.index') => __('AI Gallery'),
        \App\Support\LocalizedRoute::is('quick.index') => __('Quick'),
        $isQuickEdit && $quickEditTool !== null => __($quickEditTool['seo_title'] ?? $quickEditTool['title']),
        \App\Support\LocalizedRoute::is('creator.index') => __('Creator'),
        \App\Support\LocalizedRoute::is('studio.index') => __('Studio'),
        $isStudioSample => __($studioSample['title']),
        \App\Support\LocalizedRoute::is('guide.index') => __('User guide'),
        \App\Support\LocalizedRoute::is('guide.getting-started') => __('Create your first AI image'),
        \App\Support\LocalizedRoute::is('guide.web') => __('Manage your complete image workflow'),
        \App\Support\LocalizedRoute::is('guide.api') => __('Create images through the API'),
        \App\Support\LocalizedRoute::is('guide.faq') => __('Frequently asked questions'),
        \App\Support\LocalizedRoute::is('legal.privacy') => __('Privacy Policy'),
        \App\Support\LocalizedRoute::is('legal.terms') => __('Terms of Service'),
        \App\Support\LocalizedRoute::is('legal.support') => __('Support'),
        \App\Support\LocalizedRoute::is('legal.delete-account') => __('Delete Account'),
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
        $isQuickEdit && $quickEditTool !== null => __($quickEditTool['seo_description'] ?? $quickEditTool['description']),
        \App\Support\LocalizedRoute::is('quick.index') => __('Edit photos with focused AI tools, clear reference roles, and one result per request.'),
        \App\Support\LocalizedRoute::is('gallery.index') => __('Browse published AI images from the GenAnh community by category, tag, and visual idea.'),
        \App\Support\LocalizedRoute::is('creator.index') => __('Create AI images from prompts and references with advanced model, ratio, resolution, rewrite, and translation controls.'),
        \App\Support\LocalizedRoute::is('studio.index') => __('Build product image sets and marketing posters with projects, drafts, versions, and batch outputs.'),
        $isStudioSample => __($studioSample['description']),
        \App\Support\LocalizedRoute::is('guide.index') => __('Step-by-step guides for image creation, workflow management, publishing, account security, and API integration.'),
        \App\Support\LocalizedRoute::is('guide.getting-started') => __('From signing in to downloading a finished image, follow this practical workflow.'),
        \App\Support\LocalizedRoute::is('guide.web') => __('Track generations, improve results, publish your best work, and collect ideas from the community.'),
        \App\Support\LocalizedRoute::is('guide.api') => __('Generate a key, send a secure request, and understand quota and error responses.'),
        \App\Support\LocalizedRoute::is('guide.faq') => __('Quick answers about quota, privacy, account access, and common generation problems.'),
        \App\Support\LocalizedRoute::is('legal.privacy') => __('Learn what information GenAnh processes, why it is used, how long it is kept, and how to exercise your privacy choices.'),
        \App\Support\LocalizedRoute::is('legal.terms') => __('Read the rules for using GenAnh accounts, AI tools, uploads, generated output, public content, and API services.'),
        \App\Support\LocalizedRoute::is('legal.support') => __('Contact GenAnh for help with accounts, image generation, Studio projects, API access, privacy, and account deletion.'),
        \App\Support\LocalizedRoute::is('legal.delete-account') => __('Permanently delete your GenAnh account, prompts, uploaded images, generated results, projects, favorites, keys, and related data.'),
        default => $siteDescription,
    };

    $metaUrl = match (true) {
        $metaImage !== null => route('images.show', $metaImage),
        $metaCategory !== null => route('categories.show', $metaCategory),
        $metaTag !== null => route('tags.show', $metaTag),
        \App\Support\LocalizedRoute::is('home') => route('home'),
        \App\Support\LocalizedRoute::is('gallery.index') => route('gallery.index'),
        \App\Support\LocalizedRoute::is('quick.index') => route('quick.index'),
        $isQuickEdit && $baseRouteName !== null => route($baseRouteName),
        \App\Support\LocalizedRoute::is('creator.index') => route('creator.index'),
        \App\Support\LocalizedRoute::is('studio.index') => route('studio.index'),
        $isStudioSample => route('studio.sample', ['sample' => $routeStudioSample]),
        ($isGuide || $isLegal) && $baseRouteName !== null => route($baseRouteName),
        default => url()->current(),
    };

    $imageEditor = app(\App\Services\GeneratedMediaService::class);
    $metaImageOriginalUrl = $metaImage ? $imageEditor->imageUrl($metaImage) : null;
    $metaImageOriginalUrl = $metaImageOriginalUrl && ! \Illuminate\Support\Str::startsWith($metaImageOriginalUrl, ['http://', 'https://']) ? url($metaImageOriginalUrl) : $metaImageOriginalUrl;
    $homeLogoUrl = $isHome ? asset('logo.png').'?v='.$assetVersion : null;
    $quickCoverUrl = $quickEditTool && isset($quickEditTool['thumbnail']) ? asset($quickEditTool['thumbnail']) : null;
    $studioSampleCoverUrl = $isStudioSample ? asset($studioSample['results'][0]['image']) : null;
    $metaImageUrl = $metaImage ? $imageEditor->imageUrl($metaImage, 'og') : ($studioSampleCoverUrl ?? $quickCoverUrl ?? $homeLogoUrl);
    $metaImageUrl = $metaImageUrl && ! \Illuminate\Support\Str::startsWith($metaImageUrl, ['http://', 'https://']) ? url($metaImageUrl) : $metaImageUrl;
    $metaImageAlt = $metaImage
        ? \Illuminate\Support\Str::limit($metaImage->title ?: $metaImage->prompt, 120, '')
        : ($isStudioSample ? __($studioSample['title']) : ($quickEditTool ? __($quickEditTool['cover_alt'] ?? $quickEditTool['title']) : ($isHome ? $siteName.' logo' : null)));
    $metaImageWidth = $isHome ? 1254 : ($studioSampleCoverUrl ? 800 : ($quickCoverUrl ? 320 : 1200));
    $metaImageHeight = $isHome ? 1254 : ($studioSampleCoverUrl ? 800 : ($quickCoverUrl ? 200 : 630));
    $metaKeywords = $metaImage
        ? $metaImage->tags->map(fn (\App\Models\Tag $tag): string => (string) $tag->getTranslationWithoutFallback('name', $locale))->filter()->implode(', ')
        : ($quickEditTool && isset($quickEditTool['keywords']) ? __($quickEditTool['keywords']) : $siteKeywords);
    $metaRobots = $isIndexable ? 'index,follow,max-image-preview:large' : 'noindex,nofollow';
    $metaLocale = $locale === 'en' ? 'en_US' : 'vi_VN';
    $alternateLocale = $locale === 'en' ? 'vi_VN' : 'en_US';
    $viUrl = $isIndexable && $baseRouteName ? \App\Support\LocalizedRoute::url($baseRouteName, $routeParameters, 'vi') : null;
    $enUrl = $isIndexable && $baseRouteName && $englishEnabled && $englishReady ? \App\Support\LocalizedRoute::url($baseRouteName, $routeParameters, 'en') : null;
    $publishedAt = $metaImage?->published_at?->toIso8601String();
    $modifiedAt = $metaImage?->updated_at?->toIso8601String();
    $schema = [];

    if ($isHome) {
        $organizationId = route('home').'#organization';
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $organizationId,
            'name' => $siteName,
            'url' => route('home'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $homeLogoUrl,
                'width' => 1254,
                'height' => 1254,
            ],
        ];
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => route('home'),
            'description' => $siteDescription,
            'inLanguage' => $locale,
            'publisher' => ['@id' => $organizationId],
        ];
    }

    if (\App\Support\LocalizedRoute::is('gallery.index') || $metaCategory || $metaTag) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => filled($metaTitle) ? $metaTitle : $siteName,
            'url' => $metaUrl,
            'description' => $metaDescription,
            'inLanguage' => $locale,
        ];
    }

    if ($isQuickEdit || $isStudioSample || \App\Support\LocalizedRoute::is('creator.index', 'studio.index')) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => filled($metaTitle) ? $metaTitle : $siteName,
            'url' => $metaUrl,
            'description' => $metaDescription,
            'inLanguage' => $locale,
        ];
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'applicationCategory' => 'MultimediaApplication',
            'operatingSystem' => 'Web',
            'url' => $metaUrl,
            'description' => $metaDescription,
        ];
    }

    if ($isQuickEdit && $quickEditTool !== null && $baseRouteName !== null) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'), 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => __('Quick'), 'item' => route('quick.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => __($quickEditTool['title']), 'item' => route($baseRouteName)],
            ],
        ];
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect(\App\Support\QuickEditTools::faqs((string) request()->route('tool')))
                ->map(fn (array $faq): array => [
                    '@type' => 'Question',
                    'name' => __($faq['question'], ['tool' => __($quickEditTool['title'])]),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => __($faq['answer']),
                    ],
                ])
                ->all(),
        ];
    }

    if ($isGuide || $isLegal) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => filled($metaTitle) ? $metaTitle : ($isLegal ? __('Legal information') : __('User guide')),
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
    <meta property="og:image:width" content="{{ $metaImageWidth }}">
    <meta property="og:image:height" content="{{ $metaImageHeight }}">
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
<meta name="twitter:card" content="{{ $metaImage && $metaImageUrl ? 'summary_large_image' : 'summary' }}">
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
<meta name="theme-color" content="#ffffff">
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
