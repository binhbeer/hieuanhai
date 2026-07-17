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
        $query->whereNotNull('name->en')->where('name->en', '!=', '')->whereNotNull('description->en')->where('description->en', '!=', '')->whereHas('media', fn($query) => $query->where('is_published', true)->where('status', 'succeeded')->whereNotNull('result_path')->whereNotNull('title->en')->where('title->en', '!=', '')->whereNotNull('description->en')->where('description->en', '!=', ''));
    }

    $category = $query->firstOrFail();

    return $category instanceof Category ? $category : throw new UnexpectedValueException('Category binding returned an invalid model.');
});

Route::bind('tag', function (string $value): Tag {
    $query = Tag::query()->where(app()->getLocale() === 'en' ? 'slug_en' : 'slug', $value);

    if (app()->getLocale() === 'en') {
        $query->whereNotNull('name->en')->where('name->en', '!=', '')->whereNotNull('description->en')->where('description->en', '!=', '')->whereHas('media', fn($query) => $query->where('is_published', true)->where('status', 'succeeded')->whereNotNull('result_path')->whereNotNull('title->en')->where('title->en', '!=', '')->whereNotNull('description->en')->where('description->en', '!=', ''));
    }

    $tag = $query->firstOrFail();

    return $tag instanceof Tag ? $tag : throw new UnexpectedValueException('Tag binding returned an invalid model.');
});

Route::translate(function (): void {
    Route::livewire('/', 'pages::gallery')->name('home');
    Route::livewire(Localizer::url('skills'), 'pages::skills')->name('skills.index');
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
    Route::livewire('search', 'pages::search')->middleware('auth')->name('search.index');
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
        Route::livewire('skills', 'pages::manage.skills')->name('skills.index');
        Route::livewire('categories', 'pages::manage.categories')->name('categories.index');
        Route::livewire('settings', 'pages::manage.settings')->name('settings.index');
        Route::livewire('languages', 'pages::manage.languages')->name('languages.index');
    });

Route::redirect('api-keys', '/manage/api-keys')->middleware('auth')->name('api-keys.index');
Route::redirect('dashboard', '/')->name('dashboard');

require __DIR__ . '/settings.php';
