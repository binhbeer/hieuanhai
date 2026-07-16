<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('skill', 40)->index();
            $table->string('name');
            $table->json('form_data')->nullable();
            $table->json('input_paths')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('generated_media', function (Blueprint $table) {
            $table->foreignId('skill_project_id')
                ->nullable()
                ->after('category_id')
                ->constrained()
                ->nullOnDelete();
            $table->index(['skill_project_id', 'created_at']);
        });

        $setting = DB::table('settings')->where('key', 'ai.image_max_reference_photos')->value('value');

        if ($setting !== null && (int) json_decode((string) $setting, true) === 1) {
            DB::table('settings')
                ->where('key', 'ai.image_max_reference_photos')
                ->update(['value' => json_encode(3), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('generated_media', function (Blueprint $table) {
            $table->dropIndex(['skill_project_id', 'created_at']);
            $table->dropConstrainedForeignId('skill_project_id');
        });

        Schema::dropIfExists('skill_projects');

        $setting = DB::table('settings')->where('key', 'ai.image_max_reference_photos')->value('value');

        if ($setting !== null && (int) json_decode((string) $setting, true) === 3) {
            DB::table('settings')
                ->where('key', 'ai.image_max_reference_photos')
                ->update(['value' => json_encode(1), 'updated_at' => now()]);
        }
    }
};
