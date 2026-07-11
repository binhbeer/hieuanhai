<?php

use Livewire\Component;
new class extends Component {
    //
}; ?>

<section class="w-full">
    <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-settings.layout active="appearance" :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" :icon="svg('iconsax-two-sun-1', 'size-5')">{{ __('Light') }}</flux:radio>
            <flux:radio value="dark" :icon="svg('iconsax-two-moon', 'size-5')">{{ __('Dark') }}</flux:radio>
            <flux:radio value="system" :icon="svg('iconsax-two-monitor', 'size-5')">{{ __('System') }}</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</section>
