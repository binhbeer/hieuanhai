<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $api_key_id
 * @property int|null $user_id
 * @property int|null $media_id
 * @property string|null $ip_address
 * @property int $status_code
 * @property string $status
 * @property int $duration_ms
 * @property bool $quota_charged
 * @property string|null $error
 * @property array<string, mixed>|null $request_meta
 * @property array<string, mixed>|null $response_meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'api_key_id',
    'user_id',
    'media_id',
    'ip_address',
    'status_code',
    'status',
    'duration_ms',
    'quota_charged',
    'error',
    'request_meta',
    'response_meta',
])]
class ApiRequest extends BaseModel
{
    /**
     * @return BelongsTo<ApiKey, $this>
     */
    public function key(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    /**
     * @return BelongsTo<GeneratedMedia, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(GeneratedMedia::class, 'media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'quota_charged' => 'bool',
            'request_meta' => 'array',
            'response_meta' => 'array',
        ];
    }
}
