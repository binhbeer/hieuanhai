@props(['view'])

<header class="space-y-6 pt-4 text-center sm:pt-8">
    <flux:tabs class="mx-auto w-fit" variant="segmented" size="sm">
        <flux:tab :href="route('skills.index')" :selected="$view === 'plaza'" wire:navigate>{{ __('AI tools') }}</flux:tab>
        @auth
            <flux:tab :href="route('skills.index', ['view' => 'projects'])" :selected="$view === 'projects'" wire:navigate>{{ __('My projects') }}</flux:tab>
        @else
            <flux:tab type="button" action x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('My projects') }}</flux:tab>
        @endauth
    </flux:tabs>

    @if ($view === 'plaza')
        <div>
            <flux:heading size="xl">{{ __('Complex designs, one click away') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Smart workflows made for common creative tasks.') }}</flux:text>
        </div>
    @else
        <div>
            <flux:heading size="xl">{{ __('My projects') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Continue drafts and follow generated results.') }}</flux:text>
        </div>
    @endif
</header>
