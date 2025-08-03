<?php

namespace Tests\Feature;

use App\Models\ClaudeSession;
use App\Models\ClaudeTodo;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleClaudeTest extends TestCase
{
    use RefreshDatabase;

    public function test_claude_models_exist(): void
    {
        $this->assertTrue(class_exists(ClaudeSession::class));
        $this->assertTrue(class_exists(ClaudeTodo::class));
    }

    public function test_project_has_claude_relationships(): void
    {
        $project = Project::factory()->create();
        
        $this->assertNotNull($project->claudeSessions());
        $this->assertNotNull($project->activeClaudeSession());
        $this->assertNotNull($project->claudeTodos());
        $this->assertNotNull($project->pendingTodos());
        $this->assertNotNull($project->completedTodos());
    }

    public function test_claude_session_can_be_created(): void
    {
        $project = Project::factory()->create();
        
        $session = ClaudeSession::create([
            'project_id' => $project->id,
            'status' => 'idle',
            'conversation_history' => [],
        ]);

        $this->assertDatabaseHas('claude_sessions', [
            'project_id' => $project->id,
            'status' => 'idle',
        ]);
    }

    public function test_claude_todo_can_be_created(): void
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

    public function test_project_form_includes_claude_fields(): void
    {
        $project = Project::factory()->create([
            'auto_commit' => true,
            'auto_test' => false,
            'tdd_mode' => true,
            'claude_md' => '# Test Project',
            'local_settings' => ['test' => true],
        ]);

        $this->assertTrue($project->auto_commit);
        $this->assertFalse($project->auto_test);
        $this->assertTrue($project->tdd_mode);
        $this->assertEquals('# Test Project', $project->claude_md);
        $this->assertEquals(['test' => true], $project->local_settings);
    }

    public function test_claude_api_routes_exist(): void
    {
        $routes = collect(\Route::getRoutes())->map(fn($route) => $route->uri());
        
        $this->assertTrue($routes->contains('api/projects/{project}/claude/session'));
        $this->assertTrue($routes->contains('api/projects/{project}/claude/command'));
        $this->assertTrue($routes->contains('api/projects/{project}/claude/output'));
        $this->assertTrue($routes->contains('api/projects/{project}/claude/stream'));
    }
}