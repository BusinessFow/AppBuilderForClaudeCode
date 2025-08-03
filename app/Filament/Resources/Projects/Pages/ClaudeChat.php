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
                $session = $manager->startSession($this->project);
                Notification::make()
                    ->title('Claude started')
                    ->success()
                    ->send();
            }
            
            $this->loadSession();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
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
            // Send command
            $manager->sendCommand($session, $this->input);
            
            // Clear input
            $this->input = '';
            
            // Wait a moment for response
            sleep(1);
            
            // Get output
            $output = $manager->getOutput($session);
            
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
            if ($output) {
                $this->loadSession();
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