<?php

namespace App\Models;

use App\Support\AppSettings;
use GeneaLabs\LaravelModelCaching\CachedBuilder;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Facades\Crypt;

/**
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends BaseModel
{
    public const DEFAULTS = [
        'site.name' => 'GenAnh',
        'site.home_title' => null,
        'site.description' => 'Chỉnh ảnh AI chất lượng cao miễn phí không cần đăng ký.',
        'site.keywords' => 'chỉnh ảnh AI, tạo ảnh AI',
        'site.home_title.en' => null,
        'site.description.en' => null,
        'site.keywords.en' => null,
        'locales.en.enabled' => false,
        'analytics.google_measurement_id' => null,
        'contact.zalo_url' => 'http://zalo.me/0963559309',
        'auth.registration_enabled' => true,
        'auth.email_verification_required' => true,
        'auth.auto_verify_email' => false,
        'auth.member_request_limit' => 100,
        'auth.verified_daily_image_limit' => 5,
        'ai.image_provider' => 'openai',
        'ai.image_models' => ['cx/gpt-5.5-image'],
        'ai.text_models' => ['gpt-5.5'],
        'ai.image_model' => 'cx/gpt-5.5-image',
        'ai.text_model' => 'gpt-5.5',
        'ai.image_review_model' => '',
        'ai.tag_model' => '',
        'ai.prompt_translation_enabled' => true,
        'ai.prompt_rewrite_enabled' => true,
        'ai.image_to_prompt_enabled' => true,
        'ai.prompt_translation_model' => '',
        'ai.prompt_rewrite_model' => '',
        'ai.image_to_prompt_model' => '',
        'ai.image_timeout' => 600,
        'ai.image_size' => 'auto',
        'ai.image_quality' => 'auto',
        'ai.image_detail' => 'high',
        'ai.image_reference_field' => 'image',
        'ai.image_max_reference_photos' => 5,
        'ai.image_upload_max_kb' => 32768,
        'ai.openai_url' => 'http://42.112.31.227:22150/v1',
        'ai.openai_api_key' => null,
    ];

    private const SECRET_KEYS = ['ai.openai_api_key'];

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    /**
     * @return array<string, mixed>
     */
    public static function allValues(): array
    {
        $values = static::DEFAULTS;

        /** @var CachedBuilder $query */
        $query = static::query();

        foreach ($query->disableModelCaching()->get(['key', 'value']) as $setting) {
            if ($setting->value === null) {
                continue;
            }

            $values[$setting->key] = self::decodeValue($setting->key, $setting->value);
        }

        return $values;
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        /** @var CachedBuilder $query */
        $query = static::query();
        $setting = $query->disableModelCaching()->whereKey($key)->first(['key', 'value']);

        if ($setting?->value !== null) {
            return self::decodeValue($key, $setting->value);
        }

        return $default !== null ? $default : static::DEFAULTS[$key] ?? null;
    }

    public static function putValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => self::encodeValue($key, $value)],
        );

        AppSettings::flush();
    }

    private static function encodeValue(string $key, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($key, self::SECRET_KEYS, true)) {
            return Crypt::encryptString((string) $value);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private static function decodeValue(string $key, ?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array($key, self::SECRET_KEYS, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                return null;
            }
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }
}
