<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Pages\InvoiceBrowser;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('← Volver a carpetas')
                ->color('gray')
                ->url(InvoiceBrowser::getUrl()),

            CreateAction::make()
                ->label('Carga manual'),
        ];
    }
}
