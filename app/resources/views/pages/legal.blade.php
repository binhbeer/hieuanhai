<?php

use App\Support\AppSettings;
use App\Support\LocalizedRoute;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Legal information')] class extends Component {};
?>

@php
    $routeName = LocalizedRoute::name() ?? 'legal.privacy';
    $zaloUrl = Str::replaceStart('http://zalo.me/', 'https://zalo.me/', trim(AppSettings::string('contact.zalo_url')));
    $zaloUrl = Str::startsWith($zaloUrl, 'https://') ? $zaloUrl : null;
    $pages = [
        'legal.privacy' => ['label' => __('Privacy Policy'), 'icon' => 'shield-check'],
        'legal.terms' => ['label' => __('Terms of Service'), 'icon' => 'document-text'],
        'legal.support' => ['label' => __('Support'), 'icon' => 'lifebuoy'],
        'legal.delete-account' => ['label' => __('Delete Account'), 'icon' => 'trash'],
    ];
    $content = match ($routeName) {
        'legal.terms' => [
            'eyebrow' => __('Legal'),
            'title' => __('Terms of Service'),
            'intro' => __('These terms govern your use of GenAnh websites, applications, AI tools, Gallery, Studio, and API.'),
            'sections' => [
                [__('Using GenAnh'), [
                    __('You must provide accurate account information, protect your credentials, and use the service only where permitted by law.'),
                    __('You are responsible for activity under your account and API key. Contact us promptly if you believe either has been compromised.'),
                ]],
                [__('Your uploads and published content'), [
                    __('You retain your rights in content you upload. You confirm that you have permission to upload, process, and publish it.'),
                    __('You grant GenAnh the limited rights needed to process, store, display, and deliver your content as requested. Publishing makes selected images and related information visible in the public Gallery.'),
                ]],
                [__('AI-generated output'), [
                    __('AI output may be inaccurate, unexpected, or similar to content generated for others. Review every result before using or publishing it.'),
                    __('GenAnh does not guarantee that output is unique, error-free, or suitable for a particular commercial, legal, or professional purpose.'),
                ]],
                [__('Prohibited use'), [
                    __('Do not use GenAnh to violate law or rights, impersonate or deceive people, exploit children, create non-consensual sexual content, distribute malware, bypass safeguards, or interfere with the service.'),
                    __('We may reject content, restrict access, suspend accounts, or remove public content when needed to protect users, comply with law, or operate the service safely.'),
                ]],
                [__('Service availability and liability'), [
                    __('Features, quotas, models, and availability may change. We may pause service for maintenance, security, provider outages, or legal requirements.'),
                    __('To the extent allowed by law, GenAnh is provided as is and without warranties. GenAnh is not liable for indirect, incidental, special, or consequential loss arising from your use of the service.'),
                ]],
                [__('Changes and contact'), [
                    __('We may update these terms and publish the revised version here. Continued use after an update means you accept the revised terms.'),
                    __('Questions about these terms can be sent to info@genanh.com.'),
                ]],
            ],
        ],
        'legal.support' => [
            'eyebrow' => __('Help'),
            'title' => __('Support'),
            'intro' => __('Get help with your account, image generation, Studio projects, API access, privacy, or account deletion.'),
            'sections' => [
                [__('Contact support'), [
                    __('Email info@genanh.com with your account email, a short description, and the time the problem occurred.'),
                    __('Do not send passwords, passkeys, recovery codes, full API keys, or other authentication secrets.'),
                ]],
                [__('Before contacting us'), [
                    __('Check your internet connection, retry once, and note any status code or safe error message shown by GenAnh.'),
                    __('For image issues, include the request time and image ID when available, but do not resend sensitive source images unless support asks for them.'),
                ]],
                [__('Response and safety'), [
                    __('We review support requests as soon as practical. Response times are not guaranteed.'),
                    __('If your account or API key may be compromised, stop using the key, regenerate it if possible, and contact us immediately.'),
                ]],
            ],
        ],
        'legal.delete-account' => [
            'eyebrow' => __('Account control'),
            'title' => __('Delete Account'),
            'intro' => __('You can permanently delete your GenAnh account and associated data from this page.'),
            'sections' => [
                [__('Data deleted'), [
                    __('Deletion removes your account and authentication data, sessions, password reset tokens, passkeys, avatar, API keys and request logs.'),
                    __('It also removes your prompts, uploaded references, generated results, private and published images, Studio projects and input files, favorites, and related metadata.'),
                ]],
                [__('Data that may remain temporarily'), [
                    __('Limited copies may remain in encrypted backups until normal backup rotation completes, or longer when required for security, fraud prevention, dispute resolution, or law.'),
                    __('Information that does not identify you, including aggregated service statistics, may be retained.'),
                ]],
                [__('Before you delete'), [
                    __('Download anything you want to keep. Account deletion cannot be undone, and deleted images or projects cannot be restored.'),
                    __('For security, signed-in users must confirm their current password. If you cannot sign in, contact info@genanh.com.'),
                ]],
            ],
        ],
        default => [
            'eyebrow' => __('Privacy'),
            'title' => __('Privacy Policy'),
            'intro' => __('This policy explains what GenAnh processes, why it is used, and the choices available to you.'),
            'sections' => [
                [__('Information we process'), [
                    __('We process account and authentication information such as name, email, password hash, verification state, passkeys, security settings, sessions, IP address, and user agent.'),
                    __('When you use image features, we process prompts, uploaded reference images, generated results, project data, favorites, publication choices, model options, and related metadata.'),
                    __('API use may include API key records, request time, status, quota, duration, IP address, safe error details, and request or response metadata.'),
                    __('When analytics is enabled, usage and device information may be processed to understand service performance and improve GenAnh.'),
                ]],
                [__('Why we use information'), [
                    __('We use information to provide and secure accounts, create and deliver images, manage projects and quotas, operate public Gallery features, provide support, prevent abuse, diagnose failures, and comply with law.'),
                ]],
                [__('Service providers and transfers'), [
                    __('Prompts and reference images needed for generation are sent to the AI provider configured by GenAnh. Hosting, storage, cache, email, security, and analytics providers may process limited data on our behalf.'),
                    __('Providers may process data in other countries under their safeguards and applicable law. We require providers to use data only to deliver contracted services.'),
                ]],
                [__('Sharing and public content'), [
                    __('We do not sell your personal information. We share it only with service providers, when you choose to publish content, when required by law, or when needed to protect rights, safety, and service integrity.'),
                    __('Images are private by default. Images, prompts, titles, descriptions, tags, and account display information become public only when you publish them.'),
                ]],
                [__('Retention and deletion'), [
                    __('Account content is generally kept while your account exists or until you delete individual content. Operational logs are retained only as long as needed for security, support, quota, and legal purposes.'),
                    __('Deleting your account removes active account data and associated files. Limited backup copies may remain until backup rotation completes or when retention is legally required.'),
                ]],
                [__('Your choices and contact'), [
                    __('You can update account details, keep images private, unpublish content, delete individual images or projects, regenerate API keys, and permanently delete your account.'),
                    __('Privacy questions and requests can be sent to info@genanh.com.'),
                ]],
            ],
        ],
    };
