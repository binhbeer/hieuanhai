<?php

namespace App\Support;

use App\Models\Setting;

class AppSettings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return Setting::getValue($key, config($key, $default));
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOL);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function string(string $key, ?string $default = null): string
    {
        return (string) self::get($key, $default);
    }
}
