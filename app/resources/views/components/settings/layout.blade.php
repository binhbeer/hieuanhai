<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
        <flux:subheading>{{ __('Manage your profile and account settings') }}</flux:subheading>
    </div>

    <flux:tabs scrollable>
        <flux:tab wire:click="$dispatch('open-account-modal', { component: 'settings.profile' })" :selected="request()->routeIs('profile.edit')">{{ __('Profile') }}</flux:tab>
        <flux:tab wire:click="$dispatch('open-account-modal', { component: 'settings.security' })" :selected="request()->routeIs('security.edit')">{{ __('Security') }}</flux:tab>
        <flux:tab wire:click="$dispatch('open-account-modal', { component: 'settings.api-key' })" :selected="request()->routeIs('api-key.edit')">{{ __('API key') }}</flux:tab>
        <flux:tab wire:click="$dispatch('open-account-modal', { component: 'settings.appearance' })" :selected="request()->routeIs('appearance.edit')">{{ __('Appearance') }}</flux:tab>
    </flux:tabs>

    <div>
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full">
            {{ $slot }}
        </div>
    </div>
</div>
