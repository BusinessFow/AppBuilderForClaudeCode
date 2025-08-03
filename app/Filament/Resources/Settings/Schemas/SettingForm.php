<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->components([
                        TextInput::make('key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->alpha()
                            ->helperText('Unique identifier for the setting'),
                        
                        Select::make('group')
                            ->required()
                            ->options([
                                'general' => 'General',
                                'claude' => 'Claude API',
                                'system' => 'System',
                                'security' => 'Security',
                                'features' => 'Features',
                            ])
                            ->default('general'),
                        
                        Select::make('type')
                            ->required()
                            ->options([
                                'string' => 'String',
                                'boolean' => 'Boolean',
                                'integer' => 'Integer',
                                'float' => 'Float',
                                'json' => 'JSON',
                            ])
                            ->default('string')
                            ->reactive()
                            ->helperText('Data type for the value'),
                        
                        Textarea::make('description')
                            ->helperText('Description of what this setting does'),
                        
                        Toggle::make('is_public')
                            ->label('Public')
                            ->helperText('Whether this setting is visible to all users'),
                    ])
                    ->columns(2),
                
                Section::make('Value')
                    ->components([
                        TextInput::make('value')
                            ->label('Value')
                            ->helperText(fn ($get) => match ($get('type')) {
                                'boolean' => 'Use 1 for true, 0 for false',
                                'json' => 'Enter valid JSON format',
                                default => 'Enter the value for this setting',
                            })
                            ->required(fn ($get) => $get('type') !== 'boolean'),
                    ]),
            ]);
    }
}
