<?php

namespace App\Http\Middleware;

use App\Support\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocaleIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (App::getLocale() !== 'en' || AppSettings::bool('locales.en.enabled')) {
            return $next($request);
        }

        Session::put('locale', 'vi');
        Cookie::queue('locale', 'vi', 60 * 24 * 30);
        App::setLocale('vi');
        URL::defaults(['locale' => 'vi']);

        if ($request->route()?->getAction('locale_type') === 'without_locale') {
            return $next($request);
        }

        abort(404);
    }
}
