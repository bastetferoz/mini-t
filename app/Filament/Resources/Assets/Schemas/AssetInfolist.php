<?php

namespace App\Filament\Resources\Assets\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Información del equipo')
                ->icon('heroicon-o-computer-desktop')
                ->schema([
                    TextEntry::make('device')->label('Dispositivo'),
                    TextEntry::make('brand')->label('Marca'),
                    TextEntry::make('model')->label('Modelo'),
                    TextEntry::make('cpu')->label('CPU'),
                    TextEntry::make('ram')->label('RAM'),
                    TextEntry::make('disk')->label('Disco'),
                    TextEntry::make('serial')->label('Nº Serie'),
                ])
                ->columns(3),

            Section::make('Estado actual')
                ->icon('heroicon-o-signal')
                ->schema([
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->size('lg')
                        ->color(fn ($state) => match ($state) {
                            'available' => 'success',
                            'assigned' => 'primary',
                            'in_transit' => 'warning',
                            'in_return' => 'warning',
                            'retired' => 'danger',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'available' => 'Disponible',
                            'assigned' => 'En uso',
                            'in_transit' => 'En devolución',
                            'in_return' => 'En devolución',
                            'retired' => 'Dado de baja',
                            default => $state,
                        }),

                    TextEntry::make('assignments.person.name')
                        ->label('Asignado a')
                        ->badge()
                        ->icon('heroicon-o-user')
                        ->color('info')
                        ->visible(fn ($record) => $record->status === 'assigned')
                        ->url(fn ($record) => $record->assignments->first()?->person_id
                            ? \App\Filament\Resources\People\PersonResource::getUrl('view', ['record' => $record->assignments->first()->person_id])
                            : null
                        ),

                    TextEntry::make('notes')
                        ->label('Observaciones')
                        ->visible(fn ($record) => ! empty($record->notes)),
                ])
                ->columns(3),

            Section::make('Historial')
                ->icon('heroicon-o-clock')
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('histories')
                        ->label('')
                        ->schema([
                            TextEntry::make('person.name')
                                ->label('Usuario')
                                ->formatStateUsing(fn ($state) => $state ? "Ex {$state}" : 'IT'),

                            TextEntry::make('action')
                                ->label('Acción')
                                ->badge()
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'assignment'   => 'Asignación',
                                    'upgrade'      => 'Upgrade',
                                    'failure'      => 'Avería',
                                    'replacement'  => 'Reemplazo preventivo',
                                    'loan'         => 'Préstamo',
                                    'return'       => 'Devuelto',
                                    'reactivated'  => 'Reactivado',
                                    'Devuelto'     => 'Devuelto',
                                    'No devuelto'  => 'No devuelto',
                                    'lost'         => 'Extravío',
                                    default => $state,
                                }),

                            TextEntry::make('notes')
                                ->label('Detalle'),

                            TextEntry::make('created_at')
                                ->label('Fecha')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ]),
        ]);
    }
}
