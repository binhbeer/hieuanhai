<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $visitor_key
 * @property string|null $ip_address
 * @property string|null $preset
 * @property string $prompt
 * @property string|null $custom_prompt
 * @property string|null $source_path
 * @property string|null $result_path
 * @property string $provider
 * @property string $model
 * @property string $status
 * @property string|null $error
 * @property array<string, mixed>|null $request_meta
 * @property array<string, mixed>|null $response_meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'visitor_key',
    'ip_address',
    'preset',
    'prompt',
    'custom_prompt',
    'source_path',
    'result_path',
    'provider',
    'model',
    'status',
    'error',
    'request_meta',
    'response_meta',
])]
class AiImage extends Model
{
    public function downloadName(): string
    {
        $extension = pathinfo($this->result_path ?? '', PATHINFO_EXTENSION) ?: 'png';

        return 'HieuAnhAI.COM-'.($this->created_at?->timestamp ?? time()).'.'.$extension;
    }

    protected function casts(): array
    {
        return [
            'request_meta' => 'array',
            'response_meta' => 'array',
        ];
    }
}
