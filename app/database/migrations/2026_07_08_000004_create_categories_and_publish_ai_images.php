<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('categories')->upsert([
            ['name' => 'Ads & Sản phẩm', 'slug' => 'ads-product', 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Thương hiệu & Logo', 'slug' => 'brand-logo', 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Minh họa & 3D', 'slug' => 'illustration-3d', 'sort_order' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Poster & Visual', 'slug' => 'posters-visuals', 'sort_order' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chân dung', 'slug' => 'portraits', 'sort_order' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hình nền', 'slug' => 'wallpaper', 'sort_order' => 60, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Khác', 'slug' => 'other', 'sort_order' => 999, 'created_at' => now(), 'updated_at' => now()],
        ], ['slug'], ['name', 'sort_order', 'updated_at']);

        Schema::table('ai_images', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->boolean('is_published')->default(false)->after('status');
            $table->timestamp('published_at')->nullable()->after('is_published');
            $table->index(['is_published', 'published_at']);
            $table->index(['category_id', 'is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'is_published', 'published_at']);
            $table->dropIndex(['is_published', 'published_at']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['is_published', 'published_at']);
        });

        Schema::dropIfExists('categories');
    }
};
