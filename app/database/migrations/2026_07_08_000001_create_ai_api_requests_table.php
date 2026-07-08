<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_api_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_api_key_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_image_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->string('status')->index();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('quota_charged')->default(false);
            $table->text('error')->nullable();
            $table->json('request_meta')->nullable();
            $table->json('response_meta')->nullable();
            $table->timestamps();

            $table->index(['ai_api_key_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_api_requests');
    }
};
