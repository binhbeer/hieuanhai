<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug'])]
class AiTag extends BaseModel
{
    /**
     * @return BelongsToMany<AiImage, $this>
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(AiImage::class, 'ai_image_tag', 'ai_tag_id', 'ai_image_id')->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
