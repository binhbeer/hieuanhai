<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_images', 'source_path')) {
            DB::table('ai_images')
                ->whereNotNull('source_path')
                ->where('source_path', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($images): void {
                    foreach ($images as $image) {
                        $meta = json_decode((string) $image->response_meta, true);
                        $meta = is_array($meta) ? $meta : [];
                        $paths = $meta['source_paths'] ?? null;

                        if (! is_array($paths) || $paths === []) {
                            $meta['source_paths'] = [$image->source_path];

                            DB::table('ai_images')
                                ->where('id', $image->id)
                                ->update(['response_meta' => json_encode($meta)]);
                        }
                    }
                });

            Schema::table('ai_images', function (Blueprint $table) {
                $table->dropColumn('source_path');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ai_images', 'source_path')) {
            Schema::table('ai_images', function (Blueprint $table) {
                $table->string('source_path')->nullable()->after('custom_prompt');
            });
        }

        DB::table('ai_images')
            ->whereNotNull('response_meta')
            ->orderBy('id')
            ->chunkById(100, function ($images): void {
                foreach ($images as $image) {
                    $meta = json_decode((string) $image->response_meta, true);

                    if (! is_array($meta)) {
                        continue;
                    }

                    $paths = $meta['source_paths'] ?? null;
                    $first = is_array($paths)
                        ? collect($paths)->first(fn (mixed $path): bool => is_string($path) && $path !== '')
                        : null;

                    if (! is_string($first)) {
                        continue;
                    }

                    DB::table('ai_images')
                        ->where('id', $image->id)
                        ->update(['source_path' => $first]);
                }
            });
    }
};
