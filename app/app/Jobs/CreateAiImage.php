<?php

namespace App\Jobs;

use App\Events\AiImageCompleted;
use App\Models\AiImage;
use App\Services\AiImageEditor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CreateAiImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public const STALE_AFTER_MINUTES = 25;

    public int $timeout = 1260;

    public int $tries = 1;

    public int $uniqueFor = 1260;

    public bool $failOnTimeout = true;

    public function __construct(public int $imageId, public ?int $userId = null) {}

    public function uniqueId(): string
    {
        return 'image:'.$this->imageId;
    }

    public function handle(AiImageEditor $editor): void
    {
        $image = AiImage::find($this->imageId);

        if (! $image || $image->status !== 'pending') {
            return;
        }

        $image = $editor->completePending($image);

        AiImageCompleted::dispatch($image);
    }

    public function failed(?Throwable $exception): bool
    {
        $image = AiImage::query()
            ->whereKey($this->imageId)
            ->where('status', 'pending')
            ->first();

        if (! $image) {
            return false;
        }

        $requestMeta = is_array($image->request_meta) ? $image->request_meta : [];
        $pendingUploads = $requestMeta['pending_uploads'] ?? [];
        unset($requestMeta['parent_prompt'], $requestMeta['pending_uploads']);
        $requestMeta['progress'] = 'failed';

        $updated = AiImage::query()
            ->whereKey($image->id)
            ->where('status', 'pending')
            ->where('updated_at', $image->updated_at)
            ->update([
                'status' => 'failed',
                'error' => Str::limit($exception?->getMessage() ?: 'Không tạo được ảnh.', 2000, ''),
                'request_meta' => $requestMeta,
            ]);

        if ($updated === 0) {
            return false;
        }

        try {
            if (is_array($pendingUploads)) {
                Storage::disk('public')->delete(array_values(array_filter(array_map(
                    fn ($upload) => is_array($upload) && is_string($upload['path'] ?? null) ? $upload['path'] : null,
                    $pendingUploads,
                ))));
            }
        } catch (Throwable $e) {
            report($e);
        }

        AiImageCompleted::dispatch($image->refresh());

        return true;
    }
}
