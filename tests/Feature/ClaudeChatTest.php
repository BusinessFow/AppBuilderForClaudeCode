<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaudeChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_claude_chat_page_renders(): void
    {
        $project = Project::factory()->create([
            'name' => 'Test Project',
            'project_path' => '/tmp/test-project',
        ]);

        $response = $this->get("/admin/projects/{$project->id}/claude");

        $response->assertStatus(200);
        $response->assertSee('Claude Console');
        $response->assertSee('TODO Queue');
    }

    public function test_projects_table_shows_claude_status(): void
    {
        Project::factory()->create([
            'name' => 'Test Project with Claude',
            'project_path' => '/tmp/test-project',
        ]);

        $response = $this->get('/admin/projects');

        $response->assertStatus(200);
        $response->assertSee('Not started'); // Claude status
        $response->assertSee('0 / 0'); // TODOs progress
    }

    public function test_project_form_has_claude_configuration(): void
    {
        $response = $this->get('/admin/projects/create');

        $response->assertStatus(200);
        $response->assertSee('Claude Configuration');
        $response->assertSee('CLAUDE.md Content');
        $response->assertSee('Local Settings (settings.local.json)');
    }

    public function test_can_create_project_with_claude_settings(): void
    {
        $response = $this->post('/admin/projects', [
            'data' => [
                'name' => 'Claude Test Project',
                'description' => 'A project to test Claude integration',
                'project_path' => '/tmp/claude-test',
                'project_type' => 'web',
                'auto_commit' => true,
                'auto_test' => true,
                'tdd_mode' => false,
                'claude_md' => '# Claude Project\nThis is a test project.',
                'local_settings' => [
                    'codeEditor' => [
                        'automaticCommits' => true,
                        'testOnSave' => true,
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('projects', [
            'name' => 'Claude Test Project',
            'project_path' => '/tmp/claude-test',
            'auto_commit' => true,
            'auto_test' => true,
            'tdd_mode' => false,
        ]);
    }
}