<?php

use App\Models\Category;
use Illuminate\Support\Facades\Route;

Route::bind('category', fn (string $value): Category => Category::query()
    ->where('slug', $value)
    ->where('status', 'active')
    ->firstOrFail());

Route::livewire('/', 'pages::gallery')->name('home');
Route::livewire('c/{category:slug}', 'pages::gallery')->name('categories.show');
Route::livewire('anh/{image}', 'pages::gallery')->name('images.show');
Route::livewire('quota-check', 'pages::quota-check')->name('quota-check.index');
Route::livewire('favorites', 'pages::favorites')->middleware('auth')->name('favorites.index');
Route::livewire('images', 'pages::images')->middleware('auth')->name('images.index');
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
