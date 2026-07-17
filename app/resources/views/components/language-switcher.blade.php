@if (!\App\Support\LocalizedRoute::is('manage.*') && \Illuminate\Support\Facades\Route::isLocalized())
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
@php($localizedUrl = fn(string $locale): string => \App\Support\LocalizedRoute::currentUrl($locale))

@if (app()->getLocale() === 'en')
    <flux:button size="sm" variant="primary" :href="$localizedUrl('vi')" aria-label="Tiếng Việt" tooltip="Tiếng Việt" tooltip:position="bottom">
        VI
    </flux:button>
@elseif ($englishEnabled && $englishReady)
    <flux:button size="sm" variant="primary" :href="$localizedUrl('en')" aria-label="English" tooltip="English" tooltip:position="bottom">
        EN
    </flux:button>
@endif
@endif