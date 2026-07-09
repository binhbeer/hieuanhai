<?php

use App\Http\Controllers\Api\AiImageController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

RateLimiter::for('ai-api', fn (Request $request) => Limit::perSecond(10)->by(
    sha1($request->bearerToken() ?: $request->ip()),
));

RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(60)->by($request->ip() ?: 'guest'));

Route::middleware(['throttle:ai-api', 'ai.api.key'])->group(function (): void {
    Route::post('ai/images', [AiImageController::class, 'store']);
    Route::post('ai/images/publish', [AiImageController::class, 'storeAndPublish']);
});

Route::middleware('throttle:public-api')->get('categories', [AiImageController::class, 'categories']);
