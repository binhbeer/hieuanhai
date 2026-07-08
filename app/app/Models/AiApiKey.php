<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string $token_prefix
 * @property int $quota_limit
 * @property int $quota_used
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'token_hash',
    'token_prefix',
    'quota_limit',
    'quota_used',
    'last_used_at',
])]
class AiApiKey extends Model
{
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array{plain: string, hash: string, prefix: string}
     */
    public static function newToken(): array
    {
        $plain = 'hai_'.Str::random(48);

        return [
            'plain' => $plain,
            'hash' => self::hashToken($plain),
            'prefix' => substr($plain, 0, 12),
        ];
    }

    public function quotaRemaining(): int
    {
        return max(0, $this->quota_limit - $this->quota_used);
    }

    public function hasQuota(): bool
    {
        return $this->quota_used < $this->quota_limit;
    }

    public function statusLabel(): string
    {
        return $this->hasQuota() ? 'Còn quota' : 'Hết quota';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AiApiRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(AiApiRequest::class);
    }
}