@endphp

<section class="mx-auto w-full max-w-5xl space-y-8 px-3 pb-10 sm:px-6 sm:py-5" aria-labelledby="legal-title">
    <header class="space-y-3 border-b border-zinc-200 pb-7 dark:border-white/10">
        <flux:badge color="amber">{{ $content['eyebrow'] }}</flux:badge>
        <h1 id="legal-title" class="text-3xl font-semibold tracking-tight text-zinc-950 sm:text-4xl dark:text-white">{{ $content['title'] }}</h1>
        <p class="max-w-3xl text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ $content['intro'] }}</p>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Last updated: July 19, 2026') }}</p>
    </header>

    <nav aria-label="{{ __('Legal pages') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($pages as $name => $page)
            <flux:button :href="route($name)" :variant="$routeName === $name ? 'primary' : 'outline'" :icon="$page['icon']" wire:navigate>
                {{ $page['label'] }}
            </flux:button>
        @endforeach
    </nav>

    <div class="space-y-10">
        @foreach ($content['sections'] as [$heading, $paragraphs])
            <section class="space-y-3" aria-labelledby="section-{{ $loop->index }}">
                <flux:heading id="section-{{ $loop->index }}" size="xl">{{ $heading }}</flux:heading>
                @foreach ($paragraphs as $paragraph)
                    <p class="max-w-3xl text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ $paragraph }}</p>
                @endforeach
            </section>
        @endforeach
    </div>

    @if ($routeName === 'legal.support')
        <div class="flex flex-wrap gap-3 border-t border-zinc-200 pt-7 dark:border-white/10">
            <flux:button href="mailto:info@genanh.com" variant="primary" icon="envelope">info@genanh.com</flux:button>
            @if ($zaloUrl)
                <flux:button :href="$zaloUrl" target="_blank" rel="noopener noreferrer" variant="outline" icon="chat-bubble-left-right">{{ __('Contact via Zalo') }}</flux:button>
            @endif
        </div>
    @elseif ($routeName === 'legal.delete-account')
        <div class="border-t border-zinc-200 pt-7 dark:border-white/10">
            @auth
                <livewire:settings.delete-user-form />
            @else
                <flux:callout icon="lock-closed">
                    <flux:callout.heading>{{ __('Sign in to delete your account') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('After signing in, return to this page and confirm deletion with your current password.') }}</flux:callout.text>
                    <x-slot name="actions">
                        <flux:button type="button" variant="primary" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Log in') }}</flux:button>
                    </x-slot>
                </flux:callout>
            @endauth
        </div>
    @endif
</section>
