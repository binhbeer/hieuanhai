<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

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

    /** @return list<string> */
    public static function imageModels(): array
    {
        return self::normalizedModels(self::get('ai.image_models', []));
    }

    /** @return array<string, string> */
    public static function imageModelAliases(): array
    {
        $aliases = self::get('ai.image_model_aliases', []);

        if (! is_array($aliases)) {
            return [];
        }

        return collect($aliases)
            ->filter(fn (mixed $alias, mixed $model): bool => is_string($model) && is_string($alias) && trim($alias) !== '')
            ->mapWithKeys(fn (string $alias, string $model): array => [$model => trim($alias)])
            ->all();
    }

    public static function imageModelLabel(string $model): string
    {
        return self::imageModelAliases()[$model]
            ?? Str::of($model)->afterLast('/')->replace(['-', '_'], ' ')->title()->toString();
    }

    /** @return list<string> */
    public static function enabledImageModels(): array
    {
        $disabled = self::normalizedModels(self::get('ai.image_disabled_models', []));

        return array_values(array_diff(self::imageModels(), $disabled));
    }

    public static function defaultImageModel(): string
    {
        $models = self::enabledImageModels();
        $default = self::string('ai.image_model', (string) config('ai.image_model', 'cx/gpt-5.5-image'));

        return in_array($default, $models, true) ? $default : ($models[0] ?? '');
    }

    public static function resolveImageModel(?string $model = null): string
    {
        $model = trim((string) $model);
        $model = $model !== '' ? $model : self::defaultImageModel();

        if ($model === '' || ! in_array($model, self::enabledImageModels(), true)) {
            throw new \InvalidArgumentException(__('Select an enabled image model.'));
        }

        return $model;
    }

    /**
     * @return array<int, mixed>
     */
    public static function promptRules(): array
    {
        return ['required', 'string', 'max:2000'];
    }

    /** @return list<string> */
    private static function normalizedModels(mixed $models): array
    {
        if (! is_array($models)) {
            return [];
        }

        $models = array_filter($models, fn (mixed $model): bool => is_string($model) && trim($model) !== '');
        $models = array_values(array_unique(array_map(fn (string $model): string => trim($model), $models)));
        sort($models, SORT_NATURAL);

        return $models;
    }

    /**
     * @return array<string, mixed>
     */
    private static function values(): array
    {
        return self::$values ??= Setting::allValues();
    }
}
