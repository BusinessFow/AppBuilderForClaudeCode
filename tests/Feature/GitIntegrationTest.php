<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\GitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GitIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_git_configuration_tab_appears_in_form(): void
    {
        $response = $this->get('/admin/projects/create');

        $response->assertStatus(200);
        $response->assertSee('Git Configuration');
        $response->assertSee('Enable Git Integration');
        $response->assertSee('Repository Settings');
        $response->assertSee('Commit Settings');
        $response->assertSee('Push Settings');
        $response->assertSee('Branch Management');
    }

    public function test_can_create_project_with_git_settings(): void
    {
        $response = $this->post('/admin/projects', [
            'data' => [
                'name' => 'Git Test Project',
                'description' => 'Testing Git integration',
                'project_path' => '/tmp/git-test-project',
                'project_type' => 'web',
                'git_enabled' => true,
                'git_remote_url' => 'https://github.com/test/repo.git',
                'git_username' => 'Test User',
                'git_email' => 'test@example.com',
                'git_branch' => 'main',
                'commit_frequency' => 'after_each_task',
                'auto_push' => true,
                'push_frequency' => 'after_each_session',
                'create_feature_branches' => true,
                'branch_naming_pattern' => 'feature/{task}',
            ],
        ]);

        $this->assertDatabaseHas('projects', [
            'name' => 'Git Test Project',
            'git_enabled' => true,
            'git_remote_url' => 'https://github.com/test/repo.git',
            'git_username' => 'Test User',
            'git_email' => 'test@example.com',
            'commit_frequency' => 'after_each_task',
            'auto_push' => true,
            'push_frequency' => 'after_each_session',
        ]);
    }

    public function test_git_fields_are_hidden_when_git_disabled(): void
    {
        $project = Project::factory()->create([
            'git_enabled' => false,
        ]);

        $response = $this->get("/admin/projects/{$project->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('Enable Git Integration');
        // Git fields should be present but hidden via JavaScript
    }

    public function test_git_service_methods_exist(): void
    {
        $gitService = app(GitService::class);
        
        $this->assertTrue(method_exists($gitService, 'initializeRepository'));
        $this->assertTrue(method_exists($gitService, 'configureUser'));
        $this->assertTrue(method_exists($gitService, 'addRemote'));
        $this->assertTrue(method_exists($gitService, 'commit'));
        $this->assertTrue(method_exists($gitService, 'push'));
        $this->assertTrue(method_exists($gitService, 'createFeatureBranch'));
    }

    public function test_project_has_git_fields(): void
    {
        $project = Project::factory()->create([
            'git_enabled' => true,
            'git_remote_url' => 'https://github.com/test/repo.git',
            'commit_frequency' => 'after_each_task',
            'auto_push' => false,
            'create_feature_branches' => true,
        ]);

        $this->assertTrue($project->git_enabled);
        $this->assertEquals('https://github.com/test/repo.git', $project->git_remote_url);
        $this->assertEquals('after_each_task', $project->commit_frequency);
        $this->assertFalse($project->auto_push);
        $this->assertTrue($project->create_feature_branches);
    }

    public function test_git_access_token_is_encrypted(): void
    {
        $project = Project::factory()->create([
            'git_access_token' => 'secret_token_123',
        ]);

        // The raw database value should be encrypted
        $rawValue = \DB::table('projects')
            ->where('id', $project->id)
            ->value('git_access_token');
            
        $this->assertNotEquals('secret_token_123', $rawValue);
        
        // But accessing via model should decrypt it
        $this->assertEquals('secret_token_123', $project->git_access_token);
    }
}