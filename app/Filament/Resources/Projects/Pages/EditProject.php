<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert JSON fields to strings for editing
        if (isset($data['claude_settings'])) {
            $data['claude_settings_json'] = json_encode($data['claude_settings'], JSON_PRETTY_PRINT);
        }
        
        if (isset($data['local_settings'])) {
            $data['local_settings_json'] = json_encode($data['local_settings'], JSON_PRETTY_PRINT);
        }
        
        return $data;
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Process JSON fields
        if (isset($data['claude_settings_json'])) {
            $data['claude_settings'] = json_decode($data['claude_settings_json'], true);
            unset($data['claude_settings_json']);
        }
        
        if (isset($data['local_settings_json'])) {
            $data['local_settings'] = json_decode($data['local_settings_json'], true);
            unset($data['local_settings_json']);
        }
        
        // Remove temporary fields
        unset($data['technology_preset']);
        
        return $data;
    }
}