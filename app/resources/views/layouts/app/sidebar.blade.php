<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

	<head>
		@include('partials.head')
	</head>

	<body class="min-h-screen bg-white dark:bg-zinc-800">
		<flux:sidebar class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" sticky
			collapsible="mobile">
			<flux:sidebar.header>
				<x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
				<flux:sidebar.collapse class="lg:hidden" />
			</flux:sidebar.header>

			<livewire:pages::image-usage />

			<flux:sidebar.nav>
				<flux:sidebar.group class="grid">
					<flux:sidebar.item icon="home" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
						{{ __('Tạo ảnh') }}
					</flux:sidebar.item>
				</flux:sidebar.group>
			</flux:sidebar.nav>

			<flux:spacer />

			<x-desktop-user-menu class="hidden lg:block" />
		</flux:sidebar>

		<flux:header class="lg:hidden">
			<flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

			<flux:spacer />

			@auth
				<flux:dropdown position="top" align="end">
					<flux:profile
						:initials="auth()->user()->initials()" icon-trailing="chevron-down" />

					<flux:menu>
						<flux:menu.radio.group>
							<div class="p-0 text-sm font-normal">
								<div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
									<flux:avatar
										:name="auth()->user()->name" :initials="auth()->user()->initials()" />

									<div class="grid flex-1 text-start text-sm leading-tight">
										<flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
										<flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
									</div>
								</div>
							</div>
						</flux:menu.radio.group>

						<flux:menu.separator />

						<flux:menu.radio.group>
							<flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
								{{ __('Settings') }}
							</flux:menu.item>
						</flux:menu.radio.group>

						<flux:menu.separator />

						<form class="w-full" method="POST" action="{{ route('logout') }}">
							@csrf
							<flux:menu.item
								class="w-full cursor-pointer" data-test="logout-button" as="button" type="submit"
								icon="arrow-right-start-on-rectangle">
								{{ __('Log out') }}
							</flux:menu.item>
						</form>
					</flux:menu>
				</flux:dropdown>
			@else
				<a
					class="rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-300"
					href="{{ route('login') }}" wire:navigate>
					{{ __('Đăng nhập') }}
				</a>
			@endauth
		</flux:header>

		{{ $slot }}

		@persist('toast')
			<flux:toast.group>
				<flux:toast />
			</flux:toast.group>
		@endpersist

		@fluxScripts
	</body>

</html>
