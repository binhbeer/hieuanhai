<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $slug_en
 * @property string|null $description
 * @property int $sort_order
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'slug_en', 'description', 'sort_order', 'status'])]
#[Translatable('name', 'description')]
class Category extends BaseModel
{
    use HasTranslations;

    protected bool $useFallbackLocale = false;

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<GeneratedMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(GeneratedMedia::class);
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
     * @param  Builder<Category>  $query
     * @return Builder<Category>
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
