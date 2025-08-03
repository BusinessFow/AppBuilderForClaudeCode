<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_access_projects_index_page()
    {
        $response = $this->get('/admin/projects');
        
        $response->assertStatus(200);
        $response->assertSee('Projects');
    }

    public function test_can_access_create_project_page()
    {
        $response = $this->get('/admin/projects/create');
        
        $response->assertStatus(200);
        $response->assertSee('Create Project');
    }

    public function test_can_create_project()
    {
        Livewire::test(\App\Filament\Resources\Projects\Pages\CreateProject::class)
            ->fillForm([
                'name' => 'Test Project',
                'url' => 'https://example.com',
                'login_url' => 'https://example.com/login',
                'username' => 'testuser',
                'password' => 'testpass',
                'max_depth' => 3,
                'status' => 'pending',
                'description' => 'Test description',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'url' => 'https://example.com',
            'login_url' => 'https://example.com/login',
            'username' => 'testuser',
            'max_depth' => 3,
            'status' => 'pending',
            'description' => 'Test description',
        ]);
    }

    public function test_validation_errors_when_creating_project_without_required_fields()
    {
        Livewire::test(\App\Filament\Resources\Projects\Pages\CreateProject::class)
            ->fillForm([
                'name' => '',
                'url' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name', 'url']);
    }

    public function test_can_edit_project()
    {
        $project = Project::factory()->create();

        Livewire::test(\App\Filament\Resources\Projects\Pages\EditProject::class, [
            'record' => $project->getRouteKey(),
        ])
            ->fillForm([
                'name' => 'Updated Project Name',
                'url' => 'https://updated.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'url' => 'https://updated.com',
        ]);
    }

    public function test_can_delete_project()
    {
        $project = Project::factory()->create();

        Livewire::test(\App\Filament\Resources\Projects\Pages\EditProject::class, [
            'record' => $project->getRouteKey(),
        ])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($project);
    }

    public function test_can_view_project_in_table()
    {
        $project = Project::factory()->create([
            'name' => 'Visible Project',
            'status' => 'completed',
        ]);

        Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
            ->assertCanSeeTableRecords([$project])
            ->assertSee('Visible Project')
            ->assertSee('completed');
    }

    public function test_can_filter_projects_by_status()
    {
        Project::factory()->create(['status' => 'pending']);
        Project::factory()->create(['status' => 'running']);
        Project::factory()->create(['status' => 'completed']);
        Project::factory()->create(['status' => 'failed']);

        Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
            ->filterTable('status', 'completed')
            ->assertCanSeeTableRecords(Project::where('status', 'completed')->get())
            ->assertCanNotSeeTableRecords(Project::where('status', '!=', 'completed')->get());
    }

    public function test_can_search_projects()
    {
        Project::factory()->create(['name' => 'Searchable Project']);
        Project::factory()->create(['name' => 'Another Project']);

        Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
            ->searchTable('Searchable')
            ->assertCanSeeTableRecords(Project::where('name', 'like', '%Searchable%')->get())
            ->assertCanNotSeeTableRecords(Project::where('name', 'not like', '%Searchable%')->get());
    }

    public function test_url_validation()
    {
        Livewire::test(\App\Filament\Resources\Projects\Pages\CreateProject::class)
            ->fillForm([
                'name' => 'Test Project',
                'url' => 'not-a-valid-url',
            ])
            ->call('create')
            ->assertHasFormErrors(['url']);
    }

    public function test_max_depth_validation()
    {
        Livewire::test(\App\Filament\Resources\Projects\Pages\CreateProject::class)
            ->fillForm([
                'name' => 'Test Project',
                'url' => 'https://example.com',
                'max_depth' => 15, // Max is 10
            ])
            ->call('create')
            ->assertHasFormErrors(['max_depth']);
    }
}