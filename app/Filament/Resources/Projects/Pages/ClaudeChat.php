<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ClaudeTodo;
use App\Models\Project;
use App\Models\SystemLog;
use App\Services\ClaudeProcessManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Process;

class ClaudeChat extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static string $resource = ProjectResource::class;
    protected string $view = 'filament.resources.projects.pages.claude-chat';
    
    public Project $project;
    public ?string $input = '';
    public array $messages = [];
    public bool $isRunning = false;
    public ?string $sessionStatus = null;
    
    protected $listeners = ['refreshChat' => '$refresh'];
    
    public function mount($record): void
    {
        if (is_numeric($record)) {
            $this->project = Project::findOrFail($record);
        } else {
            $this->project = $record;
        }
        $this->loadSession();
    }
    
    public function getTitle(): string
    {
        return "Claude Chat - {$this->project->name}";
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggle_claude')
                ->label(fn() => $this->isRunning ? 'Stop Claude' : 'Start Claude')
                ->icon(fn() => $this->isRunning ? 'heroicon-o-stop' : 'heroicon-o-play')
                ->color(fn() => $this->isRunning ? 'danger' : 'success')
                ->action('toggleClaude')
                ->extraAttributes([
                    'class' => $this->isRunning ? 'fi-btn-danger' : 'fi-btn-success',
                ]),
            
            Action::make('createMissingDirectory')
                ->label('Create Directory')
                ->icon('heroicon-o-folder-plus')
                ->color('warning')
                ->action(function () {
                    $this->createProjectDirectory($this->project->project_path);
                })
                ->modalHeading('Create Project Directory')
                ->modalDescription(fn() => "The directory '{$this->project->project_path}' does not exist. Do you want to create it?")
                ->modalSubmitActionLabel('Create Directory')
                ->requiresConfirmation()
                ->visible(fn() => !file_exists($this->project->project_path)),
            
            Action::make('git_pull')
                ->label('Git Pull')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action('gitPull')
                ->requiresConfirmation()
                ->modalHeading('Pull from Git Repository')
                ->modalDescription('This will pull the latest changes from the remote repository. Are you sure?')
                ->modalSubmitActionLabel('Pull'),
            
            Action::make('git_push')
                ->label('Git Push')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->action('gitPush')
                ->requiresConfirmation()
                ->modalHeading('Push to Git Repository')
                ->modalDescription('This will push your local commits to the remote repository. Are you sure?')
                ->modalSubmitActionLabel('Push'),
            
            Action::make('edit_project')
                ->label('Edit Project')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->url(route('filament.admin.resources.projects.edit', $this->project)),
            
            Action::make('back_to_projects')
                ->label('Back to Projects')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(route('filament.admin.resources.projects.index')),
        ];
    }
    
    public function loadSession(): void
    {
        $session = $this->project->activeClaudeSession;
        
        if ($session) {
            $this->isRunning = $session->isRunning();
            $this->sessionStatus = $session->status;
            $this->messages = $session->conversation_history ?? [];
        } else {
            $this->isRunning = false;
            $this->sessionStatus = 'Not started';
            $this->messages = [];
        }
    }
    
    public function toggleClaude(): void
    {
        $manager = app(ClaudeProcessManager::class);
        
        try {
            if ($this->isRunning) {
                $session = $this->project->activeClaudeSession;
                if ($session) {
                    $manager->stopSession($session);
                    Notification::make()
                        ->title('Claude stopped')
                        ->success()
                        ->send();
                }
            } else {
                // Check if project path exists before starting Claude
                if (!file_exists($this->project->project_path)) {
                    $this->showPathNotFoundDialog();
                    return;
                }
                
                $session = $manager->startSession($this->project);
                
                Notification::make()
                    ->title('Claude started')
                    ->body('Claude is now running and ready to assist.')
                    ->success()
                    ->send();
                
                // Wait a moment for the process to fully initialize
                sleep(2);
                
                // Get initial output if any (should be ls output)
                $initialOutput = $manager->getOutput($session);
                if ($initialOutput) {
                    $session->addToHistory('assistant', $initialOutput);
                    
                    Notification::make()
                        ->title('Initial output received')
                        ->body(substr($initialOutput, 0, 100) . (strlen($initialOutput) > 100 ? '...' : ''))
                        ->info()
                        ->send();
                }
            }
            
            $this->loadSession();
        } catch (\Exception $e) {
            // Check if error is related to directory not existing
            if (str_contains($e->getMessage(), 'does not exist') || 
                str_contains($e->getMessage(), 'No such file or directory')) {
                $this->showPathNotFoundDialog();
            } else {
                Notification::make()
                    ->title('Error')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }
    
    protected function showPathNotFoundDialog(): void
    {
        $path = $this->project->project_path;
        
        Notification::make()
            ->title('Project directory does not exist')
            ->body("The directory '{$path}' does not exist. Click 'Create Directory' button above to create it, or edit the project to change the path.")
            ->warning()
            ->persistent()
            ->send();
    }
    
    protected function createProjectDirectory(string $path): void
    {
        try {
            if (!file_exists($path)) {
                if (mkdir($path, 0755, true)) {
                    Notification::make()
                        ->title('Directory created')
                        ->body("The directory '{$path}' has been created successfully.")
                        ->success()
                        ->send();
                    
                    // Try to start Claude again
                    $this->toggleClaude();
                } else {
                    throw new \Exception('Failed to create directory');
                }
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error creating directory')
                ->body("Failed to create directory: " . $e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function sendMessage(): void
    {
        if (empty($this->input)) {
            return;
        }
        
        if (!$this->isRunning) {
            Notification::make()
                ->title('Claude is not running')
                ->body('Please start Claude first')
                ->warning()
                ->send();
            return;
        }
        
        $manager = app(ClaudeProcessManager::class);
        $session = $this->project->activeClaudeSession;
        
        if (!$session) {
            Notification::make()
                ->title('No active session')
                ->danger()
                ->send();
            return;
        }
        
        try {
            // Add message to history immediately
            $session->addToHistory('user', $this->input);
            
            // Send command
            $manager->sendCommand($session, $this->input);
            
            // Clear input
            $messageToSend = $this->input;
            $this->input = '';
            
            // Wait a moment for response
            sleep(2);
            
            // Get output
            $output = $manager->getOutput($session);
            
            if ($output) {
                // Add output to history
                $session->addToHistory('assistant', $output);
                
                Notification::make()
                    ->title('Response received')
                    ->body(substr($output, 0, 100) . (strlen($output) > 100 ? '...' : ''))
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('No response yet')
                    ->body('The command was sent but no response received yet.')
                    ->info()
                    ->send();
            }
            
            // Reload messages
            $this->loadSession();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error sending message')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function clearHistory(): void
    {
        $session = $this->project->activeClaudeSession;
        if ($session) {
            $session->update(['conversation_history' => []]);
            $this->messages = [];
            
            Notification::make()
                ->title('History cleared')
                ->success()
                ->send();
        }
    }
    
    #[On('refresh-output')]
    public function refreshOutput(): void
    {
        if (!$this->isRunning) {
            return;
        }
        
        $manager = app(ClaudeProcessManager::class);
        $session = $this->project->activeClaudeSession;
        
        if ($session) {
            // Check if still running
            if (!$manager->isRunning($session)) {
                $this->isRunning = false;
                $this->sessionStatus = 'stopped';
                return;
            }
            
            // Get any new output
            $output = $manager->getOutput($session);
            if ($output && trim($output) !== '') {
                // Add the output to conversation history
                $session->addToHistory('assistant', $output);
                $this->loadSession();
                
                // Dispatch event to scroll chat to bottom
                $this->dispatch('refreshChat');
                
                // Log for debugging
                \Log::info('Refresh output added to history', [
                    'session_id' => $session->id,
                    'output_length' => strlen($output),
                    'output_preview' => substr($output, 0, 100)
                ]);
            }
        }
    }
    
    public function getFormSchema(): array
    {
        return [
            Textarea::make('input')
                ->label('Message')
                ->placeholder('Type your message to Claude...')
                ->rows(3)
                ->required(),
        ];
    }
    
    public function gitPull(): void
    {
        try {
            $projectPath = $this->project->path;
            
            if (!is_dir($projectPath)) {
                throw new \Exception('Project directory does not exist');
            }
            
            if (!is_dir($projectPath . '/.git')) {
                throw new \Exception('Project is not a Git repository');
            }
            
            // Log the operation
            SystemLog::info('Git pull started', [
                'project_id' => $this->project->id,
                'project_path' => $projectPath,
            ], 'git');
            
            // Execute git pull
            $result = Process::path($projectPath)
                ->run('git pull origin ' . ($this->project->git_branch ?? 'main'));
            
            if ($result->successful()) {
                $output = $result->output();
                
                SystemLog::success('Git pull completed', [
                    'project_id' => $this->project->id,
                    'output' => $output,
                ], 'git');
                
                Notification::make()
                    ->title('Git Pull Successful')
                    ->body($output ?: 'Already up to date.')
                    ->success()
                    ->send();
            } else {
                $error = $result->errorOutput();
                
                SystemLog::error('Git pull failed', [
                    'project_id' => $this->project->id,
                    'error' => $error,
                ], 'git');
                
                Notification::make()
                    ->title('Git Pull Failed')
                    ->body($error)
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            SystemLog::error('Git pull exception', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ], 'git');
            
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function gitPush(): void
    {
        try {
            $projectPath = $this->project->path;
            
            if (!is_dir($projectPath)) {
                throw new \Exception('Project directory does not exist');
            }
            
            if (!is_dir($projectPath . '/.git')) {
                throw new \Exception('Project is not a Git repository');
            }
            
            // Log the operation
            SystemLog::info('Git push started', [
                'project_id' => $this->project->id,
                'project_path' => $projectPath,
            ], 'git');
            
            // First check if there are any commits to push
            $statusResult = Process::path($projectPath)
                ->run('git status --porcelain --branch');
            
            if ($statusResult->failed()) {
                throw new \Exception('Failed to check git status: ' . $statusResult->errorOutput());
            }
            
            // Execute git push
            $result = Process::path($projectPath)
                ->run('git push origin ' . ($this->project->git_branch ?? 'main'));
            
            if ($result->successful()) {
                $output = $result->output();
                
                SystemLog::success('Git push completed', [
                    'project_id' => $this->project->id,
                    'output' => $output,
                ], 'git');
                
                Notification::make()
                    ->title('Git Push Successful')
                    ->body($output ?: 'Everything up-to-date.')
                    ->success()
                    ->send();
            } else {
                $error = $result->errorOutput();
                
                // Check if it's just "everything up-to-date"
                if (str_contains($error, 'Everything up-to-date')) {
                    Notification::make()
                        ->title('Git Push')
                        ->body('Everything up-to-date.')
                        ->success()
                        ->send();
                } else {
                    SystemLog::error('Git push failed', [
                        'project_id' => $this->project->id,
                        'error' => $error,
                    ], 'git');
                    
                    Notification::make()
                        ->title('Git Push Failed')
                        ->body($error)
                        ->danger()
                        ->send();
                }
            }
        } catch (\Exception $e) {
            SystemLog::error('Git push exception', [
                'project_id' => $this->project->id,
                'error' => $e->getMessage(),
            ], 'git');
            
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
}