<?php

namespace App\Jobs;

use App\Events\AiImageCompleted;
use App\Models\AiImage;
use App\Services\AiImageEditor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class CreateAiImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 360;

    public int $tries = 1;

    public int $uniqueFor = 360;

    public bool $failOnTimeout = true;

    public function __construct(public int $imageId, public ?int $userId = null) {}

    public function uniqueId(): string
    {
        $userId = $this->userId ?? AiImage::query()->whereKey($this->imageId)->value('user_id');

        return $userId ? 'user:'.$userId : 'image:'.$this->imageId;
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

    public function failed(?Throwable $exception): void
    {
        $image = AiImage::query()
            ->whereKey($this->imageId)
            ->where('status', 'pending')
            ->first();

        if (! $image) {
            return;
        }

        $requestMeta = is_array($image->request_meta) ? $image->request_meta : [];
        $requestMeta['progress'] = 'failed';

        $image->update([
            'status' => 'failed',
            'error' => Str::limit($exception?->getMessage() ?: 'Không tạo được ảnh.', 2000, ''),
            'request_meta' => $requestMeta,
        ]);

        AiImageCompleted::dispatch($image->refresh());
    }
}
