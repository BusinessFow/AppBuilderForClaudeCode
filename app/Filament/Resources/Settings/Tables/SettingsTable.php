<?php

namespace App\Filament\Resources\Settings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                BadgeColumn::make('group')
                    ->colors([
                        'primary' => 'general',
                        'success' => 'claude',
                        'warning' => 'system',
                        'danger' => 'security',
                        'info' => 'features',
                    ])
                    ->sortable(),
                
                TextColumn::make('value')
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->value;
                    }),
                
                BadgeColumn::make('type')
                    ->colors([
                        'gray' => 'string',
                        'success' => 'boolean',
                        'warning' => 'integer',
                        'danger' => 'float',
                        'info' => 'json',
                    ]),
                
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                
                ToggleColumn::make('is_public')
                    ->label('Public'),
                
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->options([
                        'general' => 'General',
                        'claude' => 'Claude API',
                        'system' => 'System',
                        'security' => 'Security',
                        'features' => 'Features',
                    ]),
                
                SelectFilter::make('type')
                    ->options([
                        'string' => 'String',
                        'boolean' => 'Boolean',
                        'integer' => 'Integer',
                        'float' => 'Float',
                        'json' => 'JSON',
                    ]),
            ])
            ->defaultSort('group')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
