<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('ai_image_tag', function (Blueprint $table) {
            $table->foreignId('ai_image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_tag_id')->constrained('ai_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['ai_image_id', 'ai_tag_id']);
            $table->index(['ai_tag_id', 'ai_image_id']);
        });

        Schema::table('ai_images', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('favorites_count');
            $table->index(['is_featured', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'published_at']);
            $table->dropColumn('is_featured');
        });

        Schema::dropIfExists('ai_image_tag');
        Schema::dropIfExists('ai_tags');
    }
};
