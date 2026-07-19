<?php

use App\Support\StudioSamples;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Studio design example')] class extends Component {
    /** @var array<string, mixed> */
    public array $sampleData = [];

    public string $slug = '';

    public function mount(string $sample): void
    {
        $this->sampleData = StudioSamples::get($sample) ?? abort(404);
        $this->slug = $sample;
    }
};
?>

<section class="mx-auto w-full max-w-7xl space-y-10 px-3 pb-8 sm:px-6 sm:py-5 sm:pb-12">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('home')" wire:navigate>{{ __('Home') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('studio.index')" wire:navigate>{{ __('Studio') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __($sampleData['title']) }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <header class="grid items-end gap-6 lg:grid-cols-[minmax(0,1fr)_auto]">
        <div class="max-w-3xl">
            <flux:badge :color="$sampleData['tool'] === 'marketing-poster' ? 'sky' : 'emerald'">
                {{ $sampleData['tool'] === 'marketing-poster' ? __('Marketing poster') : __('Product detail images') }}
            </flux:badge>
            <h1 class="mt-3 text-3xl font-semibold tracking-[-.04em] text-zinc-950 sm:text-4xl dark:text-white">{{ __($sampleData['title']) }}</h1>
            <p class="mt-3 text-base leading-7 text-zinc-600 sm:text-lg dark:text-zinc-300">{{ __($sampleData['description']) }}</p>
        </div>

        <div class="flex flex-wrap gap-3">
            @auth
                <flux:button :href="route('studio.index', ['wizard' => 1, 'tool' => $sampleData['tool']])" variant="primary" color="violet" icon="sparkles" wire:navigate>
                    {{ __('Create design') }}
                </flux:button>
            @else
                <flux:button type="button" variant="primary" color="violet" icon="sparkles" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">
                    {{ __('Create design') }}
                </flux:button>
            @endauth
        </div>
    </header>

    <section class="space-y-4" aria-labelledby="sample-inputs">
        <div>
            <flux:heading id="sample-inputs" size="xl">{{ count($sampleData['inputs']) === 1 ? __('Product image') : __('Reference images') }}</flux:heading>
            <flux:text class="mt-1">{{ __('These source images define the products and visual identity used by the workflow.') }}</flux:text>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($sampleData['inputs'] as $input)
                <figure class="space-y-2">
                    <div class="overflow-hidden rounded-2xl bg-zinc-100 dark:bg-white/5">
                        <img class="aspect-square size-full object-cover" src="{{ asset($input['image']) }}" alt="{{ __($input['label']) }}" width="800" height="800">
                    </div>
                    <figcaption class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __($input['label']) }}</figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    <section class="space-y-4" aria-labelledby="sample-results">
        <div>
            <flux:heading id="sample-results" size="xl">{{ __('Results') }}</flux:heading>
            <flux:text class="mt-1">{{ __('One guided workflow turns the inputs into production-ready visual assets.') }}</flux:text>
        </div>
        <div class="grid items-start gap-5 {{ count($sampleData['results']) === 1 ? 'max-w-xl' : 'sm:grid-cols-2' }}">
            @foreach ($sampleData['results'] as $result)
                <figure class="space-y-2">
                    <div class="overflow-hidden rounded-2xl bg-zinc-100 dark:bg-white/5">
                        <img class="h-auto w-full" src="{{ asset($result['image']) }}" alt="{{ __($result['label']) }}" width="800" height="800" loading="lazy">
                    </div>
                    <figcaption>
                        <p class="font-medium text-zinc-950 dark:text-white">{{ __($result['label']) }}</p>
                        <p class="mt-0.5 text-sm text-zinc-500">800 × 800 · 1:1</p>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    <flux:callout>
        <x-slot name="icon">
            <flux:icon name="sparkles" class="size-5 text-violet-600 dark:text-violet-400" />
        </x-slot>
        <flux:callout.heading>{{ __('Create your own version') }}</flux:callout.heading>
        <flux:callout.text>{{ __('Open the matching Studio workflow, upload your own products, and choose the results you need.') }}</flux:callout.text>
        <x-slot name="actions">
            @auth
                <flux:button :href="route('studio.index', ['wizard' => 1, 'tool' => $sampleData['tool']])" variant="primary" color="violet" wire:navigate>{{ __('Create design') }}</flux:button>
            @else
                <flux:button type="button" variant="primary" color="violet" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Create design') }}</flux:button>
            @endauth
            <flux:button :href="route('studio.index')" variant="ghost" wire:navigate>{{ __('Back to Studio') }}</flux:button>
        </x-slot>
    </flux:callout>
</section>
