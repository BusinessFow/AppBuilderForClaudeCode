<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support modifying ENUMs directly, so we need to recreate the column
        Schema::table('projects', function (Blueprint $table) {
            // First, create a temporary column
            $table->string('status_new')->nullable()->after('focus_areas');
        });
        
        // Copy data to new column
        DB::table('projects')->update(['status_new' => DB::raw('status')]);
        
        // Drop old column and rename new one
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
        });
        
        // Add default value
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert back to enum
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status_old')->nullable()->after('focus_areas');
        });
        
        // Map new values to old enum values
        DB::table('projects')
            ->where('status', 'active')
            ->update(['status_old' => 'pending']);
        DB::table('projects')
            ->where('status', 'paused')
            ->update(['status_old' => 'pending']);
        DB::table('projects')
            ->where('status', 'inactive')
            ->update(['status_old' => 'failed']);
        
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('status_old', 'status');
        });
        
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending')->change();
        });
    }
};