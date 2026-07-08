<?php

use App\Http\Controllers\Api\AiImageController;
use Illuminate\Support\Facades\Route;

Route::middleware('ai.api.key')->post('ai/images', [AiImageController::class, 'store']);
