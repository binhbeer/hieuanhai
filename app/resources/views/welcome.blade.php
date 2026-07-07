@php($title = 'Chỉnh ảnh AI')

<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

	<head>
		@include('partials.head')
	</head>

	<body class="min-h-screen text-white antialiased dark:bg-zinc-800">

		<header class="z-10 flex min-h-14 items-center px-6 [grid-area:header] lg:px-8">
			<flux:avatar size="xl" src="/logo.png" />
			<div>
				<flux:heading size="xl">
					<flux:badge size="sm">ChinhAnh.net</flux:badge>
				</flux:heading>
				<flux:text class="mt-1 text-sm" variant="subtle">Chỉnh ảnh AI chất lượng cao miễn phí không cần đăng ký.</flux:text>
			</div>
		</header>
		<div class="p-6 [grid-area:main] lg:p-8 [[data-flux-container]_&]:px-0">
			<livewire:pages::image-generator />
		</div>

		@persist('toast')
			<flux:toast.group>
				<flux:toast />
			</flux:toast.group>
		@endpersist

		@fluxScripts
	</body>

</html>
