<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('manage')
                ->label('Manage Settings')
                ->icon('heroicon-o-cog')
                ->url(fn () => SettingResource::getUrl('manage'))
                ->color('primary'),
            CreateAction::make(),
        ];
    }
}
