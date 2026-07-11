<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::view('settings/profile', 'account-modal-page', ['initial' => 'settings.profile', 'title' => 'Profile settings'])->name('profile.edit');
    Route::view('settings/api-key', 'account-modal-page', ['initial' => 'settings.api-key', 'title' => 'API key settings'])->name('api-key.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('settings/appearance', 'account-modal-page', ['initial' => 'settings.appearance', 'title' => 'Appearance settings'])->name('appearance.edit');

    Route::view('settings/security', 'account-modal-page', ['initial' => 'settings.security', 'title' => 'Security settings'])
        ->middleware('password.confirm')
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
