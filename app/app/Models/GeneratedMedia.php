<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $parent_id
 * @property int|null $category_id
 * @property int|null $studio_project_id
 * @property string|null $title
 * @property string|null $description
 * @property string $visitor_key
 * @property string|null $ip_address
 * @property string|null $preset
 * @property string $prompt
 * @property string|null $custom_prompt
 * @property string|null $source
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
    'studio_project_id',
    'title',
    'description',
    'visitor_key',
    'ip_address',
    'preset',
    'prompt',
    'custom_prompt',
    'source',
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
#[Translatable('title', 'description')]
class GeneratedMedia extends BaseModel implements HasMedia
{
    use HasTranslations;
    use InteractsWithMedia;

    protected bool $useFallbackLocale = false;

    protected $table = 'generated_media';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('result')->singleFile();
        $this->addMediaCollection('sources');
    }

    public function getRouteKey(): string
    {
        return $this->routeKeySlug();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $key = is_string($value) ? Str::before($value, '-') : $value;
        $image = parent::resolveRouteBinding($key, $field);

        return app()->getLocale() === 'en' && $image instanceof self && ! $image->englishReady()
            ? null
            : $image;
    }

    public function routeKeySlug(): string
    {
        $locale = app()->getLocale();
        $title = $this->getTranslationWithoutFallback('title', $locale);
        $language = $locale === 'en' ? 'en' : 'vi';
        $slug = Str::limit(Str::slug($title ?: $this->prompt, '-', $language), 100, '') ?: 'image';

        return $this->id.'-'.$slug;
    }

    public function englishReady(): bool
    {
        return filled($this->getTranslationWithoutFallback('title', 'en'))
            && filled($this->getTranslationWithoutFallback('description', 'en'));
    }

    public function downloadName(): string
    {
        $extension = pathinfo($this->result_path ?? '', PATHINFO_EXTENSION) ?: 'png';

        $timestamp = $this->created_at instanceof Carbon ? $this->created_at->timestamp : time();

        return 'GenAnh.com-'.$timestamp.'.'.$extension;
    }

    /**
     * User-facing failure text. Admins keep the stored technical error.
     */
    public function displayError(?User $viewer = null): string
    {
        $raw = trim((string) ($this->error ?? ''));
        $viewer ??= auth()->user();

        if ($viewer instanceof User && $viewer->isAdmin() && $raw !== '') {
            return $raw;
        }

        return __('Could not create this image.');
    }

    /**
     * @param  Builder<GeneratedMedia>  $query
     * @return Builder<GeneratedMedia>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where('status', 'succeeded')
            ->whereNotNull('result_path');
    }

    /**
     * @param  Builder<GeneratedMedia>  $query
     * @return Builder<GeneratedMedia>
     */
    public function scopeEnglishReady(Builder $query): Builder
    {
        return $query
            ->whereNotNull('title->en')
            ->where('title->en', '!=', '')
            ->whereNotNull('description->en')
            ->where('description->en', '!=', '');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<StudioProject, $this>
     */
    public function studioProject(): BelongsTo
    {
        return $this->belongsTo(StudioProject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<GeneratedMedia, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MediaFavorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(MediaFavorite::class, 'media_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'media_tag', 'media_id', 'tag_id')->withTimestamps();
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
