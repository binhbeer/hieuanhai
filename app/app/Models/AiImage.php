<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $parent_id
 * @property int|null $category_id
 * @property string|null $title
 * @property string $visitor_key
 * @property string|null $ip_address
 * @property string|null $preset
 * @property string $prompt
 * @property string|null $custom_prompt
 * @property string|null $source
 * @property string|null $source_path
 * @property string|null $result_path
 * @property string $provider
 * @property string $model
 * @property string $status
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property int $favorites_count
 * @property bool $is_featured
 * @property string|null $error
 * @property array<string, mixed>|null $request_meta
 * @property array<string, mixed>|null $response_meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'parent_id',
    'category_id',
    'title',
    'visitor_key',
    'ip_address',
    'preset',
    'prompt',
    'custom_prompt',
    'source',
    'source_path',
    'result_path',
    'provider',
    'model',
    'status',
    'is_published',
    'published_at',
    'is_featured',
    'error',
    'request_meta',
    'response_meta',
])]
class AiImage extends BaseModel
{
    public function getRouteKey(): string
    {
        return $this->routeKeySlug();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $key = is_string($value) ? Str::before($value, '-') : $value;

        return parent::resolveRouteBinding($key, $field);
    }

    public function routeKeySlug(): string
    {
        $slug = Str::slug($this->title ?: $this->prompt, '-', 'vi') ?: 'image';

        return $this->id.'-'.$slug;
    }

    public function downloadName(): string
    {
        $extension = pathinfo($this->result_path ?? '', PATHINFO_EXTENSION) ?: 'png';

        $timestamp = $this->created_at instanceof Carbon ? $this->created_at->timestamp : time();

        return 'HieuAnhAI.COM-'.$timestamp.'.'.$extension;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AiImage, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<AiImageFavorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(AiImageFavorite::class);
    }

    /**
     * @return BelongsToMany<AiTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(AiTag::class, 'ai_image_tag', 'ai_image_id', 'ai_tag_id')->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'bool',
            'published_at' => 'datetime',
            'favorites_count' => 'int',
            'is_featured' => 'bool',
            'request_meta' => 'array',
            'response_meta' => 'array',
        ];
    }
}
