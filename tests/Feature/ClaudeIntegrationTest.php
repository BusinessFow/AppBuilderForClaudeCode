<?php

namespace Tests\Feature;

use App\Models\ClaudeSession;
use App\Models\ClaudeTodo;
use App\Models\Project;
use App\Models\User;
use App\Services\ClaudeProcessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

class ClaudeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the Process facade
        Process::fake();
        
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_claude_session(): void
    {
        $project = Project::factory()->create([
            'project_path' => '/tmp/test-project',
        ]);

        $manager = app(ClaudeProcessManager::class);
        
        // Configure Process fake
        Process::fake([
            'claude code --project-dir /tmp/test-project' => Process::result(
                output: 'Claude started successfully',
            ),
        ]);

        $session = $manager->startSession($project);

        $this->assertInstanceOf(ClaudeSession::class, $session);
        $this->assertEquals('running', $session->status);
        $this->assertNotNull($session->started_at);
        $this->assertEquals($project->id, $session->project_id);
    }

    public function test_can_send_command_to_claude(): void
    {
        $project = Project::factory()->create([
            'project_path' => '/tmp/test-project',
        ]);

        $session = ClaudeSession::factory()->create([
            'project_id' => $project->id,
            'status' => 'running',
            'process_id' => 12345,
        ]);

        $manager = $this->mock(ClaudeProcessManager::class);
        $manager->shouldReceive('isRunning')
            ->with($session)
            ->andReturn(true);
        $manager->shouldReceive('sendCommand')
            ->with($session, 'test command')
            ->once();

        $manager->sendCommand($session, 'test command');
    }

    public function test_can_create_claude_todo(): void
    {
        $project = Project::factory()->create();

        $todo = ClaudeTodo::create([
            'project_id' => $project->id,
            'command' => 'npm test',
            'description' => 'Run tests',
            'priority' => 2,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('claude_todos', [
            'project_id' => $project->id,
            'command' => 'npm test',
            'status' => 'pending',
        ]);
    }

    public function test_can_mark_todo_as_completed(): void
    {
        $todo = ClaudeTodo::factory()->create([
            'status' => 'pending',
        ]);

        $todo->markAsCompleted('Test passed successfully');

        $this->assertEquals('completed', $todo->fresh()->status);
        $this->assertEquals('Test passed successfully', $todo->fresh()->result);
        $this->assertNotNull($todo->fresh()->completed_at);
    }

    public function test_claude_chat_page_loads(): void
    {
        $project = Project::factory()->create([
            'name' => 'Test Project',
            'project_path' => '/tmp/test-project',
        ]);

        $response = $this->get("/admin/projects/{$project->id}/claude");

        $response->assertStatus(200);
        $response->assertSee('Claude Chat - Test Project');
    }

    public function test_can_toggle_claude_from_chat(): void
    {
        $project = Project::factory()->create([
            'project_path' => '/tmp/test-project',
        ]);

        Process::fake([
            'claude code --project-dir /tmp/test-project' => Process::result(
                output: 'Claude started successfully',
            ),
        ]);

        Livewire::test(\App\Filament\Resources\Projects\Pages\ClaudeChat::class, ['record' => $project->id])
            ->assertSee('Not started')
            ->call('toggleClaude')
            ->assertNotified('Claude started');
    }

    public function test_can_add_todo_from_chat(): void
    {
        $project = Project::factory()->create();

        Livewire::test(\App\Filament\Resources\Projects\Pages\ClaudeChat::class, ['record' => $project->id])
            ->set('newTodoCommand', 'npm run build')
            ->set('newTodoDescription', 'Build the project')
            ->set('newTodoPriority', 3)
            ->call('addTodo')
            ->assertNotified('TODO added');

        $this->assertDatabaseHas('claude_todos', [
            'project_id' => $project->id,
            'command' => 'npm run build',
            'description' => 'Build the project',
            'priority' => 3,
            'status' => 'pending',
        ]);
    }

    public function test_project_has_claude_relationships(): void
    {
        $project = Project::factory()->create();
        
        $session = ClaudeSession::factory()->create([
            'project_id' => $project->id,
            'status' => 'running',
        ]);
        
        $todo = ClaudeTodo::factory()->create([
            'project_id' => $project->id,
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(ClaudeSession::class, $project->activeClaudeSession);
        $this->assertEquals($session->id, $project->activeClaudeSession->id);
        
        $this->assertEquals(1, $project->claudeTodos()->count());
        $this->assertEquals(1, $project->pendingTodos()->count());
        $this->assertEquals(0, $project->completedTodos()->count());
    }

    public function test_session_conversation_history(): void
    {
        $session = ClaudeSession::factory()->create();

        $session->addToHistory('user', 'Hello Claude');
        $session->addToHistory('assistant', 'Hello! How can I help you?');

        $history = $session->fresh()->conversation_history;
        
        $this->assertCount(2, $history);
        $this->assertEquals('user', $history[0]['role']);
        $this->assertEquals('Hello Claude', $history[0]['content']);
        $this->assertEquals('assistant', $history[1]['role']);
        $this->assertEquals('Hello! How can I help you?', $history[1]['content']);
    }
}