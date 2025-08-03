<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
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
        'focus_areas',
        'status',
        'started_at',
        'completed_at',
        // Git fields
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
    ];

    protected $casts = [
        'technologies' => 'array',
        'claude_settings' => 'array',
        'local_settings' => 'array',
        'custom_rules' => 'array',
        'dependencies' => 'array',
        'test_commands' => 'array',
        'build_commands' => 'array',
        'lint_commands' => 'array',
        'ignored_paths' => 'array',
        'focus_areas' => 'array',
        'auto_commit' => 'boolean',
        'auto_test' => 'boolean',
        'tdd_mode' => 'boolean',
        'code_review' => 'boolean',
        'git_enabled' => 'boolean',
        'commit_with_tests' => 'boolean',
        'auto_push' => 'boolean',
        'push_force' => 'boolean',
        'create_feature_branches' => 'boolean',
        'delete_merged_branches' => 'boolean',
        'commit_time_interval' => 'integer',
        'git_access_token' => 'encrypted',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Predefined technology stacks
    const TECHNOLOGY_STACKS = [
        'laravel' => [
            'name' => 'Laravel',
            'language' => 'PHP',
            'framework' => 'Laravel',
            'test_commands' => ['php artisan test', 'vendor/bin/pest'],
            'build_commands' => ['composer install', 'npm install', 'npm run build'],
            'lint_commands' => ['./vendor/bin/pint'],
        ],
        'react' => [
            'name' => 'React',
            'language' => 'JavaScript/TypeScript',
            'framework' => 'React',
            'test_commands' => ['npm test', 'npm run test:coverage'],
            'build_commands' => ['npm install', 'npm run build'],
            'lint_commands' => ['npm run lint', 'npm run lint:fix'],
        ],
        'vue' => [
            'name' => 'Vue.js',
            'language' => 'JavaScript/TypeScript',
            'framework' => 'Vue',
            'test_commands' => ['npm test', 'npm run test:unit'],
            'build_commands' => ['npm install', 'npm run build'],
            'lint_commands' => ['npm run lint'],
        ],
        'django' => [
            'name' => 'Django',
            'language' => 'Python',
            'framework' => 'Django',
            'test_commands' => ['python manage.py test', 'pytest'],
            'build_commands' => ['pip install -r requirements.txt'],
            'lint_commands' => ['flake8', 'black .'],
        ],
        'rails' => [
            'name' => 'Ruby on Rails',
            'language' => 'Ruby',
            'framework' => 'Rails',
            'test_commands' => ['rails test', 'rspec'],
            'build_commands' => ['bundle install', 'rails db:setup'],
            'lint_commands' => ['rubocop'],
        ],
    ];

    // Default CLAUDE.md template
    const DEFAULT_CLAUDE_MD = <<<'MD'
# Project Overview

## Description
{project_description}

## Technologies
{technologies}

## Project Structure
Describe your project structure here...

## Key Features
- Feature 1
- Feature 2
- Feature 3

## Development Guidelines

### Code Style
- Follow {framework} best practices
- Use consistent naming conventions
- Write clean, readable code

### Testing
- Write tests for all new features
- Maintain test coverage above 80%
- Run tests before committing

### Git Workflow
- Create feature branches
- Write descriptive commit messages
- Submit PRs for review

## Commands

### Test
```bash
{test_command}
```

### Build
```bash
{build_command}
```

### Lint
```bash
{lint_command}
```

## Notes for Claude
- {auto_commit_note}
- {auto_test_note}
- {tdd_note}

MD;

    // Default settings.local.json template
    const DEFAULT_LOCAL_SETTINGS = [
        'codeEditor' => [
            'automaticCommits' => false,
            'testOnSave' => false,
        ],
        'assistant' => [
            'personality' => 'professional',
            'verbosity' => 'normal',
            'proactivity' => 'moderate',
        ],
        'development' => [
            'framework' => null,
            'language' => null,
            'testRunner' => null,
            'linter' => null,
        ],
    ];
    
    public function claudeSessions(): HasMany
    {
        return $this->hasMany(ClaudeSession::class);
    }
    
    public function activeClaudeSession(): HasOne
    {
        return $this->hasOne(ClaudeSession::class)->where('status', 'running')->latest();
    }
    
    public function claudeTodos(): HasMany
    {
        return $this->hasMany(ClaudeTodo::class);
    }
    
    public function pendingTodos(): HasMany
    {
        return $this->hasMany(ClaudeTodo::class)->where('status', 'pending');
    }
    
    public function completedTodos(): HasMany
    {
        return $this->hasMany(ClaudeTodo::class)->where('status', 'completed');
    }
}