<?php

namespace App\Filament\Resources\Offboardings\Pages;

use App\Filament\Resources\Offboardings\OffboardingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOffboarding extends ViewRecord
{
    protected static string $resource = OffboardingResource::class;

    // ✅ NO static
    protected string $view = 'filament.resources.offboardings.pages.view-offboarding';
}