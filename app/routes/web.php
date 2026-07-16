<?php

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Support\Facades\Route;

Route::bind('category', fn (string $value): Category => Category::query()
    ->where('slug', $value)
    ->where('status', 'active')
    ->firstOrFail());

Route::bind('tag', fn (string $value): Tag => Tag::query()
    ->where('slug', $value)
    ->firstOrFail());

Route::livewire('/', 'pages::gallery')->name('home');
Route::livewire('skills', 'pages::skills')->name('skills.index');
Route::livewire('search', 'pages::search')->middleware('auth')->name('search.index');
Route::livewire('c/{category:slug}', 'pages::gallery')->name('categories.show');
Route::livewire('t/{tag:slug}', 'pages::gallery')->name('tags.show');
Route::livewire('anh/{image}', 'gallery.detail')->name('images.show');
Route::livewire('favorites', 'pages::favorites')->middleware('auth')->name('favorites.index');
Route::livewire('history', 'pages::images')->middleware('auth')->name('history.index');

Route::middleware(['auth'])->prefix('manage')->name('manage.')->group(function () {
    Route::livewire('/', 'pages::manage.dashboard')->name('index');
    Route::livewire('users', 'pages::manage.users')->name('users.index');
    Route::livewire('users/{user}/edit', 'pages::manage.user-edit')->name('users.edit');
    Route::livewire('api-keys', 'pages::manage.api-keys')->name('api-keys.index');
    Route::livewire('images', 'pages::manage.images')->name('images.index');
    Route::livewire('categories', 'pages::manage.categories')->name('categories.index');
    Route::livewire('settings', 'pages::manage.settings')->name('settings.index');
});

Route::redirect('api-keys', '/manage/api-keys')->middleware('auth')->name('api-keys.index');
Route::redirect('dashboard', '/')->name('dashboard');

require __DIR__.'/settings.php';
