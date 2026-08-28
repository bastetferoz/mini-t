<?php

namespace App\Filament\Resources\Printers\Pages;

use App\Filament\Resources\Printers\PrinterResource;
use App\Services\PrinterService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPrinter extends ViewRecord
{
    protected static string $resource = PrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('probe')
                ->label('Verificar ahora')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->visible(fn () => $this->record->isNetwork())
                ->action(function () {
                    $result = PrinterService::probe($this->record, 'manual');

                    if (! $result['online']) {
                        Notification::make()
                            ->title("{$this->record->name} no responde")
                            ->body("La IP {$this->record->ip} no respondió al ping.")
                            ->warning()
                            ->send();
                        return;
                    }

                    $body = 'En línea.';
                    if ($result['snmp']) {
                        $body .= ' SNMP OK.';
                        if ($result['page_count'] !== null) {
                            $body .= " Contador: {$result['page_count']} páginas.";
                        }
                    } else {
                        $body .= ' SNMP sin respuesta.';
                    }

                    Notification::make()
                        ->title('Verificación completada')
                        ->body($body)
                        ->success()
                        ->send();
                }),

            Action::make('manualCount')
                ->label('Cargar conteo')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => ! $this->record->isNetwork())
                ->schema([
                    TextInput::make('page_count')
                        ->label('Contador de páginas')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    DatePicker::make('read_at')
                        ->label('Fecha de la lectura')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $readAt = \Carbon\Carbon::parse($data['read_at']);

                    $this->record->readings()->create([
                        'page_count' => (int) $data['page_count'],
                        'read_at'    => $readAt,
                        'source'     => 'manual',
                    ]);

                    if (! $this->record->page_count_at || $readAt->gte($this->record->page_count_at)) {
                        $this->record->update([
                            'page_count'    => (int) $data['page_count'],
                            'page_count_at' => $readAt,
                        ]);
                    }

                    Notification::make()
                        ->title('Conteo cargado')
                        ->body("{$this->record->name}: {$data['page_count']} páginas ({$readAt->format('d/m/Y')}).")
                        ->success()
                        ->send();
                }),

            EditAction::make(),
        ];
    }
}
