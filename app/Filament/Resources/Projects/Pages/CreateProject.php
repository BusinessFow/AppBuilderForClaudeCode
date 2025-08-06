<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Create directory if it doesn't exist
        if (isset($data['project_path']) && !file_exists($data['project_path'])) {
            mkdir($data['project_path'], 0755, true);
        }
        
        // Process JSON fields
        if (isset($data['claude_settings_json'])) {
            $data['claude_settings'] = json_decode($data['claude_settings_json'], true);
            unset($data['claude_settings_json']);
        }
        
        if (isset($data['local_settings_json'])) {
            $data['local_settings'] = json_decode($data['local_settings_json'], true);
            unset($data['local_settings_json']);
        }
        
        // Process CLAUDE.md template
        if (isset($data['claude_md'])) {
            $replacements = [
                '{project_description}' => $data['description'] ?? 'Project description',
                '{technologies}' => implode(', ', $data['technologies'] ?? []),
                '{framework}' => $data['framework'] ?? 'framework',
                '{test_command}' => isset($data['test_commands'][0]) ? $data['test_commands'][0] : 'test command',
                '{build_command}' => isset($data['build_commands'][0]) ? $data['build_commands'][0] : 'build command',
                '{lint_command}' => isset($data['lint_commands'][0]) ? $data['lint_commands'][0] : 'lint command',
                '{auto_commit_note}' => ($data['auto_commit'] ?? false) ? 'Auto-commit is enabled' : 'Auto-commit is disabled',
                '{auto_test_note}' => ($data['auto_test'] ?? false) ? 'Tests run automatically' : 'Tests must be run manually',
                '{tdd_note}' => ($data['tdd_mode'] ?? false) ? 'TDD mode is active' : 'TDD mode is not active',
            ];
            $data['claude_md'] = str_replace(array_keys($replacements), array_values($replacements), $data['claude_md']);
        }
        
        // Remove temporary fields
        unset($data['technology_preset']);
        
        return $data;
    }
}