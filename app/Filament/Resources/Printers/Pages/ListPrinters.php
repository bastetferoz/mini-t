<?php

namespace App\Filament\Resources\Printers\Pages;

use App\Filament\Resources\Printers\PrinterResource;
use App\Models\Printer;
use App\Services\PrinterService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPrinters extends ListRecords
{
    protected static string $resource = PrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Agregar impresora'),

            Action::make('probeAll')
                ->label('Verificar todas')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(function () {
                    $printers = Printer::where('type', 'network')->get();
                    $online = 0;

                    foreach ($printers as $printer) {
                        $result = PrinterService::probe($printer, 'manual');
                        if ($result['online']) {
                            $online++;
                        }
                    }

                    Notification::make()
                        ->title('Verificación completada')
                        ->body("{$online} de {$printers->count()} impresora(s) de red en línea.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
