@props(['active'])

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
        <flux:subheading>{{ __('Manage your profile and account settings') }}</flux:subheading>
    </div>

    <flux:tabs scrollable>
        <flux:tab x-data x-on:click="$dispatch('open-account-modal', { component: 'settings.profile' })" :selected="$active === 'profile'">{{ __('Profile') }}</flux:tab>
        <flux:tab x-data x-on:click="$dispatch('open-account-modal', { component: 'settings.security' })" :selected="$active === 'security'">{{ __('Security') }}</flux:tab>
        <flux:tab x-data x-on:click="$dispatch('open-account-modal', { component: 'settings.api-key' })" :selected="$active === 'api-key'">{{ __('API key') }}</flux:tab>
        <flux:tab x-data x-on:click="$dispatch('open-account-modal', { component: 'settings.appearance' })" :selected="$active === 'appearance'">{{ __('Appearance') }}</flux:tab>
    </flux:tabs>

    <div>
        @if (filled($heading ?? null) || filled($subheading ?? null))
            <flux:heading>{{ $heading ?? '' }}</flux:heading>
            @if (filled($subheading ?? null))
                <flux:subheading>{{ $subheading }}</flux:subheading>
            @endif
        @endif

        <div @class(['w-full', 'mt-5' => filled($heading ?? null) || filled($subheading ?? null)])>
            {{ $slot }}
        </div>
    </div>
</div>
