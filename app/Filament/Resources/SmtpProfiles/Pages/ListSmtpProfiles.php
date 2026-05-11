<?php

namespace App\Filament\Resources\SmtpProfiles\Pages;

use App\Filament\Resources\SmtpProfiles\SmtpProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSmtpProfiles extends ListRecords
{
    protected static string $resource = SmtpProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
