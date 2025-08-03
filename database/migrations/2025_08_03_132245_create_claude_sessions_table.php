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
        Schema::create('claude_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('process_id')->nullable();
            $table->enum('status', ['idle', 'running', 'stopped', 'error'])->default('idle');
            $table->text('last_input')->nullable();
            $table->text('last_output')->nullable();
            $table->json('conversation_history')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
            
            $table->index('project_id');
            $table->index('status');
        });
        
        Schema::create('claude_todos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('claude_session_id')->nullable()->constrained()->onDelete('set null');
            $table->text('command');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('priority')->default(0);
            $table->text('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['project_id', 'status']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claude_todos');
        Schema::dropIfExists('claude_sessions');
    }
};