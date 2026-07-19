<?php

use App\Support\LocalizedRoute;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('User guide')] class extends Component {
}; ?>

@php
    $routeName = LocalizedRoute::name() ?? 'guide.index';
    $guidePages = [
        'guide.index' => [
            'label' => __('Overview'),
            'description' => __('Choose the right guide for your goal.'),
            'icon' => 'home',
        ],
        'guide.getting-started' => [
            'label' => __('Getting started'),
            'description' => __('Create and download your first AI image.'),
            'icon' => 'rocket-launch',
        ],
        'guide.web' => [
            'label' => __('Web app'),
            'description' => __('Manage, edit, publish, and discover images.'),
            'icon' => 'window',
        ],
        'guide.api' => [
            'label' => __('API for developers'),
            'description' => __('Connect your application with an API key.'),
            'icon' => 'code-bracket',
        ],
        'guide.faq' => [
            'label' => __('FAQ & account'),
            'description' => __('Understand quotas, privacy, and account security.'),
            'icon' => 'question-mark-circle',
        ],
    ];

    $content = match ($routeName) {
        'guide.getting-started' => [
            'eyebrow' => __('Start here'),
            'title' => __('Create your first AI image'),
            'intro' => __('From signing in to downloading a finished image, follow this practical workflow.'),
            'readingTime' => __('5 minute guide'),
            'sections' => [
                [
                    'id' => 'sign-in',
                    'number' => '01',
                    'title' => __('Sign in and verify your email'),
                    'body' => __('Select Create image. If you are not signed in, the account panel opens automatically. Create an account or sign in, then verify your email to keep creating images after your registration day.'),
                    'tips' => [
                        __('Use a strong password or a passkey.'),
                        __('Keep your recovery codes somewhere safe when you enable 2FA.'),
                    ],
                ],
                [
                    'id' => 'write-prompt',
                    'number' => '02',
                    'title' => __('Describe the image you want'),
                    'body' => __('Write a clear prompt with subject, setting, composition, lighting, and style. You can add reference images when appearance or layout matters.'),
                    'tips' => [
                        __('Describe important details first.'),
                        __('Use prompt tools to rewrite, analyze a reference image, or translate when available.'),
                    ],
                ],
                [
                    'id' => 'generate',
                    'number' => '03',
                    'title' => __('Choose options and generate'),
                    'body' => __('Select aspect ratio and resolution, review your references, then start generation. Only one request can remain pending for your account at a time.'),
                    'tips' => [
                        __('Square works well for avatars and product thumbnails.'),
                        __('Portrait ratios fit posters and social media posts.'),
                    ],
                ],
                [
                    'id' => 'download',
                    'number' => '04',
                    'title' => __('Review and download the result'),
                    'body' => __('The Created images page shows queued, reviewing, generating, saving, and completed states. Open a completed image to download, edit, publish, or create a similar version.'),
                    'tips' => [
                        __('Retry failed requests without rewriting the prompt.'),
                        __('Publishing is optional; new images stay private by default.'),
                    ],
                ],
            ],
            'screenshots' => [
                [
                    'file' => 'getting-started-composer.webp',
                    'alt' => __('Image composer with prompt, references, options, and create button'),
                    'caption' => __('Image composer: prepare all inputs before sending a generation request.'),
                    'markers' => [
                        ['number' => 1, 'x' => 50, 'y' => 31, 'text' => __('Write a detailed prompt describing the expected result.')],
                        ['number' => 2, 'x' => 50, 'y' => 53, 'text' => __('Add reference images when you need visual consistency.')],
                        ['number' => 3, 'x' => 28, 'y' => 72, 'text' => __('Choose ratio, resolution, and reference quality.')],
                        ['number' => 4, 'x' => 78, 'y' => 91, 'text' => __('Check the request, then select Create image.')],
                    ],
                ],
                [
                    'file' => 'mobile-composer.webp',
                    'width' => 390,
                    'height' => 844,
                    'alt' => __('Image composer on a mobile screen'),
                    'caption' => __('Mobile composer: the same workflow remains available in a focused full-screen panel.'),
                    'markers' => [
                        ['number' => 1, 'x' => 50, 'y' => 43, 'text' => __('Scroll through prompt, references, and options in order.')],
                        ['number' => 2, 'x' => 50, 'y' => 92, 'text' => __('Review the final action at the bottom of the panel.')],
                    ],
                ],
            ],
        ],
        'guide.web' => [
            'eyebrow' => __('Web app workflow'),
            'title' => __('Manage your complete image workflow'),
            'intro' => __('Track generations, improve results, publish your best work, and collect ideas from the community.'),
            'readingTime' => __('8 minute guide'),
            'sections' => [
                [
                    'id' => 'history',
                    'number' => '01',
                    'title' => __('Track images and daily quota'),
                    'body' => __('Created images lists every request owned by your account. Filter by status or publication state, sort recent work, and watch pending jobs update automatically.'),
                    'tips' => [
                        __('Pending and successful generations count toward the daily web quota.'),
                        __('Administrators are not limited by daily web quota.'),
                    ],
                ],
                [
                    'id' => 'improve',
                    'number' => '02',
                    'title' => __('Edit or create a similar image'),
                    'body' => __('Edit image opens the original prompt and image as references for a new generation. Create similar image reuses only the prompt. Your original result is never overwritten.'),
                    'tips' => [
                        __('Use Edit image when the current composition should remain recognizable.'),
                        __('Use Create similar image when you only want the idea or writing style.'),
                    ],
                ],
                [
                    'id' => 'publish',
                    'number' => '03',
                    'title' => __('Publish and unpublish safely'),
                    'body' => __('Publish makes a successful image visible in the public gallery. Unpublish removes it from discovery while keeping it in your account.'),
                    'tips' => [
                        __('Review the prompt and reference images before publishing.'),
                        __('Only publish content you are allowed to share.'),
                    ],
                ],
                [
                    'id' => 'discover',
                    'number' => '04',
                    'title' => __('Search, favorite, and reuse ideas'),
                    'body' => __('Browse categories and tags, search titles or prompts, save favorites, copy a public prompt, or start your own version from a published image.'),
                    'tips' => [
                        __('Favorites are private to your account.'),
                        __('Category and tag filters help narrow visual styles quickly.'),
                    ],
                ],
            ],
            'screenshots' => [
                [
                    'file' => 'web-history.webp',
                    'alt' => __('Created images page with quota, filters, statuses, and image actions'),
                    'caption' => __('Created images: monitor usage and manage every result from one place.'),
                    'markers' => [
                        ['number' => 1, 'x' => 52, 'y' => 31, 'text' => __('Review daily usage and remaining web quota.')],
                        ['number' => 2, 'x' => 44, 'y' => 60, 'text' => __('Filter by generation status or publication state.')],
                        ['number' => 3, 'x' => 66, 'y' => 83, 'text' => __('Open an image to download, edit, publish, retry, or delete it.')],
                    ],
                ],
                [
                    'file' => 'web-gallery.webp',
                    'alt' => __('Community gallery with category navigation and image cards'),
                    'caption' => __('Community gallery: discover ideas without exposing your private images.'),
                    'markers' => [
                        ['number' => 1, 'x' => 8, 'y' => 40, 'text' => __('Choose a category to focus the gallery.')],
                        ['number' => 2, 'x' => 50, 'y' => 20, 'text' => __('Switch between new and featured images.')],
                        ['number' => 3, 'x' => 60, 'y' => 67, 'text' => __('Open a published image for its prompt, details, and reuse actions.')],
                    ],
                ],
                [
                    'file' => 'mobile-gallery.webp',
                    'width' => 390,
                    'height' => 844,
                    'alt' => __('Community gallery on a mobile screen'),
                    'caption' => __('Mobile gallery: creation and navigation stay within thumb reach.'),
                    'markers' => [
                        ['number' => 1, 'x' => 8, 'y' => 4, 'text' => __('Open the mobile sidebar for guide, categories, search, and favorites.')],
                        ['number' => 2, 'x' => 92, 'y' => 4, 'text' => __('Create an image from the persistent header action.')],
                        ['number' => 3, 'x' => 50, 'y' => 50, 'text' => __('Tap an image card to open details and reuse actions.')],
                    ],
                ],
            ],
        ],
        'guide.api' => [
            'eyebrow' => __('Developer guide'),
            'title' => __('Create images through the API'),
            'intro' => __('Generate a key, send a secure request, and understand quota and error responses.'),
            'readingTime' => __('10 minute guide'),
            'sections' => [
                [
                    'id' => 'key',
                    'number' => '01',
                    'title' => __('Generate and protect your API key'),
                    'body' => __('Open Account, then API key. Each account has one active key. Regenerating invalidates the previous token immediately while preserving usage statistics.'),
                    'tips' => [
                        __('Send the token only in the Authorization header.'),
                        __('Never place an API key in browser code, screenshots, or a public repository.'),
                    ],
                ],
                [
                    'id' => 'request',
                    'number' => '02',
                    'title' => __('Send a generation request'),
                    'body' => __('Use JSON for prompt-only requests or multipart form data when you include reference images. Requests are accepted only through the API subdomain. The prompt is required and accepts up to 1200 words.'),
                    'tips' => [
                        __('POST /api/ai/images creates a private image.'),
                        __('POST /api/ai/images/publish creates and publishes the result.'),
                    ],
                ],
                [
                    'id' => 'quota',
                    'number' => '03',
                    'title' => __('Read quota and request logs'),
                    'body' => __('API quota is separate from daily web quota. One quota is charged only after a successful generation. Validation, moderation, quota, and provider failures do not consume it.'),
                    'tips' => [
                        __('Use the latest request logs to diagnose status codes and duration.'),
                        __('Regenerating a token does not reset quota or logs.'),
                    ],
                ],
                [
                    'id' => 'errors',
                    'number' => '04',
                    'title' => __('Handle errors by status code'),
                    'body' => __('Treat 401 as an invalid key, 403 as a blocked account, 422 as invalid or rejected input, 429 as exhausted quota, and 503 as a temporary review failure.'),
                    'tips' => [
                        __('Do not retry 401, 403, 422, or 429 without correcting the cause.'),
                        __('Use bounded retries with backoff for temporary 503 or network failures.'),
                    ],
                ],
            ],
            'screenshots' => [
                [
                    'file' => 'api-key.webp',
                    'alt' => __('API key settings with masked key, quota, statistics, and logs'),
                    'caption' => __('API key settings: keep credentials hidden while monitoring usage.'),
                    'markers' => [
                        ['number' => 1, 'x' => 53, 'y' => 33, 'text' => __('Reveal or copy the key only when you are ready to store it securely.')],
                        ['number' => 2, 'x' => 57, 'y' => 52, 'text' => __('Check used and remaining lifetime API quota.')],
                        ['number' => 3, 'x' => 55, 'y' => 76, 'text' => __('Review recent request status, duration, and safe error summaries.')],
                    ],
                ],
            ],
        ],
        'guide.faq' => [
            'eyebrow' => __('Help & security'),
            'title' => __('Frequently asked questions'),
            'intro' => __('Quick answers about quota, privacy, account access, and common generation problems.'),
            'readingTime' => __('6 minute guide'),
            'sections' => [],
            'screenshots' => [],
        ],
        default => [
            'eyebrow' => __('GenAnh knowledge base'),
            'title' => __('Create better images with confidence'),
            'intro' => __('Step-by-step guides for image creation, workflow management, publishing, account security, and API integration.'),
            'readingTime' => __('Updated product guide'),
            'sections' => [],
            'screenshots' => [
                [
                    'file' => 'overview-gallery.webp',
                    'alt' => __('GenAnh gallery with sidebar navigation, sorting, and image creation button'),
                    'caption' => __('Start from the gallery: create an image, browse categories, or open the guide at any time.'),
                    'markers' => [
                        ['number' => 1, 'x' => 9, 'y' => 21, 'text' => __('Use the main navigation to open tools, search, favorites, and this guide.')],
                        ['number' => 2, 'x' => 48, 'y' => 8, 'text' => __('Sort the gallery to discover new or featured work.')],
                        ['number' => 3, 'x' => 96, 'y' => 8, 'text' => __('Select Create image from any page when you are signed in.')],
                    ],
                ],
            ],
        ],
    };

    $faqs = [
        [__('Are my generated images public by default?'), __('No. A new image stays private in your account. It appears in the community gallery only after you publish it.')],
        [__('What is the difference between web quota and API quota?'), __('Web quota limits daily generations from the site. API quota is a separate lifetime allowance for authenticated API requests.')],
        [__('Why can I not create another image?'), __('Check whether another request is still pending, your daily quota is exhausted, or your email needs verification. The Created images page shows pending work and remaining usage.')],
        [__('Does editing replace my original image?'), __('No. Edit image creates a new child result using the original prompt and image as references. The original remains unchanged.')],
        [__('When does an API request consume quota?'), __('API quota is charged only after generation succeeds. Invalid input, rejected content, exhausted quota, and provider failures are not charged.')],
        [__('What should I do if an image fails?'), __('Open the failed image to read its safe error message, then retry. Adjust the prompt or reference image if validation or content review rejected the request.')],
        [__('How do I secure my account?'), __('Verify your email, use a unique password or passkey, enable two-factor authentication, and store recovery codes offline.')],
        [__('What happens when I regenerate an API key?'), __('The old token stops working immediately. Your quota, request statistics, and logs remain attached to the same key record.')],
    ];

    $isApiGuide = $routeName === 'guide.api';
    $focusRing = $isApiGuide ? 'focus-visible:ring-amber-500' : 'focus-visible:ring-violet-500';
    $markerBg = $isApiGuide ? 'bg-amber-500' : 'bg-violet-600';
    $chipClass = $isApiGuide
        ? 'rounded-full border border-amber-200/90 bg-white/80 px-3 py-1.5 text-xs font-medium text-zinc-700 dark:border-white/15 dark:bg-white/5 dark:text-zinc-300'
        : 'rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 dark:border-white/15 dark:bg-white/5 dark:text-zinc-300';
    $navActive = $isApiGuide
        ? 'bg-amber-100 text-amber-950 dark:bg-amber-400 dark:text-amber-950'
        : 'bg-zinc-100 text-zinc-950 dark:bg-white dark:text-zinc-950';
    $navActiveMuted = $isApiGuide
        ? 'text-amber-800/80 dark:text-amber-950/70'
        : 'text-zinc-500 dark:text-zinc-600';
