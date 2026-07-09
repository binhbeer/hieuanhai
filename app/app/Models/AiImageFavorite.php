<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $ai_image_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'ai_image_id'])]
class AiImageFavorite extends BaseModel
{
    protected static function booted(): void
    {
        static::created(fn (self $favorite) => AiImage::query()
            ->whereKey($favorite->ai_image_id)
            ->increment('favorites_count'));

        static::deleted(fn (self $favorite) => AiImage::query()
            ->whereKey($favorite->ai_image_id)
            ->where('favorites_count', '>', 0)
            ->decrement('favorites_count'));
    }

    /**
     * @return BelongsTo<AiImage, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(AiImage::class, 'ai_image_id');
    }
}
