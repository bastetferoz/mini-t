<?php

namespace App\Filament\Resources\InvoiceProviders\Pages;

use App\Filament\Resources\InvoiceProviders\InvoiceProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoiceProvider extends CreateRecord
{
    protected static string $resource = InvoiceProviderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
