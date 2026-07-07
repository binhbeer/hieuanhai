<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_key', 64)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('preset')->nullable();
            $table->text('prompt');
            $table->text('custom_prompt')->nullable();
            $table->string('source_path')->nullable();
            $table->string('result_path')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->string('status')->index();
            $table->text('error')->nullable();
            $table->json('request_meta')->nullable();
            $table->json('response_meta')->nullable();
            $table->timestamps();

            $table->index(['visitor_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_images');
    }
};
