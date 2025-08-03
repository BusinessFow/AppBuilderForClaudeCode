<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('login_url')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->json('login_data')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->text('description')->nullable();
            $table->json('model_schema')->nullable();
            $table->integer('max_depth')->default(3);
            $table->json('scraped_urls')->nullable();
            $table->json('screenshots')->nullable();
            $table->json('form_data')->nullable();
            $table->json('api_requests')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
