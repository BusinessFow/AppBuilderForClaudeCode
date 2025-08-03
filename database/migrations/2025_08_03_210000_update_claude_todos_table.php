<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claude_todos', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('priority');
            $table->boolean('completed_by_claude')->default(false)->after('status');
            $table->timestamp('executed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('claude_todos', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'completed_by_claude', 'executed_at']);
        });
    }
};