@auth
	<flux:dropdown position="bottom" align="start" {{ $attributes }}>
		<flux:sidebar.profile data-test="sidebar-menu-button" :name="auth()->user()->name" :initials="auth()->user()->initials()" :avatar="auth()->user()->avatar_path ? Storage::url(auth()->user()->avatar_path) : null" icon:trailing="chevrons-up-down" />

		<flux:menu>
			<div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
				<flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" :src="auth()->user()->avatar_path ? Storage::url(auth()->user()->avatar_path) : null" />
				<div class="grid flex-1 text-start text-sm leading-tight">
					<flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
					<flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
				</div>
			</div>
			<flux:menu.separator />
			<flux:menu.radio.group>
				@foreach ([['component' => 'settings.profile', 'label' => __('Profile'), 'icon' => 'iconsax-two-profile'], ['component' => 'settings.security', 'label' => __('Security'), 'icon' => 'iconsax-two-shield-security'], ['component' => 'settings.api-key', 'label' => __('API key'), 'icon' => 'iconsax-two-key'], ['component' => 'settings.appearance', 'label' => __('Appearance'), 'icon' => 'iconsax-two-color-swatch']] as $setting)
					<flux:menu.item class="cursor-pointer" as="button" type="button" onclick="Livewire.dispatch('open-account-modal', { component: '{{ $setting['component'] }}' })">
						<x-slot name="icon"><x-dynamic-component class="mr-1.5 size-5" :component="$setting['icon']" /></x-slot>
						{{ $setting['label'] }}
					</flux:menu.item>
				@endforeach
				@if (auth()->user()?->isAdmin())
					<flux:menu.separator />
					<flux:menu.item :href="route('manage.index')" wire:navigate>
						<x-slot name="icon"><x-iconsax-two-setting-2 class="mr-1.5 size-5" /></x-slot>
						{{ __('Manage') }}
					</flux:menu.item>
				@endif
				<flux:menu.separator />
				<flux:menu.item :href="route('guide.index')" wire:navigate>
					<x-slot name="icon"><x-iconsax-two-book class="mr-1.5 size-5" /></x-slot>
					{{ __('User guide') }}
				</flux:menu.item>
				<form class="w-full" method="POST" action="{{ route('logout') }}">
					@csrf
					<flux:menu.item class="w-full cursor-pointer" data-test="logout-button" as="button" type="submit">
						<x-slot name="icon"><x-iconsax-two-logout class="mr-1.5 size-5" /></x-slot>
						{{ __('Log out') }}
					</flux:menu.item>
				</form>
			</flux:menu.radio.group>
		</flux:menu>
	</flux:dropdown>
@else
<div class="space-y-2" {{ $attributes }}>
	<div class="grid grid-cols-2 gap-2">
		<flux:button class="w-full" type="button" variant="outline" x-data x-on:click="Livewire.dispatch('open-account-modal', { component: 'auth.login' })">
			{{ __('Log in') }}
		</flux:button>
		@if (\App\Support\AppSettings::bool('auth.registration_enabled', true))
			<flux:button class="w-full" type="button" variant="primary" x-data x-on:click="Livewire.dispatch('open-account-modal', { component: 'auth.register' })">
				{{ __('Register') }}
			</flux:button>
		@endif
	</div>
</div>
@endauth