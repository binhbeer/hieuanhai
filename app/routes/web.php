<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::redirect('dashboard', '/')->name('dashboard');

require __DIR__.'/settings.php';
