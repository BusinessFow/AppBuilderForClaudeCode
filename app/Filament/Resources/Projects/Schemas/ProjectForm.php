<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\Project;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Project Configuration')
                    ->tabs([
                        Tab::make('Basic Information')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('My Awesome Project'),
                                        
                                        TextInput::make('project_path')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('/home/user/projects/my-project')
                                            ->helperText(function ($state) {
                                                if ($state && !file_exists($state)) {
                                                    return "⚠️ This directory does not exist. It will be created automatically when you save the project.";
                                                }
                                                return 'Absolute path to the project directory on the server';
                                            })
                                            ->reactive(),
                                    ]),
                                
                                Textarea::make('description')
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->placeholder('Brief description of your project...'),
                                
                                Select::make('project_type')
                                    ->options([
                                        'web' => 'Web Application',
                                        'api' => 'API/Backend',
                                        'cli' => 'CLI Tool',
                                        'library' => 'Library/Package',
                                        'mobile' => 'Mobile App',
                                        'desktop' => 'Desktop Application',
                                        'fullstack' => 'Full Stack Application',
                                    ])
                                    ->placeholder('Select project type'),
                            ]),
                        
                        Tab::make('Technology Stack')
                            ->schema([
                                Select::make('technology_preset')
                                    ->label('Quick Setup - Technology Stack')
                                    ->options(array_map(fn($stack) => $stack['name'], Project::TECHNOLOGY_STACKS))
                                    ->placeholder('Select a preset or configure manually')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        if ($state && isset(Project::TECHNOLOGY_STACKS[$state])) {
                                            $stack = Project::TECHNOLOGY_STACKS[$state];
                                            $set('framework', $stack['framework']);
                                            $set('language', $stack['language']);
                                            $set('test_commands', $stack['test_commands']);
                                            $set('build_commands', $stack['build_commands']);
                                            $set('lint_commands', $stack['lint_commands']);
                                        }
                                    }),
                                
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('framework')
                                            ->placeholder('Laravel, React, Django, etc.')
                                            ->helperText('Primary framework used'),
                                        
                                        TextInput::make('language')
                                            ->placeholder('PHP, JavaScript, Python, etc.')
                                            ->helperText('Primary programming language'),
                                    ]),
                                
                                TagsInput::make('technologies')
                                    ->placeholder('Add technologies used...')
                                    ->suggestions([
                                        'PHP', 'JavaScript', 'TypeScript', 'Python', 'Ruby', 'Java', 'C#', 'Go',
                                        'Laravel', 'Symfony', 'React', 'Vue', 'Angular', 'Django', 'Rails',
                                        'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'Elasticsearch',
                                        'Docker', 'Kubernetes', 'AWS', 'Git', 'CI/CD',
                                    ])
                                    ->helperText('Technologies and tools used in the project'),
                                
                                Repeater::make('dependencies')
                                    ->label('Key Dependencies')
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->placeholder('Package name'),
                                        TextInput::make('version')
                                            ->placeholder('Version constraint'),
                                    ])
                                    ->columns(2)
                                    ->collapsible()
                                    ->defaultItems(0),
                            ]),
                        
                        Tab::make('Development Settings')
                            ->schema([
                                Section::make('Automation')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Toggle::make('auto_commit')
                                                    ->label('Auto Commit')
                                                    ->helperText('Automatically commit after changes'),
                                                
                                                Toggle::make('auto_test')
                                                    ->label('Auto Test')
                                                    ->helperText('Run tests automatically'),
                                                
                                                Toggle::make('tdd_mode')
                                                    ->label('TDD Mode')
                                                    ->helperText('Test-Driven Development mode'),
                                            ]),
                                        
                                        Toggle::make('code_review')
                                            ->label('Code Review Mode')
                                            ->helperText('Enable detailed code review before changes'),
                                    ]),
                                
                                Section::make('Commands')
                                    ->schema([
                                        TagsInput::make('test_commands')
                                            ->label('Test Commands')
                                            ->placeholder('Add test commands...')
                                            ->helperText('Commands to run tests (e.g., npm test, phpunit)'),
                                        
                                        TagsInput::make('build_commands')
                                            ->label('Build Commands')
                                            ->placeholder('Add build commands...')
                                            ->helperText('Commands to build the project'),
                                        
                                        TagsInput::make('lint_commands')
                                            ->label('Lint Commands')
                                            ->placeholder('Add lint commands...')
                                            ->helperText('Commands to lint/format code'),
                                    ]),
                                
                                Section::make('Project Focus')
                                    ->schema([
                                        TagsInput::make('focus_areas')
                                            ->label('Focus Areas')
                                            ->placeholder('Add areas to focus on...')
                                            ->suggestions([
                                                'Performance', 'Security', 'Testing', 'Documentation',
                                                'Refactoring', 'New Features', 'Bug Fixes', 'UI/UX',
                                                'API Development', 'Database Optimization',
                                            ])
                                            ->helperText('Areas Claude should focus on'),
                                        
                                        TagsInput::make('ignored_paths')
                                            ->label('Ignored Paths')
                                            ->placeholder('Add paths to ignore...')
                                            ->suggestions([
                                                'node_modules/', 'vendor/', 'dist/', 'build/',
                                                '.git/', 'storage/', 'cache/', 'logs/',
                                            ])
                                            ->helperText('Paths Claude should ignore'),
                                    ]),
                            ]),
                        
                        Tab::make('Claude Configuration')
                            ->schema([
                                Section::make('CLAUDE.md Content')
                                    ->schema([
                                        Textarea::make('claude_md')
                                            ->label('CLAUDE.md')
                                            ->rows(20)
                                            ->default(function () {
                                                return Project::DEFAULT_CLAUDE_MD;
                                            })
                                            ->reactive()
                                            ->helperText('This content will be saved as CLAUDE.md in your project. Template variables will be replaced when you save.'),
                                    ]),
                                
                                Section::make('Custom Rules')
                                    ->schema([
                                        Repeater::make('custom_rules')
                                            ->label('Custom Rules for Claude')
                                            ->schema([
                                                TextInput::make('rule')
                                                    ->required()
                                                    ->placeholder('Always use TypeScript for new files'),
                                            ])
                                            ->defaultItems(0)
                                            ->collapsible()
                                            ->helperText('Specific rules Claude should follow'),
                                    ]),
                            ]),
                        
                        Tab::make('Git Configuration')
                            ->schema([
                                Section::make('Repository Settings')
                                    ->schema([
                                        Toggle::make('git_enabled')
                                            ->label('Enable Git Integration')
                                            ->helperText('Enable Git version control for this project')
                                            ->reactive()
                                            ->afterStateUpdated(fn (Set $set, ?bool $state) => 
                                                $state ?: $set('auto_push', false)
                                            ),
                                        
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('git_remote_url')
                                                    ->label('Remote Repository URL')
                                                    ->placeholder('https://github.com/username/repo.git')
                                                    ->url()
                                                    ->visible(fn (Get $get) => $get('git_enabled'))
                                                    ->helperText('Git remote repository URL'),
                                                
                                                TextInput::make('git_branch')
                                                    ->label('Default Branch')
                                                    ->placeholder('main')
                                                    ->default('main')
                                                    ->visible(fn (Get $get) => $get('git_enabled'))
                                                    ->helperText('Default branch to work on'),
                                            ]),
                                        
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('git_username')
                                                    ->label('Git Username')
                                                    ->placeholder('John Doe')
                                                    ->visible(fn (Get $get) => $get('git_enabled'))
                                                    ->helperText('Username for Git commits'),
                                                
                                                TextInput::make('git_email')
                                                    ->label('Git Email')
                                                    ->email()
                                                    ->placeholder('john@example.com')
                                                    ->visible(fn (Get $get) => $get('git_enabled'))
                                                    ->helperText('Email for Git commits'),
                                            ]),
                                        
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('git_access_token')
                                                    ->label('Access Token (Optional)')
                                                    ->password()
                                                    ->visible(fn (Get $get) => $get('git_enabled'))
                                                    ->helperText('GitHub/GitLab personal access token for authentication'),
                                                
                                                TextInput::make('git_ssh_key_path')
                                                    ->label('SSH Key Path (Optional)')
                                                    ->placeholder('/home/user/.ssh/id_rsa')
                                                    ->visible(fn (Get $get) => $get('git_enabled'))
                                                    ->helperText('Path to SSH private key for authentication'),
                                            ]),
                                    ]),
                                
                                Section::make('Commit Settings')
                                    ->visible(fn (Get $get) => $get('git_enabled'))
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('commit_frequency')
                                                    ->label('Commit Frequency')
                                                    ->options([
                                                        'after_each_task' => 'After Each Task',
                                                        'after_each_todo' => 'After Each TODO',
                                                        'after_each_session' => 'After Each Session',
                                                        'time_based' => 'Time Based',
                                                        'manual' => 'Manual Only',
                                                    ])
                                                    ->default('after_each_task')
                                                    ->reactive()
                                                    ->helperText('When to automatically create commits'),
                                                
                                                TextInput::make('commit_time_interval')
                                                    ->label('Time Interval (minutes)')
                                                    ->numeric()
                                                    ->placeholder('30')
                                                    ->visible(fn (Get $get) => $get('commit_frequency') === 'time_based')
                                                    ->helperText('Commit every X minutes'),
                                            ]),
                                        
                                        Toggle::make('commit_with_tests')
                                            ->label('Run Tests Before Commit')
                                            ->helperText('Only commit if all tests pass')
                                            ->default(false),
                                        
                                        TextInput::make('commit_message_template')
                                            ->label('Commit Message Template')
                                            ->placeholder('[{type}] {description} - {task_id}')
                                            ->helperText('Template for commit messages. Variables: {type}, {description}, {task_id}, {timestamp}')
                                            ->default('[AUTO] {description}'),
                                    ]),
                                
                                Section::make('Push Settings')
                                    ->visible(fn (Get $get) => $get('git_enabled'))
                                    ->schema([
                                        Toggle::make('auto_push')
                                            ->label('Auto Push')
                                            ->helperText('Automatically push commits to remote')
                                            ->reactive()
                                            ->default(false),
                                        
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('push_frequency')
                                                    ->label('Push Frequency')
                                                    ->options([
                                                        'after_each_commit' => 'After Each Commit',
                                                        'after_each_session' => 'After Each Session',
                                                        'daily' => 'Daily',
                                                        'manual' => 'Manual Only',
                                                    ])
                                                    ->default('after_each_session')
                                                    ->visible(fn (Get $get) => $get('auto_push'))
                                                    ->helperText('When to push commits to remote'),
                                                
                                                Toggle::make('push_force')
                                                    ->label('Force Push')
                                                    ->helperText('Use force push (dangerous!)')
                                                    ->visible(fn (Get $get) => $get('auto_push'))
                                                    ->default(false),
                                            ]),
                                    ]),
                                
                                Section::make('Branch Management')
                                    ->visible(fn (Get $get) => $get('git_enabled'))
                                    ->schema([
                                        Toggle::make('create_feature_branches')
                                            ->label('Create Feature Branches')
                                            ->helperText('Create separate branches for each task')
                                            ->reactive()
                                            ->default(true),
                                        
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('branch_naming_pattern')
                                                    ->label('Branch Naming Pattern')
                                                    ->placeholder('feature/{task}')
                                                    ->default('feature/{task}')
                                                    ->visible(fn (Get $get) => $get('create_feature_branches'))
                                                    ->helperText('Pattern for branch names. Variables: {task}, {type}, {date}'),
                                                
                                                Toggle::make('delete_merged_branches')
                                                    ->label('Delete Merged Branches')
                                                    ->helperText('Automatically delete branches after merging')
                                                    ->visible(fn (Get $get) => $get('create_feature_branches'))
                                                    ->default(false),
                                            ]),
                                    ]),
                            ]),
                        
                        Tab::make('Advanced Settings')
                            ->schema([
                                Section::make('Claude Settings (JSON)')
                                    ->schema([
                                        Textarea::make('claude_settings_json')
                                            ->label('Claude Settings')
                                            ->rows(10)
                                            ->default(json_encode([
                                                'model' => 'claude-3-opus',
                                                'temperature' => 0.7,
                                                'max_tokens' => 4096,
                                            ], JSON_PRETTY_PRINT))
                                            ->helperText('Advanced Claude API settings in JSON format'),
                                    ]),
                                
                                Section::make('Local Settings (settings.local.json)')
                                    ->schema([
                                        Textarea::make('local_settings_json')
                                            ->label('Local Settings')
                                            ->rows(15)
                                            ->default(json_encode(Project::DEFAULT_LOCAL_SETTINGS, JSON_PRETTY_PRINT))
                                            ->helperText('Content for settings.local.json file'),
                                    ]),
                            ]),
                    ]),
                
                Hidden::make('status')->default('active'),
            ]);
    }
}