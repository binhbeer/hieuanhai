<?php

namespace App\Console\Commands;

use App\Models\GeneratedMedia;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateMediaToMediaLibrary extends Command
{
    protected $signature = 'media:migrate-to-library {--chunk=100}';

    protected $description = 'Chuyển result_path/source_paths/avatar_path sang spatie media library (idempotent).';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $disk = Storage::disk('public');

        $this->info('Chuyển ảnh GeneratedMedia...');
        GeneratedMedia::query()
            ->whereNotNull('result_path')
            ->orderBy('id')
            ->chunkById($chunk, function ($images) use ($disk): void {
                foreach ($images as $image) {
                    try {
                        if (is_string($image->result_path) && $image->getFirstMedia('result') === null && $disk->exists($image->result_path)) {
                            $media = $image->addMediaFromDisk($image->result_path, 'public')
                                ->preservingOriginal()
                                ->toMediaCollection('result');
                            $image->updateQuietly(['result_path' => $media->getPathRelativeToRoot()]);
                        }

                        $sourcePaths = is_array($image->response_meta) ? ($image->response_meta['source_paths'] ?? []) : [];

                        if (is_array($sourcePaths) && $sourcePaths !== [] && $image->getMedia('sources')->isEmpty()) {
                            $newPaths = [];

                            foreach ($sourcePaths as $sourcePath) {
                                if (! is_string($sourcePath) || ! $disk->exists($sourcePath)) {
                                    $newPaths[] = $sourcePath;

                                    continue;
                                }

                                $media = $image->addMediaFromDisk($sourcePath, 'public')
                                    ->preservingOriginal()
                                    ->toMediaCollection('sources');
                                $newPaths[] = $media->getPathRelativeToRoot();
                            }

                            $responseMeta = $image->response_meta;
                            $responseMeta['source_paths'] = $newPaths;
                            $image->updateQuietly(['response_meta' => $responseMeta]);
                        }
                    } catch (Throwable $e) {
                        report($e);
                        $this->warn("GeneratedMedia #{$image->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info('Chuyển avatar User...');
        User::query()
            ->whereNotNull('avatar_path')
            ->orderBy('id')
            ->chunkById($chunk, function ($users) use ($disk): void {
                foreach ($users as $user) {
                    try {
                        if (is_string($user->avatar_path) && $user->getFirstMedia('avatar') === null && $disk->exists($user->avatar_path)) {
                            $media = $user->addMediaFromDisk($user->avatar_path, 'public')
                                ->preservingOriginal()
                                ->toMediaCollection('avatar');
                            $user->updateQuietly(['avatar_path' => $media->getPathRelativeToRoot()]);
                        }
                    } catch (Throwable $e) {
                        report($e);
                        $this->warn("User #{$user->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info('Xong.');

        return self::SUCCESS;
    }
}
