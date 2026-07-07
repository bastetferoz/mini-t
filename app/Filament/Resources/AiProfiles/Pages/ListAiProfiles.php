<?php

namespace App\Filament\Resources\AiProfiles\Pages;

use App\Filament\Resources\AiProfiles\AiProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiProfiles extends ListRecords
{
    protected static string $resource = AiProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo perfil de IA'),
        ];
    }
}
