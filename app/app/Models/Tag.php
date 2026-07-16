<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description'])]
class Tag extends BaseModel
{
    /**
     * @return BelongsToMany<GeneratedMedia, $this>
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(GeneratedMedia::class, 'media_tag', 'tag_id', 'media_id')->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
