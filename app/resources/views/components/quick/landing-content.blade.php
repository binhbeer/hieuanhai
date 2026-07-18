@props(['slug', 'tool', 'tools'])

@php
    $relatedTools = collect($tool['related'] ?? [])
        ->filter(fn (string $relatedSlug): bool => isset($tools[$relatedSlug]))
        ->take(3);
    $faqs = collect(\App\Support\QuickEditTools::faqs($slug))
        ->map(fn (array $faq): array => [
            'question' => __($faq['question'], ['tool' => __($tool['title'])]),
            'answer' => __($faq['answer']),
        ]);
@endphp

<article class="border-t border-zinc-200 pt-10 dark:border-white/10">
    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start xl:grid-cols-[minmax(0,1fr)_20rem] xl:gap-14">
        <div class="min-w-0 space-y-12">
            <section aria-labelledby="tool-overview">
                <flux:badge color="amber">{{ __('About this tool') }}</flux:badge>
                <h2 id="tool-overview" class="mt-4 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('What does :tool do?', ['tool' => __($tool['title'])]) }}</h2>
                <div class="prose prose-zinc mt-5 max-w-none dark:prose-invert">
                    <p class="text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ __($tool['overview']) }}</p>
                </div>
            </section>

            <section aria-labelledby="best-use-cases">
                <h2 id="best-use-cases" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('When this tool is useful') }}</h2>
                <p class="mt-3 text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ __('Use a clear source image and describe only the change you need. GenAnh keeps unrelated details outside the request.') }}</p>
                <div class="mt-6 space-y-5">
                    @foreach ($tool['use_cases'] as $index => $useCase)
                        <div class="flex gap-4">
                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-sm font-semibold text-amber-800 dark:bg-amber-300/15 dark:text-amber-200">{{ $index + 1 }}</span>
                            <div>
                                <h3 class="font-semibold text-zinc-950 dark:text-white">{{ __($useCase) }}</h3>
                                <p class="mt-1.5 text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ __('AI reviews the visible content before proposing a concrete request, so the workflow remains relevant to your photo.') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            @if (!empty($tool['content']))
                <section aria-labelledby="tool-details">
                    <h2 id="tool-details" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __($tool['content_heading']) }}</h2>
                    <p class="mt-3 text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ __($tool['content_description']) }}</p>
                    <div class="mt-6 space-y-6">
                        @foreach ($tool['content'] as $item)
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __($item['title']) }}</h3>
                                <p class="mt-2 text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ __($item['body']) }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (!empty($tool['guide']))
                <section aria-labelledby="tool-guide">
                    <h2 id="tool-guide" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Get a more useful result') }}</h2>
                    <p class="mt-3 text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ __('A clear source and a focused request give GenAnh more reliable visual information. Review important details before using or publishing the image.') }}</p>
                    <ol class="mt-6 space-y-4">
                        @foreach ($tool['guide'] as $index => $step)
                            <li class="flex gap-4">
                                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-950 text-sm font-semibold text-white dark:bg-white dark:text-zinc-950">{{ $index + 1 }}</span>
                                <p class="pt-0.5 text-base leading-7 text-zinc-600 dark:text-zinc-300">{{ __($step) }}</p>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            @if ($tool['caution'])
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-300/20 dark:bg-amber-300/10" aria-labelledby="tool-caution">
                    <div class="flex gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-300/15 dark:text-amber-200"><x-iconsax-two-info-circle class="size-5" /></div>
                        <div>
                            <h2 id="tool-caution" class="text-lg font-semibold text-amber-950 dark:text-amber-50">{{ __('Quality and responsible use') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-amber-950/80 dark:text-amber-100/80">{{ __($tool['caution']) }}</p>
                        </div>
                    </div>
                </section>
            @endif

            <section aria-labelledby="tool-faq">
                <h2 id="tool-faq" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Frequently asked questions') }}</h2>
                <div class="mt-6 divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ($faqs as $faq)
                        <details class="group py-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-zinc-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-white">
                                {{ $faq['question'] }}
                                <span class="text-zinc-400 transition group-open:rotate-45 motion-reduce:transition-none" aria-hidden="true">+</span>
                            </summary>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-24">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('At a glance') }}</p>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="font-medium text-zinc-950 dark:text-white">{{ __('Tool') }}</dt>
                        <dd class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __($tool['title']) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-950 dark:text-white">{{ __('Best for') }}</dt>
                        <dd class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __($tool['description']) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-950 dark:text-white">{{ __('What GenAnh aims to preserve') }}</dt>
                        <dd class="mt-1 leading-6 text-zinc-600 dark:text-zinc-300">{{ __($tool['preserves']) }}</dd>
                    </div>
                </dl>
                @auth
                    <flux:button class="mt-5 w-full" type="button" variant="primary" color="amber" x-data x-on:click="$dispatch('open-quick-composer', { tool: @js($slug) })">{{ __('Start Quick Edit') }}</flux:button>
                @else
                    <flux:button class="mt-5 w-full" type="button" variant="primary" color="amber" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Start Quick Edit') }}</flux:button>
                @endauth
            </div>

            @if ($relatedTools->isNotEmpty())
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('Related tools') }}</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($relatedTools as $relatedSlug)
                            @php($related = $tools[$relatedSlug])
                            <a href="{{ route('quick.'.$relatedSlug) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 p-2 pe-3 transition hover:border-amber-300 hover:bg-amber-50/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-white/10 dark:hover:border-amber-300/30 dark:hover:bg-amber-300/5">
                                @if (!empty($related['thumbnail']))
                                    <img src="{{ asset($related['thumbnail']) }}" alt="{{ __($related['cover_alt'] ?? $related['title']) }}" width="56" height="35" class="h-12 w-16 shrink-0 rounded-lg object-cover" loading="lazy" decoding="async">
                                @else
                                    <span class="flex h-12 w-16 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-300"><x-iconsax-two-magic-star class="size-5" /></span>
                                @endif
                                <span class="min-w-0 font-medium text-zinc-950 dark:text-white">{{ __($related['title']) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-300/20 dark:bg-emerald-400/10">
                <h2 class="text-base font-semibold text-emerald-950 dark:text-emerald-50">{{ __('Need more control?') }}</h2>
                <p class="mt-2 text-sm leading-6 text-emerald-950/80 dark:text-emerald-100/80">{{ __('Use Creator for model, size, quality, rewrite, translation, and advanced reference controls.') }}</p>
                <flux:button class="mt-4 w-full" :href="route('creator.index')" variant="outline" wire:navigate>{{ __('Open Creator') }}</flux:button>
            </div>
        </aside>
    </div>
</article>
