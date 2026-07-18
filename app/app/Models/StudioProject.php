<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tool
 * @property string $name
 * @property array<string, mixed>|null $form_data
 * @property array<string, mixed>|null $input_paths
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'tool', 'name', 'form_data', 'input_paths', 'submitted_at'])]
class StudioProject extends BaseModel
{
    protected $table = 'studio_projects';

    public const TOOLS = ['product-detail', 'marketing-poster'];

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
        return $this->hasMany(GeneratedMedia::class, 'studio_project_id');
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
