@auth
	<flux:dropdown position="bottom" align="start" {{ $attributes }}>
		<flux:sidebar.profile data-test="sidebar-menu-button" :name="auth()->user()->name" :initials="auth()->user()->initials()" icon:trailing="chevrons-up-down" />

		<flux:menu>
			<div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
				<flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
				<div class="grid flex-1 text-start text-sm leading-tight">
					<flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
					<flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
				</div>
			</div>
			<flux:menu.separator />
			<flux:menu.radio.group>
				<flux:menu.item icon="chart-bar" :href="route('quota-check.index')" :current="request()->routeIs('quota-check.*')" wire:navigate>
					{{ __('Quota Check') }}
				</flux:menu.item>
				@if (auth()->user()?->isAdmin())
					<flux:menu.item :href="route('manage.index')" icon="wrench-screwdriver" wire:navigate>
						{{ __('Manage') }}
					</flux:menu.item>
				@endif
				<flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
					{{ __('Settings') }}
				</flux:menu.item>
				<form class="w-full" method="POST" action="{{ route('logout') }}">
					@csrf
					<flux:menu.item class="w-full cursor-pointer" data-test="logout-button" as="button" type="submit" icon="arrow-right-start-on-rectangle">
						{{ __('Log out') }}
					</flux:menu.item>
				</form>
			</flux:menu.radio.group>
		</flux:menu>
	</flux:dropdown>
@else
	<flux:button class="items-center" :href="route('login')" variant="outline" wire:navigate {{ $attributes }}>
		{{ __('Log in') }}
	</flux:button>
@endauth