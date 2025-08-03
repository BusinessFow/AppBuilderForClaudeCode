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
        Schema::table('projects', function (Blueprint $table) {
            // Git configuration
            $table->boolean('git_enabled')->default(false)->after('git_branch');
            $table->string('git_remote_url')->nullable()->after('git_enabled');
            $table->string('git_username')->nullable()->after('git_remote_url');
            $table->string('git_email')->nullable()->after('git_username');
            $table->text('git_access_token')->nullable()->after('git_email'); // encrypted
            $table->string('git_ssh_key_path')->nullable()->after('git_access_token');
            
            // Commit settings
            $table->enum('commit_frequency', ['after_each_task', 'after_each_session', 'after_each_todo', 'manual', 'time_based'])->default('after_each_task')->after('git_ssh_key_path');
            $table->integer('commit_time_interval')->nullable()->after('commit_frequency'); // in minutes
            $table->boolean('commit_with_tests')->default(false)->after('commit_time_interval');
            $table->string('commit_message_template')->nullable()->after('commit_with_tests');
            
            // Push settings
            $table->boolean('auto_push')->default(false)->after('commit_message_template');
            $table->enum('push_frequency', ['after_each_commit', 'after_each_session', 'daily', 'manual'])->default('after_each_session')->after('auto_push');
            $table->boolean('push_force')->default(false)->after('push_frequency');
            
            // Branch settings
            $table->boolean('create_feature_branches')->default(true)->after('push_force');
            $table->string('branch_naming_pattern')->default('feature/{task}')->after('create_feature_branches');
            $table->boolean('delete_merged_branches')->default(false)->after('branch_naming_pattern');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'git_enabled',
                'git_remote_url',
                'git_username',
                'git_email',
                'git_access_token',
                'git_ssh_key_path',
                'commit_frequency',
                'commit_time_interval',
                'commit_with_tests',
                'commit_message_template',
                'auto_push',
                'push_frequency',
                'push_force',
                'create_feature_branches',
                'branch_naming_pattern',
                'delete_merged_branches',
            ]);
        });
    }
};