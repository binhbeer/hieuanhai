<?php

namespace App\Console\Commands;

use App\Models\AiImage;
use App\Services\AiImageEditor;
use Illuminate\Console\Command;
use Throwable;

class BackfillAiImageMetadata extends Command
{
    protected $signature = 'ai-images:backfill-metadata {--limit=0 : Max images to process (0 = all)}';

    protected $description = 'Generate missing title, description, category, and tags for published images';

    public function handle(AiImageEditor $editor): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 0) {
            $this->error('--limit must be zero or greater.');

            return self::INVALID;
        }

        $query = AiImage::query()
            ->publiclyVisible()
            ->where(fn ($query) => $query
                ->whereNull('title')
                ->orWhere('title', '')
                ->orWhereNull('description')
                ->orWhere('description', '')
                ->orWhereNull('category_id')
                ->orWhereDoesntHave('tags'));
        $total = $limit > 0 ? min($limit, $query->count()) : $query->count();
        $progress = $this->output->createProgressBar($total);
        $processed = 0;
        $done = 0;
        $failed = 0;

        $progress->start();

        $query
            ->orderBy('id')
            ->chunkById(50, function ($images) use ($editor, $limit, $progress, &$processed, &$done, &$failed): bool {
                foreach ($images as $image) {
                    /** @var AiImage $image */
                    $processed++;

                    try {
                        $editor->backfillMetadata($image);
                        $done++;
                    } catch (Throwable $e) {
                        report($e);
                        $failed++;
                        $progress->clear();
                        $this->warn("#{$image->id} failed: {$e->getMessage()}");
                        $progress->display();
                    }

                    $progress->advance();

                    if ($limit > 0 && $processed >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        $progress->finish();
        $this->newLine(2);
        $this->info("Processed {$processed}: {$done} metadata backfilled, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
