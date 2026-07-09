<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
	@include('partials.head')
</head>

<body class="dark:bg-linear-to-b min-h-screen bg-white antialiased dark:from-neutral-950 dark:to-neutral-900">
	<div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
		<div class="flex w-full max-w-sm flex-col gap-2">
			<a class="flex flex-col items-center gap-2 font-medium" href="{{ route('home') }}" wire:navigate>
				<span class="mb-1 flex h-20 w-20 items-center justify-center rounded-md">
					<x-app-logo-icon class="size-20 fill-current text-black dark:text-white" />
				</span>
				<span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
			</a>
			<div class="flex flex-col gap-6">
				{{ $slot }}
			</div>
			<a class="self-center text-sm font-medium text-zinc-600 underline-offset-4 hover:text-zinc-900 hover:underline dark:text-zinc-400 dark:hover:text-white" href="{{ route('home') }}" wire:navigate>
				{{ __('Back to home') }}
			</a>
		</div>
	</div>

	@persist('toast')
	<flux:toast.group>
		<flux:toast />
	</flux:toast.group>
	@endpersist

	@fluxScripts
</body>

</html>