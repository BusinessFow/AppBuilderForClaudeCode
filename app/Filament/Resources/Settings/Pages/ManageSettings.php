<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class ManageSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string $resource = SettingResource::class;

    protected static ?string $title = 'Manage Settings';
    
    public function getView(): string
    {
        return 'filament.resources.settings.pages.manage-settings';
    }

    public array $data = [];

    public function mount(): void
    {
        $this->fillSettingsFromDatabase();
    }

    protected function fillSettingsFromDatabase(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        
        foreach ($settings as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                $this->data[$key] = Setting::castValue($value, $setting->type);
            }
        }
        
        $this->form->fill($this->data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->action('save')
                ->icon('heroicon-o-check')
                ->color('success'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        foreach ($data as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                Setting::set($key, $value, $setting->type, $setting->group);
            }
        }
        
        Setting::clearCache();
        
        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    public function schema(Schema $schema): Schema
    {
        $this->ensureClaudeSettings();
        $this->ensureGeneralSettings();
        $this->ensureFeaturesSettings();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Claude API Configuration')
                    ->description('Configure your Claude API settings')
                    ->components([
                        TextInput::make('claude_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->helperText('Your Anthropic API key')
                            ->required(),
                        
                        TextInput::make('claude_model')
                            ->label('Default Model')
                            ->helperText('Default Claude model to use')
                            ->default('claude-3-5-sonnet-20241022'),
                        
                        Toggle::make('claude_streaming')
                            ->label('Enable Streaming')
                            ->helperText('Enable streaming responses from Claude')
                            ->default(true),
                        
                        TextInput::make('claude_max_tokens')
                            ->label('Max Tokens')
                            ->numeric()
                            ->default(4096)
                            ->helperText('Maximum tokens per request'),
                        
                        TextInput::make('claude_temperature')
                            ->label('Temperature')
                            ->numeric()
                            ->default(0.7)
                            ->helperText('Controls randomness (0.0 - 1.0)'),
                    ])
                    ->columns(2),
                
                Section::make('General Settings')
                    ->description('General application settings')
                    ->components([
                        TextInput::make('app_name')
                            ->label('Application Name')
                            ->default('AppBuilder for Claude Code')
                            ->required(),
                        
                        Toggle::make('auto_update_enabled')
                            ->label('Enable Auto Updates')
                            ->helperText('Automatically check for and install updates')
                            ->default(false),
                        
                        TextInput::make('update_check_interval')
                            ->label('Update Check Interval (hours)')
                            ->numeric()
                            ->default(24)
                            ->visible(fn ($get) => $get('auto_update_enabled')),
                        
                        Toggle::make('telemetry_enabled')
                            ->label('Enable Telemetry')
                            ->helperText('Send anonymous usage data to improve the application')
                            ->default(false),
                        
                        Textarea::make('allowed_file_extensions')
                            ->label('Allowed File Extensions')
                            ->helperText('Comma-separated list of allowed file extensions')
                            ->default('php,js,ts,jsx,tsx,json,md,txt,yml,yaml'),
                    ])
                    ->columns(2),
                
                Section::make('Features')
                    ->description('Enable or disable application features')
                    ->components([
                        Toggle::make('feature_git_integration')
                            ->label('Git Integration')
                            ->helperText('Enable Git repository management')
                            ->default(true),
                        
                        Toggle::make('feature_web_scraping')
                            ->label('Web Scraping')
                            ->helperText('Enable web scraping functionality')
                            ->default(true),
                        
                        Toggle::make('feature_ai_analysis')
                            ->label('AI Analysis')
                            ->helperText('Enable AI-powered code analysis')
                            ->default(true),
                        
                        Toggle::make('feature_project_templates')
                            ->label('Project Templates')
                            ->helperText('Enable project template functionality')
                            ->default(true),
                        
                        Toggle::make('feature_code_generation')
                            ->label('Code Generation')
                            ->helperText('Enable AI code generation features')
                            ->default(true),
                        
                        Toggle::make('feature_collaboration')
                            ->label('Collaboration Tools')
                            ->helperText('Enable team collaboration features')
                            ->default(false),
                    ])
                    ->columns(3),
            ]);
    }

    protected function ensureClaudeSettings(): void
    {
        $claudeSettings = [
            'claude_api_key' => ['type' => 'string', 'group' => 'claude', 'description' => 'Anthropic API key'],
            'claude_model' => ['type' => 'string', 'group' => 'claude', 'description' => 'Default Claude model'],
            'claude_streaming' => ['type' => 'boolean', 'group' => 'claude', 'description' => 'Enable streaming responses'],
            'claude_max_tokens' => ['type' => 'integer', 'group' => 'claude', 'description' => 'Maximum tokens per request'],
            'claude_temperature' => ['type' => 'float', 'group' => 'claude', 'description' => 'Temperature for responses'],
        ];
        
        foreach ($claudeSettings as $key => $config) {
            if (!Setting::where('key', $key)->exists()) {
                Setting::create([
                    'key' => $key,
                    'value' => '',
                    'type' => $config['type'],
                    'group' => $config['group'],
                    'description' => $config['description'],
                ]);
            }
        }
    }

    protected function ensureGeneralSettings(): void
    {
        $generalSettings = [
            'app_name' => ['type' => 'string', 'group' => 'general', 'description' => 'Application name'],
            'auto_update_enabled' => ['type' => 'boolean', 'group' => 'general', 'description' => 'Enable auto updates'],
            'update_check_interval' => ['type' => 'integer', 'group' => 'general', 'description' => 'Update check interval in hours'],
            'telemetry_enabled' => ['type' => 'boolean', 'group' => 'general', 'description' => 'Enable telemetry'],
            'allowed_file_extensions' => ['type' => 'string', 'group' => 'general', 'description' => 'Allowed file extensions'],
        ];
        
        foreach ($generalSettings as $key => $config) {
            if (!Setting::where('key', $key)->exists()) {
                Setting::create([
                    'key' => $key,
                    'value' => '',
                    'type' => $config['type'],
                    'group' => $config['group'],
                    'description' => $config['description'],
                ]);
            }
        }
    }

    protected function ensureFeaturesSettings(): void
    {
        $featureSettings = [
            'feature_git_integration' => ['type' => 'boolean', 'group' => 'features', 'description' => 'Enable Git integration'],
            'feature_web_scraping' => ['type' => 'boolean', 'group' => 'features', 'description' => 'Enable web scraping'],
            'feature_ai_analysis' => ['type' => 'boolean', 'group' => 'features', 'description' => 'Enable AI analysis'],
            'feature_project_templates' => ['type' => 'boolean', 'group' => 'features', 'description' => 'Enable project templates'],
            'feature_code_generation' => ['type' => 'boolean', 'group' => 'features', 'description' => 'Enable code generation'],
            'feature_collaboration' => ['type' => 'boolean', 'group' => 'features', 'description' => 'Enable collaboration tools'],
        ];
        
        foreach ($featureSettings as $key => $config) {
            if (!Setting::where('key', $key)->exists()) {
                Setting::create([
                    'key' => $key,
                    'value' => '1',
                    'type' => $config['type'],
                    'group' => $config['group'],
                    'description' => $config['description'],
                ]);
            }
        }
    }

    public function getSchemas(): array 
    {
        return [
            'form' => $this->schema(Schema::make()),
        ];
    }
}