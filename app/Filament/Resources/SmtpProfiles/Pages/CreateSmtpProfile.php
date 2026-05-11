<?php

namespace App\Filament\Resources\SmtpProfiles\Pages;

use App\Filament\Resources\SmtpProfiles\SmtpProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSmtpProfile extends CreateRecord
{
    protected static string $resource = SmtpProfileResource::class;
}
