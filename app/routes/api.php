<?php

use App\Http\Controllers\Api\AiImageController;
use Illuminate\Support\Facades\Route;

Route::domain('api.'.parse_url((string) config('app.url'), PHP_URL_HOST))->group(function (): void {
    Route::middleware(['throttle:ai-api', 'ai.api.key'])->group(function (): void {
        Route::post('ai/images', [AiImageController::class, 'store']);
        Route::post('ai/images/publish', [AiImageController::class, 'storeAndPublish']);
    });

    Route::middleware('throttle:public-api')->group(function (): void {
        Route::get('categories', [AiImageController::class, 'categories']);
        Route::get('images/search', [AiImageController::class, 'search']);
    });
});
