<?php
namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Services\ExchangeRateService;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function afterSave(): void
    {
        if ($this->record->currency === 'ARS') {
            $date = $this->record->invoice_date->format('Y-m-d');
            $rate = ExchangeRateService::getBnaRate($date);

            if ($rate) {
                $this->record->update([
                    'exchange_rate' => $rate,
                    'amount_usd'    => round($this->record->amount / $rate, 2),
                ]);
            }
        }

        if ($this->record->currency === 'USD') {
            $this->record->update([
                'exchange_rate' => 1,
                'amount_usd'    => $this->record->amount,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}