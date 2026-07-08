<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('quota-check', 'quota-check')->name('quota-check.index');
Route::view('api-keys', 'api-keys')->middleware('auth')->name('api-keys.index');
Route::redirect('dashboard', '/')->name('dashboard');

require __DIR__.'/settings.php';
