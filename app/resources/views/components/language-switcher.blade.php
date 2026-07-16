@if (! \App\Support\LocalizedRoute::is('manage.*') && \Illuminate\Support\Facades\Route::isLocalized())
    @php($englishEnabled = \App\Support\AppSettings::bool('locales.en.enabled'))
    @php($routeCategory = request()->route('category'))
    @php($routeTag = request()->route('tag'))
    @php($routeImage = request()->route('image'))
    @php($routeName = \App\Support\LocalizedRoute::name())
    @php($englishReady = match ($routeName) {
        'categories.show' => $routeCategory instanceof \App\Models\Category && $routeCategory->englishReady(),
        'tags.show' => $routeTag instanceof \App\Models\Tag && $routeTag->englishReady(),
        'images.show' => $routeImage instanceof \App\Models\GeneratedMedia && $routeImage->englishReady(),
        default => true,
    })
    @php($localizedUrl = fn (string $locale): string => \App\Support\LocalizedRoute::currentUrl($locale))

    @if (app()->getLocale() === 'en')
        <flux:menu.item :href="$localizedUrl('vi')">
            <x-slot name="icon"><x-iconsax-two-global class="size-5 mr-1.5" /></x-slot>
            Tiếng Việt
        </flux:menu.item>
    @elseif ($englishEnabled && $englishReady)
        <flux:menu.item :href="$localizedUrl('en')">
            <x-slot name="icon"><x-iconsax-two-global class="size-5 mr-1.5" /></x-slot>
            English
        </flux:menu.item>
    @endif
@endif
