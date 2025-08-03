<?php

namespace Tests\Unit;

use App\Filament\Resources\Projects\Schemas\ProjectForm;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Tests\TestCase;

class ProjectFormTest extends TestCase
{
    public function test_form_has_correct_structure()
    {
        $schema = Schema::make();
        $form = ProjectForm::configure($schema);
        
        $components = $form->getComponents();
        
        // Should have 3 sections + hidden fields
        $this->assertGreaterThan(3, count($components));
        
        // Count sections
        $sections = array_filter($components, fn($c) => $c instanceof Section);
        $this->assertCount(3, $sections);
        
        // Count hidden fields
        $hiddenFields = array_filter($components, fn($c) => $c instanceof Hidden);
        $this->assertCount(7, $hiddenFields);
    }

    public function test_project_details_section_has_correct_fields()
    {
        $schema = Schema::make();
        $form = ProjectForm::configure($schema);
        
        $sections = array_filter($form->getComponents(), fn($c) => $c instanceof Section);
        $projectDetailsSection = array_values($sections)[0];
        
        $this->assertEquals('Project Details', $projectDetailsSection->getHeading());
        $this->assertEquals(2, $projectDetailsSection->getColumns());
        
        $fields = $projectDetailsSection->getChildComponents();
        $this->assertCount(4, $fields);
        
        // Check field names
        $fieldNames = array_map(fn($f) => $f->getName(), $fields);
        $this->assertContains('name', $fieldNames);
        $this->assertContains('url', $fieldNames);
        $this->assertContains('login_url', $fieldNames);
        $this->assertContains('max_depth', $fieldNames);
    }

    public function test_login_credentials_section_has_correct_fields()
    {
        $schema = Schema::make();
        $form = ProjectForm::configure($schema);
        
        $sections = array_filter($form->getComponents(), fn($c) => $c instanceof Section);
        $loginSection = array_values($sections)[1];
        
        $this->assertEquals('Login Credentials', $loginSection->getHeading());
        $this->assertEquals(2, $loginSection->getColumns());
        
        $fields = $loginSection->getChildComponents();
        $this->assertCount(3, $fields);
        
        // Check field names
        $fieldNames = array_map(fn($f) => $f->getName(), $fields);
        $this->assertContains('username', $fieldNames);
        $this->assertContains('password', $fieldNames);
        $this->assertContains('login_data', $fieldNames);
    }

    public function test_status_results_section_has_correct_fields()
    {
        $schema = Schema::make();
        $form = ProjectForm::configure($schema);
        
        $sections = array_filter($form->getComponents(), fn($c) => $c instanceof Section);
        $statusSection = array_values($sections)[2];
        
        $this->assertEquals('Status & Results', $statusSection->getHeading());
        $this->assertEquals(2, $statusSection->getColumns());
        
        $fields = $statusSection->getChildComponents();
        $this->assertCount(2, $fields);
        
        // Check field names
        $fieldNames = array_map(fn($f) => $f->getName(), $fields);
        $this->assertContains('status', $fieldNames);
        $this->assertContains('description', $fieldNames);
    }

    public function test_required_fields_are_marked()
    {
        $schema = Schema::make();
        $form = ProjectForm::configure($schema);
        
        $sections = array_filter($form->getComponents(), fn($c) => $c instanceof Section);
        $projectDetailsSection = array_values($sections)[0];
        
        $fields = $projectDetailsSection->getChildComponents();
        
        // Find name and url fields
        $nameField = array_values(array_filter($fields, fn($f) => $f->getName() === 'name'))[0];
        $urlField = array_values(array_filter($fields, fn($f) => $f->getName() === 'url'))[0];
        
        $this->assertTrue($nameField->isRequired());
        $this->assertTrue($urlField->isRequired());
    }

    public function test_field_constraints()
    {
        $schema = Schema::make();
        $form = ProjectForm::configure($schema);
        
        $sections = array_filter($form->getComponents(), fn($c) => $c instanceof Section);
        $projectDetailsSection = array_values($sections)[0];
        
        $fields = $projectDetailsSection->getChildComponents();
        
        // Check max_depth field constraints
        $maxDepthField = array_values(array_filter($fields, fn($f) => $f->getName() === 'max_depth'))[0];
        
        $this->assertEquals(3, $maxDepthField->getDefault());
        $this->assertEquals(1, $maxDepthField->getMinValue());
        $this->assertEquals(10, $maxDepthField->getMaxValue());
    }

    public function test_hidden_fields_exist()
    {
        $schema = Schema::make();
        $form = ProjectForm::configure($schema);
        
        $hiddenFields = array_filter($form->getComponents(), fn($c) => $c instanceof Hidden);
        $hiddenFieldNames = array_map(fn($f) => $f->getName(), $hiddenFields);
        
        $expectedHiddenFields = [
            'model_schema',
            'scraped_urls',
            'screenshots',
            'form_data',
            'api_requests',
            'started_at',
            'completed_at',
        ];
        
        foreach ($expectedHiddenFields as $fieldName) {
            $this->assertContains($fieldName, $hiddenFieldNames);
        }
    }

    public function test_status_field_has_correct_options()
    {
        $schema = Schema::make();
        $form = ProjectForm::configure($schema);
        
        $sections = array_filter($form->getComponents(), fn($c) => $c instanceof Section);
        $statusSection = array_values($sections)[2];
        
        $fields = $statusSection->getChildComponents();
        $statusField = array_values(array_filter($fields, fn($f) => $f->getName() === 'status'))[0];
        
        $options = $statusField->getOptions();
        
        $this->assertArrayHasKey('pending', $options);
        $this->assertArrayHasKey('running', $options);
        $this->assertArrayHasKey('completed', $options);
        $this->assertArrayHasKey('failed', $options);
        
        $this->assertEquals('pending', $statusField->getDefault());
    }
}