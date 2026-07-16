<?php

use App\Http\Middleware\EnsureLocaleIsEnabled;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\RestoreFortifyLocale;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\VerifyAiApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use NielsNumbers\LaravelLocalizer\Middleware\RedirectLocale;
use NielsNumbers\LaravelLocalizer\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(remove: [SubstituteBindings::class]);
        $middleware->web(append: [
            SetLocale::class,
            EnsureLocaleIsEnabled::class,
            RedirectLocale::class,
            SubstituteBindings::class,
            RestoreFortifyLocale::class,
            EnsureUserIsNotBanned::class,
        ]);
        $middleware->api(prepend: [SetApiLocale::class]);
        $middleware->alias([
            'ai.api.key' => VerifyAiApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
