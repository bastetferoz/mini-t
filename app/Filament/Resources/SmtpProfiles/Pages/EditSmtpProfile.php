<?php

namespace App\Filament\Resources\SmtpProfiles\Pages;

use App\Filament\Resources\SmtpProfiles\SmtpProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSmtpProfile extends EditRecord
{
    protected static string $resource = SmtpProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
