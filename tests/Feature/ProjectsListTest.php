<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsListTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        // Set up Filament panel
        Filament::serving(function () {
            Filament::registerPanel(
                \Filament\Panel::make()
                    ->id('admin')
                    ->path('admin')
                    ->authGuard('web')
            );
        });
    }

    public function test_can_render_projects_list()
    {
        // Create some test projects
        $projects = Project::factory()->count(3)->create();
        
        // Test the Livewire component
        Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($projects);
    }

    public function test_can_see_project_details_in_table()
    {
        $project = Project::factory()->create([
            'name' => 'Test Project',
            'project_path' => '/home/user/test-project',
            'framework' => 'Laravel',
            'language' => 'PHP',
            'auto_commit' => true,
            'auto_test' => false,
            'tdd_mode' => true,
            'status' => 'active',
        ]);
        
        Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
            ->assertCanSeeTableRecords([$project])
            ->assertSee('Test Project')
            ->assertSee('/home/user/test-project')
            ->assertSee('Laravel')
            ->assertSee('PHP');
    }

    public function test_can_filter_projects_by_type()
    {
        Project::factory()->create(['project_type' => 'web']);
        Project::factory()->create(['project_type' => 'api']);
        Project::factory()->create(['project_type' => 'cli']);
        
        Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
            ->filterTable('project_type', 'web')
            ->assertCanSeeTableRecords(Project::where('project_type', 'web')->get())
            ->assertCanNotSeeTableRecords(Project::where('project_type', '!=', 'web')->get());
    }

    public function test_can_filter_projects_by_status()
    {
        Project::factory()->create(['status' => 'active']);
        Project::factory()->create(['status' => 'paused']);
        Project::factory()->create(['status' => 'inactive']);
        
        Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
            ->filterTable('status', 'active')
            ->assertCanSeeTableRecords(Project::where('status', 'active')->get())
            ->assertCanNotSeeTableRecords(Project::where('status', '!=', 'active')->get());
    }

    public function test_can_search_projects()
    {
        Project::factory()->create(['name' => 'Laravel API']);
        Project::factory()->create(['name' => 'React Dashboard']);
        Project::factory()->create(['framework' => 'Django']);
        
        Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class)
            ->searchTable('Laravel')
            ->assertCanSeeTableRecords(Project::where('name', 'like', '%Laravel%')->get())
            ->assertCanNotSeeTableRecords(Project::where('name', 'not like', '%Laravel%')->get());
    }

    public function test_bulk_actions_are_available()
    {
        Project::factory()->count(3)->create();
        
        $component = Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class);
        
        // Check that bulk actions are defined
        $component->assertTableBulkActionExists('delete');
        $component->assertTableBulkActionExists('activate');
        $component->assertTableBulkActionExists('pause');
    }

    public function test_table_actions_are_available()
    {
        $project = Project::factory()->create();
        
        $component = Livewire::test(\App\Filament\Resources\Projects\Pages\ListProjects::class);
        
        // Check that table actions exist
        $component->assertTableActionExists('view');
        $component->assertTableActionExists('edit');
        $component->assertTableActionExists('toggle_status');
        $component->assertTableActionExists('open_in_claude');
    }
}