<?php

namespace Tests\Unit;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Authenticate as admin
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Set up Filament
        Filament::serving(function () {
            Filament::registerPanel(
                \Filament\Panel::make()
                    ->id('admin')
                    ->path('admin')
            );
        });
    }

    public function test_project_resource_has_correct_model()
    {
        $this->assertEquals(Project::class, ProjectResource::getModel());
    }

    public function test_project_resource_has_navigation_icon()
    {
        $this->assertNotNull(ProjectResource::getNavigationIcon());
    }

    public function test_project_resource_has_required_pages()
    {
        $pages = ProjectResource::getPages();
        
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_project_resource_form_has_required_fields()
    {
        $form = ProjectResource::form(\Filament\Schemas\Schema::make());
        $components = $form->getComponents();
        
        $this->assertNotEmpty($components);
        
        // Check for required sections
        $sections = array_filter($components, function ($component) {
            return $component instanceof \Filament\Schemas\Components\Section;
        });
        
        $this->assertCount(3, $sections, 'Form should have 3 sections');
    }

    public function test_project_resource_table_configuration()
    {
        $table = ProjectResource::table(\Filament\Tables\Table::make());
        
        $this->assertNotNull($table);
    }

    public function test_project_resource_relations()
    {
        $relations = ProjectResource::getRelations();
        
        $this->assertIsArray($relations);
    }
}