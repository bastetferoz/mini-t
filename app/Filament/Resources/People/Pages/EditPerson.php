<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

use App\Models\Assignment;
use App\Models\Asset;

class EditPerson extends EditRecord
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [

            

            // 🔥 ACCIONES ORIGINALES (IMPORTANTE)
            ViewAction::make()
                 ->label('Volver'),
            DeleteAction::make()
                 ->label('Borrar Agente'),
        ];
    }
}