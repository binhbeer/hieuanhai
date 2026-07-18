<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_media', function (Blueprint $table): void {
            $table->dropForeign(['skill_project_id']);
            $table->dropIndex(['skill_project_id', 'created_at']);
        });

        Schema::rename('skill_projects', 'studio_projects');

        Schema::table('studio_projects', function (Blueprint $table): void {
            $table->renameColumn('skill', 'tool');
        });

        Schema::table('generated_media', function (Blueprint $table): void {
            $table->renameColumn('skill_project_id', 'studio_project_id');
        });

        Schema::table('generated_media', function (Blueprint $table): void {
            $table->foreign('studio_project_id')->references('id')->on('studio_projects')->nullOnDelete();
            $table->index(['studio_project_id', 'created_at']);
        });

        DB::table('generated_media')
            ->select(['id', 'source', 'request_meta'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) ($row->request_meta ?? ''), true);

                    if (! is_array($meta)) {
                        continue;
                    }

                    $mode = $meta['generation_mode'] ?? null;
                    $meta['generation_mode'] = match ($mode) {
                        'quick-edit' => 'quick',
                        'generator' => 'creator',
                        default => $mode,
                    };

                    if (array_key_exists('skill', $meta)) {
                        $meta['tool'] = $meta['skill'];
                        unset($meta['skill']);
                    }

                    DB::table('generated_media')->where('id', $row->id)->update([
                        'source' => $row->source === 'skills' ? 'web' : $row->source,
                        'request_meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('generated_media')
            ->select(['id', 'source', 'request_meta', 'studio_project_id'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) ($row->request_meta ?? ''), true);

                    if (! is_array($meta)) {
                        continue;
                    }

                    $mode = $meta['generation_mode'] ?? null;
                    $meta['generation_mode'] = match ($mode) {
                        'quick' => 'quick-edit',
                        'creator' => 'generator',
                        default => $mode,
                    };

                    if (array_key_exists('tool', $meta)) {
                        $meta['skill'] = $meta['tool'];
                        unset($meta['tool']);
                    }

                    DB::table('generated_media')->where('id', $row->id)->update([
                        'source' => $row->source === 'web' && $row->studio_project_id !== null ? 'skills' : $row->source,
                        'request_meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });

        Schema::table('generated_media', function (Blueprint $table): void {
            $table->dropForeign(['studio_project_id']);
            $table->dropIndex(['studio_project_id', 'created_at']);
        });

        Schema::table('generated_media', function (Blueprint $table): void {
            $table->renameColumn('studio_project_id', 'skill_project_id');
        });

        Schema::table('studio_projects', function (Blueprint $table): void {
            $table->renameColumn('tool', 'skill');
        });

        Schema::rename('studio_projects', 'skill_projects');

        Schema::table('generated_media', function (Blueprint $table): void {
            $table->foreign('skill_project_id')->references('id')->on('skill_projects')->nullOnDelete();
            $table->index(['skill_project_id', 'created_at']);
        });
    }
};
