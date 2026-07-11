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
				@if (auth()->user()?->isAdmin())
					<flux:menu.item :href="route('manage.index')" wire:navigate>
						<x-slot name="icon"><x-iconsax-bul-setting-2 class="size-5 mr-1.5" /></x-slot>
						{{ __('Manage') }}
					</flux:menu.item>
				@endif
				@foreach ([
					['component' => 'settings.profile', 'label' => __('Profile')],
					['component' => 'settings.security', 'label' => __('Security')],
					['component' => 'settings.api-key', 'label' => __('API key')],
					['component' => 'settings.appearance', 'label' => __('Appearance')],
				] as $setting)
					<flux:menu.item as="button" type="button" onclick="Livewire.dispatch('open-account-modal', { component: '{{ $setting['component'] }}' })">
						<x-slot name="icon"><x-iconsax-bul-setting-2 class="size-5 mr-1.5" /></x-slot>
						{{ $setting['label'] }}
					</flux:menu.item>
				@endforeach
				<form class="w-full" method="POST" action="{{ route('logout') }}">
					@csrf
					<flux:menu.item class="w-full cursor-pointer" data-test="logout-button" as="button" type="submit">
						<x-slot name="icon"><x-iconsax-two-logout class="size-5 mr-1.5" /></x-slot>
						{{ __('Log out') }}
					</flux:menu.item>
				</form>
			</flux:menu.radio.group>
		</flux:menu>
	</flux:dropdown>
@else
	<div class="grid grid-cols-2 gap-2" {{ $attributes }}>
		<flux:button class="w-full" type="button" variant="outline" x-data x-on:click="Livewire.dispatch('open-account-modal', { component: 'auth.login' })">
			{{ __('Log in') }}
		</flux:button>
		@if (\App\Support\AppSettings::bool('auth.registration_enabled', true))
			<flux:button class="w-full" type="button" variant="primary" x-data x-on:click="Livewire.dispatch('open-account-modal', { component: 'auth.register' })">
				{{ __('Register') }}
			</flux:button>
		@endif
	</div>
@endauth