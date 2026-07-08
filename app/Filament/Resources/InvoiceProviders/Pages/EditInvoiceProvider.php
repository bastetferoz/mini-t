<?php

namespace App\Filament\Resources\InvoiceProviders\Pages;

use App\Filament\Resources\InvoiceProviders\InvoiceProviderResource;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceProvider extends EditRecord
{
    protected static string $resource = InvoiceProviderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
