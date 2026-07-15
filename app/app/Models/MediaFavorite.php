<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $media_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'media_id'])]
class MediaFavorite extends BaseModel
{
    protected static function booted(): void
    {
        static::created(fn (self $favorite) => GeneratedMedia::query()
            ->whereKey($favorite->media_id)
            ->increment('favorites_count'));

        static::deleted(fn (self $favorite) => GeneratedMedia::query()
            ->whereKey($favorite->media_id)
            ->where('favorites_count', '>', 0)
            ->decrement('favorites_count'));
    }

    /**
     * @return BelongsTo<GeneratedMedia, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(GeneratedMedia::class, 'media_id');
    }
}
