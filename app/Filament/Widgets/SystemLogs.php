<?php

namespace App\Filament\Widgets;

use App\Models\SystemLog;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SystemLogs extends BaseWidget
{
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'System Logs';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(SystemLog::query()->with(['user', 'project']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),
                    
                Tables\Columns\BadgeColumn::make('level')
                    ->label('Level')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->colors([
                        'success' => 'success',
                        'info' => 'info',
                        'primary' => 'debug',
                        'warning' => 'warning',
                        'danger' => fn ($state) => in_array($state, ['error', 'critical', 'alert', 'emergency']),
                    ]),
                    
                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->wrap()
                    ->limit(150)
                    ->tooltip(function ($record) {
                        return $record->message;
                    })
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->badge()
                    ->color('success')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('context')
                    ->label('Context')
                    ->formatStateUsing(function ($state) {
                        if (empty($state) || (is_array($state) && count($state) === 0)) {
                            return '-';
                        }
                        return Str::limit(json_encode($state), 50);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options([
                        'debug' => 'Debug',
                        'info' => 'Info',
                        'success' => 'Success',
                        'warning' => 'Warning',
                        'error' => 'Error',
                    ]),
                    
                Tables\Filters\SelectFilter::make('channel')
                    ->options(function () {
                        return SystemLog::distinct()
                            ->pluck('channel', 'channel')
                            ->toArray();
                    }),
                    
                Tables\Filters\Filter::make('today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today()))
                    ->label('Today Only'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->poll('10s')
            ->striped();
    }
}