@endphp

<section class="mx-auto w-full max-w-7xl space-y-8 px-3 pb-6 sm:px-6 sm:pb-10 sm:py-5">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('home')" wire:navigate>{{ __('Home') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('User guide') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <section class="max-w-3xl" aria-labelledby="guide-title">
        @if ($isApiGuide)
            <flux:badge color="amber" icon="code-bracket">{{ __('For developers') }}</flux:badge>
        @else
            <flux:badge color="violet" icon="book-open">{{ $content['eyebrow'] }}</flux:badge>
        @endif
        <h1 id="guide-title" class="mt-3 text-2xl font-semibold tracking-[-.04em] text-zinc-950 sm:text-3xl lg:text-4xl dark:text-white">{{ $content['title'] }}</h1>
        <p class="mt-3 text-base leading-6 sm:leading-7 text-zinc-600 sm:text-lg dark:text-zinc-300">{{ $content['intro'] }}</p>
        <div class="mt-5 flex flex-wrap items-center gap-3">
            @if ($routeName === 'guide.index')
                <flux:button :href="route('guide.getting-started')" variant="primary" color="violet" icon:trailing="arrow-right" wire:navigate>{{ __('Start with your first image') }}</flux:button>
                <flux:button :href="route('guide.api')" variant="outline" color="amber" icon="code-bracket" wire:navigate>{{ __('Explore the API') }}</flux:button>
            @elseif ($isApiGuide)
                <span class="{{ $chipClass }}">POST /api/ai/images</span>
                <span class="{{ $chipClass }}">{{ __('Bearer token') }}</span>
                <span class="{{ $chipClass }}">{{ __('Quota aware') }}</span>
            @endif
        </div>
    </section>

    <div class="grid items-start gap-8 lg:grid-cols-[17rem_minmax(0,1fr)]">
        <aside class="lg:sticky lg:top-6" aria-label="{{ __('Guide sections') }}">
            <nav class="rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-white/5">
                @foreach ($guidePages as $name => $page)
                    <a class="group flex items-start gap-3 rounded-xl px-3 py-3 transition focus-visible:outline-none focus-visible:ring-2 {{ $focusRing }} {{ $routeName === $name ? $navActive : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/10' }}" href="{{ route($name) }}" @if ($routeName === $name) aria-current="page" @endif wire:navigate>
                        <flux:icon :name="$page['icon']" class="mt-0.5 size-5 shrink-0" />
                        <span>
                            <span class="block text-sm font-medium">{{ $page['label'] }}</span>
                            <span class="mt-0.5 block text-xs leading-5 {{ $routeName === $name ? $navActiveMuted : 'text-zinc-500 dark:text-zinc-400' }}">{{ $page['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="min-w-0 space-y-10">
            @if ($routeName === 'guide.index')
                <section aria-labelledby="choose-guide">
                    <div class="max-w-2xl">
                        <flux:heading id="choose-guide" level="2" size="xl">{{ __('Choose your path') }}</flux:heading>
                        <flux:text class="mt-2 text-base! leading-7!">{{ __('Each guide focuses on one outcome, with real screens and numbered notes showing exactly where to act.') }}</flux:text>
                    </div>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        @foreach (array_slice($guidePages, 1, null, true) as $name => $page)
                            @php($pathColor = $name === 'guide.api' ? 'amber' : 'violet')
                            <a class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 {{ $focusRing }} focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-800" href="{{ route($name) }}" wire:navigate>
                                <flux:callout class="h-full transition group-hover:-translate-y-0.5 group-hover:shadow-lg">
                                    <x-slot name="icon">
                                        <flux:icon :name="$page['icon']" class="size-5 {{ $pathColor === 'amber' ? 'text-amber-600 dark:text-amber-400' : 'text-violet-600 dark:text-violet-400' }}" />
                                    </x-slot>
                                    <flux:callout.heading>{{ $page['label'] }}</flux:callout.heading>
                                    <flux:callout.text>
                                        <p>{{ $page['description'] }}</p>
                                        <p class="mt-3 inline-flex items-center gap-1 font-medium {{ $pathColor === 'amber' ? 'text-amber-700 dark:text-amber-300' : 'text-violet-600 dark:text-violet-300' }}">{{ __('Open guide') }} <flux:icon.arrow-right class="size-4 transition group-hover:translate-x-0.5" /></p>
                                    </flux:callout.text>
                                </flux:callout>
                            </a>
                        @endforeach
                    </div>
                </section>
            @elseif ($routeName === 'guide.faq')
                <section class="space-y-4" aria-labelledby="faq-heading">
                    <div class="max-w-2xl">
                        <flux:heading id="faq-heading" level="2" size="xl">{{ __('Common questions') }}</flux:heading>
                        <flux:text class="mt-2 text-base! leading-7!">{{ __('Clear answers for the decisions that affect privacy, usage, and security.') }}</flux:text>
                    </div>
                    <flux:accordion transition>
                        @foreach ($faqs as [$question, $answer])
                            <flux:accordion.item :heading="$question">
                                {{ $answer }}
                            </flux:accordion.item>
                        @endforeach
                    </flux:accordion>
                </section>

                <flux:callout>
                    <x-slot name="icon">
                        <flux:icon name="lifebuoy" class="size-5 text-violet-600 dark:text-violet-400" />
                    </x-slot>
                    <flux:callout.heading>{{ __('Still need help?') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Open the relevant image or request log first. Its current status and safe error message usually identify the next step.') }}</flux:callout.text>
                </flux:callout>
            @else
                <nav class="flex gap-2 overflow-x-auto pb-2 lg:hidden" aria-label="{{ __('On this page') }}">
                    @foreach ($content['sections'] as $section)
                        <a class="shrink-0 rounded-full border border-zinc-200 px-3 py-1.5 text-sm text-zinc-700 dark:border-white/10 dark:text-zinc-200" href="#{{ $section['id'] }}">{{ $section['number'] }} · {{ $section['title'] }}</a>
                    @endforeach
                </nav>

                <div class="space-y-6">
                    @foreach ($content['sections'] as $section)
                        <article id="{{ $section['id'] }}" class="scroll-mt-6">
                            <flux:callout>
                                <x-slot name="icon">
                                    <span class="inline-flex size-5 items-center justify-center rounded text-[10px] font-semibold leading-none {{ $isApiGuide ? 'bg-amber-500 text-white' : 'bg-violet-600 text-white' }}">{{ $section['number'] }}</span>
                                </x-slot>
                                <flux:callout.heading>{{ $section['title'] }}</flux:callout.heading>
                                <flux:callout.text>
                                    <p>{{ $section['body'] }}</p>
                                    <ul class="mt-4 space-y-2">
                                        @foreach ($section['tips'] as $tip)
                                            <li class="flex items-start gap-2.5">
                                                <flux:icon.check-circle class="mt-0.5 size-4 shrink-0 {{ $isApiGuide ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}" aria-hidden="true" />
                                                <span>{{ $tip }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </flux:callout.text>
                            </flux:callout>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($routeName === 'guide.api')
                <section class="space-y-4" aria-labelledby="api-example">
                    <div>
                        <flux:heading id="api-example" level="2" size="xl">{{ __('Working cURL example') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Replace the placeholder token and local image path before running this request.') }}</flux:text>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950 shadow-xl">
                        <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 text-xs text-zinc-400">
                            <span>POST /api/ai/images</span>
                            <span>multipart/form-data</span>
                        </div>
                        <pre class="overflow-x-auto p-5 text-sm leading-7 text-zinc-100"><code>curl -X POST https://api.{{ parse_url((string) config('app.url'), PHP_URL_HOST) }}/api/ai/images \
  -H "Authorization: Bearer genanh_xxx" \
  -F "prompt=A cinematic product photo with soft studio lighting" \
  -F "model=cx/gpt-5.5-image" \
  -F "images[]=@/path/to/reference.jpg"</code></pre>
                    </div>
                    <flux:callout>
                        <x-slot name="icon">
                            <flux:icon name="shield-check" class="size-5 text-amber-600 dark:text-amber-400" />
                        </x-slot>
                        <flux:callout.heading>{{ __('Protect the token') }}</flux:callout.heading>
                        <flux:callout.text>{{ __('The example uses a placeholder. Keep the real key in a server-side secret manager or environment variable, never in client-side code.') }}</flux:callout.text>
                    </flux:callout>
                </section>
            @endif

            @foreach ($content['screenshots'] as $screenshot)
                <figure class="space-y-4">
                    <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 shadow-xl shadow-zinc-950/10 dark:border-white/10 dark:bg-zinc-900 {{ ($screenshot['width'] ?? 1440) < 600 ? 'mx-auto max-w-sm' : '' }}">
                        <img class="h-auto w-full" src="{{ asset('images/guide/'.$screenshot['file']) }}" alt="{{ $screenshot['alt'] }}" width="{{ $screenshot['width'] ?? 1440 }}" height="{{ $screenshot['height'] ?? 900 }}" loading="lazy">
                        @foreach ($screenshot['markers'] as $marker)
                            <span class="absolute flex size-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white {{ $markerBg }} text-sm font-bold text-white shadow-lg shadow-black/30" style="left: {{ $marker['x'] }}%; top: {{ $marker['y'] }}%" aria-hidden="true">{{ $marker['number'] }}</span>
                        @endforeach
                    </div>
                    <figcaption>
                        <p class="font-medium text-zinc-950 dark:text-white">{{ $screenshot['caption'] }}</p>
                        <ol class="mt-3 grid gap-3 sm:grid-cols-2">
                            @foreach ($screenshot['markers'] as $marker)
                                <li class="flex items-start gap-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full {{ $markerBg }} text-xs font-semibold text-white">{{ $marker['number'] }}</span>
                                    <span>{{ $marker['text'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </figcaption>
                </figure>
            @endforeach

            <section aria-labelledby="guide-next-step">
                <flux:callout>
                    <x-slot name="icon">
                        <flux:icon :name="$isApiGuide ? 'key' : 'sparkles'" class="size-5 {{ $isApiGuide ? 'text-amber-600 dark:text-amber-400' : 'text-violet-600 dark:text-violet-400' }}" />
                    </x-slot>
                    <flux:callout.heading id="guide-next-step">{{ __('Ready for the next step?') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Put the guide into practice, or continue with the next topic when you need a deeper workflow.') }}</flux:callout.text>
                    <x-slot name="actions">
                        @if ($isApiGuide)
                            @auth
                                <flux:button type="button" variant="primary" color="amber" icon="key" x-data x-on:click="$dispatch('open-account-modal', { component: 'settings.api-key' })">{{ __('Open API key') }}</flux:button>
                            @else
                                <flux:button type="button" variant="primary" color="amber" icon="key" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Log in for an API key') }}</flux:button>
                            @endauth
                        @else
                            @auth
                                <flux:modal.trigger name="image-composer">
                                    <flux:button type="button" variant="primary" color="emerald" icon="sparkles" x-data x-on:click="$dispatch('open-image-composer')">{{ __('Create image') }}</flux:button>
                                </flux:modal.trigger>
                            @else
                                <flux:button type="button" variant="primary" color="emerald" icon="sparkles" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Log in to create images') }}</flux:button>
                            @endauth
                        @endif
                        @if ($routeName !== 'guide.faq')
                            <flux:button :href="route('guide.faq')" variant="ghost" icon:trailing="arrow-right" wire:navigate>{{ __('Read common questions') }}</flux:button>
                        @endif
                    </x-slot>
                </flux:callout>
            </section>
        </div>
    </div>
</section>
