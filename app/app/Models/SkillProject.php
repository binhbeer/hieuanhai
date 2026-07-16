<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $skill
 * @property string $name
 * @property array<string, mixed>|null $form_data
 * @property array<string, mixed>|null $input_paths
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'skill', 'name', 'form_data', 'input_paths', 'submitted_at'])]
class SkillProject extends BaseModel
{
    public const SKILLS = ['product-detail', 'marketing-poster'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<GeneratedMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(GeneratedMedia::class);
    }

    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'input_paths' => 'array',
            'submitted_at' => 'datetime',
        ];
    }
}
