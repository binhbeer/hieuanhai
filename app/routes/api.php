<?php

use App\Http\Controllers\Api\AiImageController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

RateLimiter::for('ai-api', fn (Request $request) => Limit::perSecond(10)->by(
    sha1($request->bearerToken() ?: $request->ip()),
));

Route::middleware(['throttle:ai-api', 'ai.api.key'])->post('ai/images', [AiImageController::class, 'store']);
