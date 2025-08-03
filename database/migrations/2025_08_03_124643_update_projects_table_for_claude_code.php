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
            // Remove old web scraping columns
            $table->dropColumn([
                'url',
                'login_url',
                'username',
                'password',
                'max_depth',
                'login_data',
                'scraped_urls',
                'screenshots',
                'form_data',
                'api_requests',
                'model_schema'
            ]);
            
            // Add new Claude Code columns
            $table->string('project_path')->after('description'); // Server path to project
            $table->json('technologies')->nullable(); // Used technologies
            $table->json('claude_settings')->nullable(); // Claude Code settings
            $table->text('claude_md')->nullable(); // CLAUDE.md content
            $table->json('local_settings')->nullable(); // settings.local.json content
            $table->boolean('auto_commit')->default(false); // Auto commit after changes
            $table->boolean('auto_test')->default(false); // Auto run tests
            $table->boolean('tdd_mode')->default(false); // TDD mode
            $table->json('custom_rules')->nullable(); // Custom rules for Claude
            $table->string('project_type')->nullable(); // Type of project (web, api, cli, etc.)
            $table->string('framework')->nullable(); // Framework used
            $table->string('language')->nullable(); // Primary language
            $table->json('dependencies')->nullable(); // Project dependencies
            $table->json('test_commands')->nullable(); // Test commands to run
            $table->json('build_commands')->nullable(); // Build commands
            $table->json('lint_commands')->nullable(); // Lint commands
            $table->string('git_branch')->nullable(); // Git branch to work on
            $table->boolean('code_review')->default(false); // Enable code review mode
            $table->json('ignored_paths')->nullable(); // Paths to ignore
            $table->json('focus_areas')->nullable(); // Areas to focus on
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Remove Claude Code columns
            $table->dropColumn([
                'project_path',
                'technologies',
                'claude_settings',
                'claude_md',
                'local_settings',
                'auto_commit',
                'auto_test',
                'tdd_mode',
                'custom_rules',
                'project_type',
                'framework',
                'language',
                'dependencies',
                'test_commands',
                'build_commands',
                'lint_commands',
                'git_branch',
                'code_review',
                'ignored_paths',
                'focus_areas'
            ]);
            
            // Restore old columns
            $table->string('url');
            $table->string('login_url')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->integer('max_depth')->default(3);
            $table->json('login_data')->nullable();
            $table->json('scraped_urls')->nullable();
            $table->json('screenshots')->nullable();
            $table->json('form_data')->nullable();
            $table->json('api_requests')->nullable();
            $table->json('model_schema')->nullable();
        });
    }
};