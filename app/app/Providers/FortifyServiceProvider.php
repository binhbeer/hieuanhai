<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Support\AppSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        $modal = fn (string $initial, string $title) => view('account-modal-page', compact('initial', 'title'));

        Fortify::loginView(fn () => $modal('auth.login', __('Log in')));
        Fortify::verifyEmailView(fn () => $modal('auth.verify-email', __('Email verification')));
        Fortify::twoFactorChallengeView(fn () => $modal('auth.two-factor-challenge', __('Two-factor authentication')));
        Fortify::confirmPasswordView(fn () => $modal('auth.confirm-password', __('Confirm password')));
        Fortify::registerView(fn () => AppSettings::bool('auth.registration_enabled', true)
            ? $modal('auth.register', __('Register'))
            : abort(404));
        Fortify::resetPasswordView(fn () => $modal('auth.reset-password', __('Reset password')));
        Fortify::requestPasswordResetLinkView(fn () => $modal('auth.forgot-password', __('Forgot password')));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
