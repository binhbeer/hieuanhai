<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="{{ request()->routeIs('images.show') ? 'h-dvh overflow-hidden p-0!' : 'pt-3! px-2! md:px-6! lg:min-h-0 lg:overflow-y-auto' }}">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>