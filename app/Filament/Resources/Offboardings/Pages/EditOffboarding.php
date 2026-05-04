<?php

namespace App\Filament\Resources\Offboardings\Pages;

use App\Filament\Resources\Offboardings\OffboardingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOffboarding extends EditRecord
{
    protected static string $resource = OffboardingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
