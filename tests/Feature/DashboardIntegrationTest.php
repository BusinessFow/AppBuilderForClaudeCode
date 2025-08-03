<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create();
    }

    public function test_dashboard_loads_without_errors(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');
        
        $response->assertSuccessful();
        $response->assertSessionHasNoErrors();
        $response->assertViewIs('filament.pages.dashboard');
        
        // Check that the page has basic structure
        $response->assertSee('Dashboard', false);
        $response->assertSee('Welcome to AppBuilder', false);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_dashboard_contains_widget_containers(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');
        
        $response->assertSuccessful();
        
        // Check for Livewire components
        $content = $response->getContent();
        $this->assertStringContainsString('wire:id', $content);
        $this->assertStringContainsString('fi-wi-widget', $content);
    }
}