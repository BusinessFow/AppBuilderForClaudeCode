<?php

namespace Tests\Unit;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_can_be_created()
    {
        $project = Project::create([
            'name' => 'Test Project',
            'url' => 'https://example.com',
            'login_url' => 'https://example.com/login',
            'username' => 'testuser',
            'password' => 'testpass',
            'max_depth' => 5,
            'status' => 'pending', // Explicitly set status
        ]);

        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'url' => 'https://example.com',
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $project->status);
        $this->assertEquals(5, $project->max_depth);
    }

    public function test_project_casts_json_fields()
    {
        $loginData = ['csrf_token' => 'abc123'];
        $scrapedUrls = ['https://example.com/page1', 'https://example.com/page2'];

        $project = Project::create([
            'name' => 'Test Project',
            'url' => 'https://example.com',
            'login_data' => $loginData,
            'scraped_urls' => $scrapedUrls,
        ]);

        $this->assertEquals($loginData, $project->login_data);
        $this->assertEquals($scrapedUrls, $project->scraped_urls);
        $this->assertIsArray($project->login_data);
        $this->assertIsArray($project->scraped_urls);
    }

    public function test_project_hides_password()
    {
        $project = Project::create([
            'name' => 'Test Project',
            'url' => 'https://example.com',
            'password' => 'secret123',
        ]);

        $array = $project->toArray();
        $this->assertArrayNotHasKey('password', $array);
    }

    public function test_project_status_enum_values()
    {
        $project = new Project();
        
        $project->status = 'pending';
        $this->assertEquals('pending', $project->status);
        
        $project->status = 'running';
        $this->assertEquals('running', $project->status);
        
        $project->status = 'completed';
        $this->assertEquals('completed', $project->status);
        
        $project->status = 'failed';
        $this->assertEquals('failed', $project->status);
    }

    public function test_project_datetime_casts()
    {
        $project = Project::create([
            'name' => 'Test Project',
            'url' => 'https://example.com',
            'started_at' => now(),
            'completed_at' => now()->addMinutes(30),
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $project->started_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $project->completed_at);
    }
}