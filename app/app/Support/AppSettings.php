<?php

namespace App\Support;

use App\Models\Setting;

class AppSettings
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $values = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = self::values();

        return array_key_exists($key, $values) ? $values[$key] : config($key, $default);
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

    public static function flush(): void
    {
        self::$values = null;
    }

    public static function maxReferencePhotos(): int
    {
        return min(5, max(1, self::int('ai.image_max_reference_photos', (int) config('ai.image_max_reference_photos', 5))));
    }

    public static function imageUploadMaxKb(): int
    {
        return self::int('ai.image_upload_max_kb', (int) config('ai.image_upload_max_kb', 32768));
    }

    /**
     * @return array<int, mixed>
     */
    public static function promptRules(string $tooManyWordsMessage): array
    {
        return [
            'required',
            'string',
            'max:12000',
            function (string $attribute, mixed $value, \Closure $fail) use ($tooManyWordsMessage): void {
                if (preg_match_all('/[\p{L}\p{N}]+/u', (string) $value) > 1200) {
                    $fail($tooManyWordsMessage);
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function values(): array
    {
        return self::$values ??= Setting::allValues();
    }
}
