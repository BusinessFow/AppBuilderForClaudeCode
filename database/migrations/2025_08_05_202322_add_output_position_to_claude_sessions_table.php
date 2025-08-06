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
        Schema::table('claude_sessions', function (Blueprint $table) {
            $table->integer('output_position')->default(0)->after('last_output');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claude_sessions', function (Blueprint $table) {
            $table->dropColumn('output_position');
        });
    }
};
