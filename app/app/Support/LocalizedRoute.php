<?php

namespace App\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use NielsNumbers\LaravelLocalizer\Facades\Localizer;

class LocalizedRoute
{
    public static function name(): ?string
    {
        return Localizer::baseName(Route::current()?->getName());
    }

    public static function is(string ...$patterns): bool
    {
        $name = self::name();

        return $name !== null && Str::is($patterns, $name);
    }

    public static function url(string $name, mixed $parameters = [], string $locale = 'vi', bool $absolute = true): string
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return route($name, $parameters, $absolute);
        } finally {
            App::setLocale($previous);
        }
    }
}
