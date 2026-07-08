<?php

namespace App\Filament\Resources\InvoiceProviders\Pages;

use App\Filament\Resources\InvoiceProviders\InvoiceProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceProviders extends ListRecords
{
    protected static string $resource = InvoiceProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo proveedor'),
        ];
    }
}
