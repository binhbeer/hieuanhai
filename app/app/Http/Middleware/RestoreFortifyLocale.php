<?php

namespace App\Http\Middleware;

use App\Support\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class RestoreFortifyLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $action = $request->route()?->getActionName() ?? '';

        if (! str_starts_with($action, 'Laravel\\Fortify\\') && ! str_starts_with($action, 'Laravel\\Passkeys\\')) {
            return $next($request);
        }

        $preferred = $request->session()->get('locale', $request->cookie('locale', 'vi'));
        $locale = $preferred === 'en' && AppSettings::bool('locales.en.enabled') ? 'en' : 'vi';

        App::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
