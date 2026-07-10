<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->string('source')->nullable()->after('custom_prompt')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ai_images', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
