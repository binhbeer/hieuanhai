<?php

namespace App\Console\Commands;

use App\Jobs\CreateAiImage;
use App\Models\GeneratedMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RecoverStaleAiImages extends Command
{
    protected $signature = 'ai-images:recover-stale';

    protected $description = 'Fail image generation tasks that stopped updating';

    public function handle(): int
    {
        $count = 0;

        GeneratedMedia::query()
            ->where('status', 'pending')
            ->where('updated_at', '<', now()->subMinutes(CreateAiImage::STALE_AFTER_MINUTES))
            ->chunkById(100, function ($images) use (&$count): void {
                foreach ($images as $image) {
                    $recovered = (new CreateAiImage($image->id, $image->user_id))->failed(
                        new RuntimeException('Tác vụ tạo ảnh bị gián đoạn. Vui lòng thử lại.'),
                    );

                    if ($recovered) {
                        $count++;
                    }
                }
            });

        if ($count > 0) {
            Log::warning('Recovered stale AI image tasks.', ['count' => $count]);
        }

        $this->info("Recovered {$count} stale AI image task(s).");

        return self::SUCCESS;
    }
}
