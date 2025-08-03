<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Services\ClaudeProcessManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn ($record) => route('filament.admin.resources.projects.claude', $record)),

//                TextColumn::make('project_path')
//                    ->label('Path')
//                    ->searchable()
//                    ->limit(30)
//                    ->tooltip(fn ($record) => $record->project_path),

//                BadgeColumn::make('project_type')
//                    ->label('Type')
//                    ->colors([
//                        'primary' => 'web',
//                        'success' => 'api',
//                        'warning' => 'cli',
//                        'danger' => 'mobile',
//                        'secondary' => static fn ($state): bool => in_array($state, ['library', 'desktop']),
//                        'info' => 'fullstack',
//                    ]),

//                TextColumn::make('framework')
//                    ->searchable()
//                    ->placeholder('Not set')
//                    ->toggleable(),
//
//                TextColumn::make('language')
//                    ->searchable()
//                    ->placeholder('Not set')
//                    ->toggleable(),
//
//                IconColumn::make('auto_commit')
//                    ->label('Commit')
//                    ->boolean()
//                    ->trueIcon('heroicon-o-check-circle')
//                    ->falseIcon('heroicon-o-x-circle')
//                    ->trueColor('success')
//                    ->falseColor('gray')
//                    ->tooltip(fn ($state) => $state ? 'Auto-commit enabled' : 'Auto-commit disabled'),
//
//                IconColumn::make('auto_test')
//                    ->label('Test')
//                    ->boolean()
//                    ->trueIcon('heroicon-o-check-circle')
//                    ->falseIcon('heroicon-o-x-circle')
//                    ->trueColor('success')
//                    ->falseColor('gray')
//                    ->tooltip(fn ($state) => $state ? 'Auto-test enabled' : 'Auto-test disabled'),
//
//                IconColumn::make('tdd_mode')
//                    ->label('TDD')
//                    ->boolean()
//                    ->trueIcon('heroicon-o-check-circle')
//                    ->falseIcon('heroicon-o-x-circle')
//                    ->trueColor('success')
//                    ->falseColor('gray')
//                    ->tooltip(fn ($state) => $state ? 'TDD mode enabled' : 'TDD mode disabled'),
//
                BadgeColumn::make('activeClaudeSession.status')
                    ->label('Claude')
                    ->colors([
                        'success' => 'running',
                        'warning' => 'idle',
                        'danger' => static fn ($state): bool => in_array($state, ['stopped', 'error']),
                        'gray' => null,
                    ])
                    ->formatStateUsing(fn ($state) => $state ?? 'Not started')
                    ->placeholder('Not started'),

                TextColumn::make('todos_progress')
                    ->label('Tasks')
                    ->getStateUsing(function ($record) {
                        $total = $record->claudeTodos()->count();
                        $completed = $record->completedTodos()->count();

                        if ($total === 0) {
                            return '0 / 0';
                        }

                        $percentage = round(($completed / $total) * 100);
                        return "{$completed} / {$total} ({$percentage}%)";
                    })
                    ->color(function ($record) {
                        $total = $record->claudeTodos()->count();
                        $completed = $record->completedTodos()->count();

                        if ($total === 0) return 'gray';

                        $percentage = ($completed / $total) * 100;

                        if ($percentage === 100) return 'success';
                        if ($percentage >= 50) return 'warning';
                        return 'danger';
                    }),

                BadgeColumn::make('status')
                    ->label('Project')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => 'inactive',
                    ])
                    ->icons([
                        'heroicon-s-play' => 'active',
                        'heroicon-s-pause' => 'paused',
                        'heroicon-s-stop' => 'inactive',
                    ]),

//                TextColumn::make('created_at')
//                    ->dateTime('M j, Y')
//                    ->sortable()
//                    ->toggleable(isToggledHiddenByDefault: true),
//
//                TextColumn::make('updated_at')
//                    ->dateTime('M j, Y')
//                    ->sortable()
//                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('project_type')
                    ->options([
                        'web' => 'Web Application',
                        'api' => 'API/Backend',
                        'cli' => 'CLI Tool',
                        'library' => 'Library/Package',
                        'mobile' => 'Mobile App',
                        'desktop' => 'Desktop Application',
                        'fullstack' => 'Full Stack Application',
                    ])
                    ->multiple(),

                SelectFilter::make('framework')
                    ->options([
                        'Laravel' => 'Laravel',
                        'React' => 'React',
                        'Vue' => 'Vue.js',
                        'Django' => 'Django',
                        'Rails' => 'Ruby on Rails',
                    ])
                    ->searchable(),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'inactive' => 'Inactive',
                    ]),

                Filter::make('automation')
                    ->form([
                        \Filament\Forms\Components\Toggle::make('auto_commit'),
                        \Filament\Forms\Components\Toggle::make('auto_test'),
                        \Filament\Forms\Components\Toggle::make('tdd_mode'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['auto_commit'], fn ($q) => $q->where('auto_commit', true))
                            ->when($data['auto_test'], fn ($q) => $q->where('auto_test', true))
                            ->when($data['tdd_mode'], fn ($q) => $q->where('tdd_mode', true));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['auto_commit'] ?? false) {
                            $indicators['auto_commit'] = 'Auto-commit enabled';
                        }
                        if ($data['auto_test'] ?? false) {
                            $indicators['auto_test'] = 'Auto-test enabled';
                        }
                        if ($data['tdd_mode'] ?? false) {
                            $indicators['tdd_mode'] = 'TDD mode enabled';
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Action::make('toggle_claude')
                    ->label(function ($record) {
                        $session = $record->activeClaudeSession;
                        return $session && $session->isRunning() ? 'Stop' : 'Start';
                    })
                    ->icon(function ($record) {
                        $session = $record->activeClaudeSession;
                        return $session && $session->isRunning() ? 'heroicon-o-stop' : 'heroicon-o-play';
                    })
                    ->color(function ($record) {
                        $session = $record->activeClaudeSession;
                        return $session && $session->isRunning() ? 'danger' : 'success';
                    })
                    ->action(function ($record) {
                        $manager = app(ClaudeProcessManager::class);
                        $session = $record->activeClaudeSession;

                        try {
                            if ($session && $session->isRunning()) {
                                $manager->stopSession($session);
                                Notification::make()
                                    ->title('Claude stopped')
                                    ->success()
                                    ->send();
                            } else {
                                $manager->startSession($record);
                                Notification::make()
                                    ->title('Claude started')
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('toggle_status')
                        ->label(fn ($record) => $record->status === 'active' ? 'Pause' : 'Activate')
                        ->icon(fn ($record) => $record->status === 'active' ? 'heroicon-o-pause' : 'heroicon-o-play')
                        ->color(fn ($record) => $record->status === 'active' ? 'warning' : 'success')
                        ->action(function ($record) {
                            $record->update([
                                'status' => $record->status === 'active' ? 'paused' : 'active'
                            ]);
                        }),
                ]),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
                BulkAction::make('activate')
                    ->label('Activate Selected')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(fn ($records) => $records->each->update(['status' => 'active']))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('pause')
                    ->label('Pause Selected')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->action(fn ($records) => $records->each->update(['status' => 'paused']))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s')
            ->recordUrl(fn ($record) => route('filament.admin.resources.projects.claude', $record));
    }
}
