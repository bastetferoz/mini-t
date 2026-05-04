<?php

namespace App\Filament\Resources\Offboardings\Pages;

use App\Filament\Resources\Offboardings\OffboardingResource;
use Filament\Resources\Pages\ListRecords;

class ListOffboardings extends ListRecords
{
    protected static string $resource = OffboardingResource::class;
}