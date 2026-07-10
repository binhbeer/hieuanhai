<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Horizon\Events\LongWaitDetected;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureQueueMonitoring();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(6));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('ai-api', fn (Request $request) => Limit::perSecond(10)->by(
            sha1($request->bearerToken() ?: $request->ip()),
        ));

        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(60)->by($request->ip() ?: 'guest'));
    }

    private function configureQueueMonitoring(): void
    {
        Event::listen(QueueBusy::class, fn (QueueBusy $event) => Log::warning('Queue backlog threshold exceeded.', [
            'connection' => $event->connectionName,
            'queue' => $event->queue,
            'size' => $event->size,
        ]));

        Event::listen(LongWaitDetected::class, fn (LongWaitDetected $event) => Log::warning('Horizon queue wait threshold exceeded.', [
            'connection' => $event->connection,
            'queue' => $event->queue,
            'seconds' => $event->seconds,
        ]));
    }
}
