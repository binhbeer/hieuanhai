<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $slug_en
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'slug_en', 'description'])]
#[Translatable('name', 'description')]
class Tag extends BaseModel
{
    use HasTranslations;

    protected bool $useFallbackLocale = false;

    /**
     * @return BelongsToMany<GeneratedMedia, $this>
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(GeneratedMedia::class, 'media_tag', 'tag_id', 'media_id')->withTimestamps();
    }

    public function getRouteKey(): mixed
    {
        return app()->getLocale() === 'en' ? $this->slug_en : $this->slug;
    }

    public function getRouteKeyName(): string
    {
        return app()->getLocale() === 'en' ? 'slug_en' : 'slug';
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeEnglishReady(Builder $query): Builder
    {
        return $query
            ->whereNotNull('slug_en')
            ->where('slug_en', '!=', '')
            ->whereNotNull('name->en')
            ->where('name->en', '!=', '')
            ->whereNotNull('description->en')
            ->where('description->en', '!=', '')
            ->whereHas('media', fn (Builder $query) => $query
                ->where('is_published', true)
                ->where('status', 'succeeded')
                ->whereNotNull('result_path')
                ->whereNotNull('title->en')
                ->where('title->en', '!=', '')
                ->whereNotNull('description->en')
                ->where('description->en', '!=', ''));
    }

    public function englishReady(): bool
    {
        return filled($this->slug_en)
            && filled($this->getTranslationWithoutFallback('name', 'en'))
            && filled($this->getTranslationWithoutFallback('description', 'en'))
            && $this->media()->publiclyVisible()->englishReady()->exists();
    }
}
