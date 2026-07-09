<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'sort_order', 'status'])]
class Category extends BaseModel
{
    /**
     * @return HasMany<AiImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(AiImage::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
