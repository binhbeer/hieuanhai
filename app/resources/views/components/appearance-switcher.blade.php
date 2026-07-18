<div x-data {{ $attributes }}>
    <flux:dropdown position="bottom" align="end">
        <flux:button size="sm" variant="ghost" square aria-label="{{ __('Appearance') }}" tooltip="{{ __('Appearance') }}" tooltip:position="bottom">
            <x-slot name="icon">
                <span class="inline-flex" x-show="! $flux.dark" x-cloak>
                    <x-iconsax-two-sun-1 class="size-5" />
                </span>
                <span class="inline-flex" x-show="$flux.dark" x-cloak>
                    <x-iconsax-two-moon class="size-5" />
                </span>
            </x-slot>
        </flux:button>

        <flux:menu class="min-w-40">
            <flux:menu.radio.group x-model="$flux.appearance">
                <flux:menu.radio value="light">
                    <span class="inline-flex items-center gap-2">
                        <x-iconsax-two-sun-1 class="size-4 shrink-0 opacity-70" />
                        {{ __('Light') }}
                    </span>
                </flux:menu.radio>
                <flux:menu.radio value="dark">
                    <span class="inline-flex items-center gap-2">
                        <x-iconsax-two-moon class="size-4 shrink-0 opacity-70" />
                        {{ __('Dark') }}
                    </span>
                </flux:menu.radio>
                <flux:menu.radio value="system">
                    <span class="inline-flex items-center gap-2">
                        <x-iconsax-two-monitor class="size-4 shrink-0 opacity-70" />
                        {{ __('System') }}
                    </span>
                </flux:menu.radio>
            </flux:menu.radio.group>
        </flux:menu>
    </flux:dropdown>
</div>
