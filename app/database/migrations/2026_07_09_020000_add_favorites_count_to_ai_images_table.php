<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->unsignedInteger('favorites_count')->default(0)->after('published_at');
        });

        DB::statement('UPDATE ai_images SET favorites_count = (SELECT COUNT(*) FROM ai_image_favorites WHERE ai_image_favorites.ai_image_id = ai_images.id)');
    }

    public function down(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->dropColumn('favorites_count');
        });
    }
};
