<?php

namespace App\Livewire;

use App\Models\ClaudeTodo;
use App\Models\Project;
use App\Services\ClaudeProcessManager;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ClaudeTasksList extends Component implements HasTable, HasForms, HasActions
{
    use InteractsWithTable;
    use InteractsWithForms;
    use InteractsWithActions;
    
    public Project $project;
    public bool $isRunning = false;
    
    public function mount(Project $project, bool $isRunning = false): void
    {
        $this->project = $project;
        $this->isRunning = $isRunning;
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->query(ClaudeTodo::query()->where('project_id', $this->project->id))
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->poll('10s')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->size('sm')
                    ->width('50px'),
                    
                TextColumn::make('command')
                    ->label('Task')
                    ->searchable()
                    ->wrap()
                    ->description(fn (ClaudeTodo $record): ?string => $record->description)
                    ->tooltip(fn (ClaudeTodo $record): ?string => $record->result),
                    
                BadgeColumn::make('priority')
                    ->label('Priority')
                    ->formatStateUsing(fn (int $state): string => match($state) {
                        3 => 'High',
                        2 => 'Medium',
                        1 => 'Low',
                        default => 'Low'
                    })
                    ->colors([
                        'danger' => 3,
                        'warning' => 2,
                        'secondary' => 1,
                    ])
                    ->icons([
                        'heroicon-o-arrow-up' => 3,
                        'heroicon-o-minus' => 2,
                        'heroicon-o-arrow-down' => 1,
                    ]),
                    
                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'processing',
                        'secondary' => 'pending',
                        'danger' => 'failed',
                    ]),
                    
                IconColumn::make('completed_by_claude')
                    ->label('By Claude')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn ($state) => $state ? 'Completed by Claude' : 'Not completed by Claude'),
                    
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, H:i')
                    ->sortable()
                    ->description(fn (ClaudeTodo $record): ?string => 
                        $record->completed_at ? 'Completed: ' . $record->completed_at->format('M j, H:i') : null
                    ),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('execute')
                        ->label('Execute')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn (ClaudeTodo $record): bool => 
                            $record->status === 'pending' && $this->isRunning
                        )
                        ->action(function (ClaudeTodo $record): void {
                            $this->executeTask($record);
                        }),
                        
                    EditAction::make()
                        ->form([
                            TextInput::make('command')
                                ->label('Task')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description')
                                ->label('Description')
                                ->rows(2),
                            Select::make('priority')
                                ->options([
                                    1 => 'Low',
                                    2 => 'Medium',
                                    3 => 'High',
                                ])
                                ->required(),
                        ])
                        ->modalHeading('Edit Task'),
                        
                    Action::make('markCompleted')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn (ClaudeTodo $record): bool => 
                            $record->status !== 'completed'
                        )
                        ->action(function (ClaudeTodo $record): void {
                            $record->markAsCompleted('Manually marked as completed');
                            $record->update(['completed_by_claude' => false]);
                        }),
                        
                    DeleteAction::make(),
                ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Task')
                    ->form([
                        TextInput::make('command')
                            ->label('Task')
                            ->placeholder('e.g., Add user authentication')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Description (optional)')
                            ->rows(2),
                        Select::make('priority')
                            ->label('Priority')
                            ->options([
                                1 => 'Low',
                                2 => 'Medium',
                                3 => 'High',
                            ])
                            ->default(2)
                            ->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['project_id'] = $this->project->id;
                        $data['status'] = 'pending';
                        $data['sort_order'] = ClaudeTodo::where('project_id', $this->project->id)
                            ->max('sort_order') + 1;
                        return $data;
                    })
                    ->modalHeading('Add New Task')
                    ->modalButton('Add Task')
                    ->createAnother(false),
            ])
            ->emptyStateHeading('No tasks yet')
            ->emptyStateDescription('Add a task for Claude to implement')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Add First Task')
                    ->form([
                        TextInput::make('command')
                            ->label('Task')
                            ->placeholder('e.g., Add user authentication')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Description (optional)')
                            ->rows(2),
                        Select::make('priority')
                            ->label('Priority')
                            ->options([
                                1 => 'Low',
                                2 => 'Medium',
                                3 => 'High',
                            ])
                            ->default(2)
                            ->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['project_id'] = $this->project->id;
                        $data['status'] = 'pending';
                        $data['sort_order'] = 1;
                        return $data;
                    }),
            ]);
    }
    
    protected function executeTask(ClaudeTodo $task): void
    {
        $manager = app(ClaudeProcessManager::class);
        $session = $this->project->activeClaudeSession;
        
        if (!$session) {
            Notification::make()
                ->title('No active Claude session')
                ->danger()
                ->send();
            return;
        }
        
        try {
            $task->markAsProcessing();
            $manager->sendCommand($session, $task->command);
            
            // Wait for response
            sleep(1);
            
            $output = $manager->getOutput($session);
            if ($output) {
                $task->markAsCompleted($output);
                $task->update([
                    'completed_by_claude' => true,
                    'executed_at' => now(),
                ]);
            }
            
            Notification::make()
                ->title('Task executed')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            $task->markAsFailed($e->getMessage());
            
            Notification::make()
                ->title('Task execution failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function render()
    {
        return view('livewire.claude-tasks-list');
    }
}