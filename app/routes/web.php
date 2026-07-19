<?php

use App\Http\Controllers\ImageDownloadController;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Support\Facades\Route;
use NielsNumbers\LaravelLocalizer\Facades\Localizer;

Route::bind('category', function (string $value): Category {
    $query = Category::query()
        ->where(app()->getLocale() === 'en' ? 'slug_en' : 'slug', $value)
        ->where('status', 'active');

    if (app()->getLocale() === 'en') {
        $query->whereNotNull('name->en')->where('name->en', '!=', '')->whereNotNull('description->en')->where('description->en', '!=', '')->whereHas('media', fn ($query) => $query->where('is_published', true)->where('status', 'succeeded')->whereNotNull('result_path')->whereNotNull('title->en')->where('title->en', '!=', '')->whereNotNull('description->en')->where('description->en', '!=', ''));
    }

    return $query->firstOrFail();
});

Route::bind('tag', function (string $value): Tag {
    $query = Tag::query()->where(app()->getLocale() === 'en' ? 'slug_en' : 'slug', $value);

    if (app()->getLocale() === 'en') {
        $query->whereNotNull('name->en')->where('name->en', '!=', '')->whereNotNull('description->en')->where('description->en', '!=', '')->whereHas('media', fn ($query) => $query->where('is_published', true)->where('status', 'succeeded')->whereNotNull('result_path')->whereNotNull('title->en')->where('title->en', '!=', '')->whereNotNull('description->en')->where('description->en', '!=', ''));
    }

    return $query->firstOrFail();
});

Route::translate(function (): void {
    Route::livewire('/', 'pages::home')->name('home');
    Route::livewire(Localizer::url('gallery'), 'pages::gallery')->name('gallery.index');
    Route::livewire(Localizer::url('quick'), 'pages::quick')->name('quick.index');
    Route::livewire(Localizer::url('quick/remove-object'), 'pages::quick')->name('quick.remove-object')->defaults('tool', 'remove-object');
    Route::livewire(Localizer::url('quick/restore-old-photo'), 'pages::quick')->name('quick.restore-old-photo')->defaults('tool', 'restore-old-photo');
    Route::livewire(Localizer::url('quick/replace-background'), 'pages::quick')->name('quick.replace-background')->defaults('tool', 'replace-background');
    Route::livewire(Localizer::url('quick/product-photo'), 'pages::quick')->name('quick.product-photo')->defaults('tool', 'product-photo');
    Route::livewire(Localizer::url('quick/expand-image'), 'pages::quick')->name('quick.expand-image')->defaults('tool', 'expand-image');
    Route::livewire(Localizer::url('quick/enhance-image'), 'pages::quick')->name('quick.enhance-image')->defaults('tool', 'enhance-image');
    Route::livewire(Localizer::url('quick/advertising-image'), 'pages::quick')->name('quick.advertising-image')->defaults('tool', 'advertising-image');
    Route::livewire(Localizer::url('quick/cinematic-portrait'), 'pages::quick')->name('quick.cinematic-portrait')->defaults('tool', 'cinematic-portrait');
    Route::livewire(Localizer::url('quick/premium-studio'), 'pages::quick')->name('quick.premium-studio')->defaults('tool', 'premium-studio');
    Route::livewire(Localizer::url('quick/ghibli-style'), 'pages::quick')->name('quick.ghibli-style')->defaults('tool', 'ghibli-style');
    Route::livewire(Localizer::url('quick/face-swap'), 'pages::quick')->name('quick.face-swap')->defaults('tool', 'face-swap');
    Route::livewire(Localizer::url('quick/change-outfit'), 'pages::quick')->name('quick.change-outfit')->defaults('tool', 'change-outfit');
    Route::livewire(Localizer::url('quick/add-person'), 'pages::quick')->name('quick.add-person')->defaults('tool', 'add-person');
    Route::livewire(Localizer::url('quick/id-photo'), 'pages::quick')->name('quick.id-photo')->defaults('tool', 'id-photo');
    Route::livewire(Localizer::url('creator'), 'pages::creator')->name('creator.index');
    Route::livewire(Localizer::url('studio'), 'pages::studio')->name('studio.index');
    Route::livewire(Localizer::url('studio/mau/{sample}'), 'pages::studio-sample')->name('studio.sample');
    Route::livewire(Localizer::url('huong-dan'), 'pages::guide')->name('guide.index');
    Route::livewire(Localizer::url('huong-dan/bat-dau'), 'pages::guide')->name('guide.getting-started');
    Route::livewire(Localizer::url('huong-dan/ung-dung-web'), 'pages::guide')->name('guide.web');
    Route::livewire(Localizer::url('huong-dan/api'), 'pages::guide')->name('guide.api');
    Route::livewire(Localizer::url('huong-dan/faq'), 'pages::guide')->name('guide.faq');
    Route::livewire(Localizer::url('c/{category}'), 'pages::gallery')->name('categories.show');
    Route::livewire(Localizer::url('t/{tag}'), 'pages::gallery')->name('tags.show');
    Route::get(Localizer::url('anh/{image}/download'), ImageDownloadController::class)->name('images.download');
    Route::livewire(Localizer::url('anh/{image}'), 'gallery.detail')->name('images.show');
});

Route::localize(function (): void {
    Route::get('search', fn () => redirect()->route('gallery.index', request()->query()))->name('search.index');
    Route::livewire('favorites', 'pages::favorites')->middleware('auth')->name('favorites.index');
    Route::livewire('history', 'pages::history')->middleware('auth')->name('history.index');
});

Route::middleware(['auth'])
    ->prefix('manage')
    ->name('manage.')
    ->group(function (): void {
        Route::livewire('/', 'pages::manage.dashboard')->name('index');
        Route::livewire('users', 'pages::manage.users')->name('users.index');
        Route::livewire('users/{user}/edit', 'pages::manage.user-edit')->name('users.edit');
        Route::livewire('api-keys', 'pages::manage.api-keys')->name('api-keys.index');
        Route::livewire('images', 'pages::manage.images')->name('images.index');
        Route::livewire('studio', 'pages::manage.studio')->name('studio.index');
        Route::livewire('categories', 'pages::manage.categories')->name('categories.index');
        Route::livewire('settings', 'pages::manage.settings')->name('settings.index');
        Route::livewire('languages', 'pages::manage.languages')->name('languages.index');
    });

Route::redirect('api-keys', '/manage/api-keys')->middleware('auth')->name('api-keys.index');
Route::redirect('dashboard', '/')->name('dashboard');

require __DIR__.'/settings.php';
