<?php

namespace App\Filament\Resources\Printers\Tables;

use App\Services\PrinterService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrintersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => $state === 'manual' ? 'Manual' : 'De red')
                    ->colors([
                        'info' => fn ($state) => $state === 'network',
                        'gray' => fn ($state) => $state === 'manual',
                    ]),

                TextColumn::make('ip')
                    ->label('IP')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('brand')
                    ->label('Marca')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('model')
                    ->label('Modelo')
                    ->searchable()
                    ->limit(30)
                    ->placeholder('—'),

                TextColumn::make('location')
                    ->label('Ubicación')
                    ->searchable()
                    ->placeholder('—'),

                BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'online'  => 'En línea',
                        'offline' => 'Desconectada',
                        default   => 'Desconocido',
                    })
                    ->colors([
                        'success' => fn ($state) => $state === 'online',
                        'danger'  => fn ($state) => $state === 'offline',
                        'gray'    => fn ($state) => $state === 'unknown',
                    ]),

                TextColumn::make('page_count')
                    ->label('Contador')
                    ->numeric()
                    ->placeholder('—'),

                TextColumn::make('last_seen_at')
                    ->label('Último chequeo')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->searchPlaceholder('Buscar por nombre, IP, marca, modelo o ubicación')
            ->defaultSort('name')
            ->recordActions([
                Action::make('probe')
                    ->label('Verificar')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->visible(fn ($record) => $record->isNetwork())
                    ->action(function ($record) {
                        $result = PrinterService::probe($record, 'manual');

                        if (! $result['online']) {
                            Notification::make()
                                ->title("{$record->name} no responde")
                                ->body("La IP {$record->ip} no respondió al ping.")
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
                            $body .= ' SNMP sin respuesta (verificá la community o que SNMP esté habilitado en la impresora).';
                        }

                        Notification::make()
                            ->title("{$record->name} verificada")
                            ->body($body)
                            ->success()
                            ->send();
                    }),

                Action::make('manualCount')
                    ->label('Cargar conteo')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn ($record) => ! $record->isNetwork())
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
                    ->action(function ($record, array $data) {
                        $readAt = \Carbon\Carbon::parse($data['read_at']);

                        $record->readings()->create([
                            'page_count' => (int) $data['page_count'],
                            'read_at'    => $readAt,
                            'source'     => 'manual',
                        ]);

                        // Actualizar el contador actual solo si esta lectura es la más reciente
                        if (! $record->page_count_at || $readAt->gte($record->page_count_at)) {
                            $record->update([
                                'page_count'    => (int) $data['page_count'],
                                'page_count_at' => $readAt,
                            ]);
                        }

                        Notification::make()
                            ->title('Conteo cargado')
                            ->body("{$record->name}: {$data['page_count']} páginas ({$readAt->format('d/m/Y')}).")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
