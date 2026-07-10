<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="min-h-0 overflow-y-auto pt-3! px-2! md:px-6!">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>