<?php

namespace App\Filament\Resources\Assets\Schemas;

use Filament\Schemas\Schema;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
    ->components([

        TextEntry::make('device')->label('Dispositivo'),
        TextEntry::make('brand')->label('Marca'),
        TextEntry::make('model')->label('Modelo'),

        TextEntry::make('cpu')->label('CPU'),
        TextEntry::make('ram')->label('RAM'),
        TextEntry::make('disk')->label('Disco'),
        TextEntry::make('serial')->label('Nº Serie'),

        // 🔥 ESTADO (ACÁ VA)
        TextEntry::make('status')
            ->label('Estado')
            ->badge()
            ->color(fn ($state) => match ($state) {
                'available' => 'success',
                'assigned' => 'primary',
                'in_return' => 'warning',
                'retired' => 'danger',
                default => 'gray',
            })
            ->formatStateUsing(fn ($state) => match ($state) {
                'available' => 'Disponible',
                'assigned' => 'En uso',
                'in_return' => 'En devolución',
                'retired' => 'Dado de baja',
                default => $state,
            }),

        // 🔥 HISTORIAL (lo que ya tenías)
        RepeatableEntry::make('histories')
            ->label('Historial')
            ->schema([
                TextEntry::make('person.name')
                    ->label('Usuario')
                    ->formatStateUsing(fn ($state) => $state ? "Ex {$state}" : '-'),

                TextEntry::make('action')
                    ->label('Acción'),

                TextEntry::make('notes')
                    ->label('Detalle'),

                TextEntry::make('created_at')
                    ->label('Fecha')
                    ->dateTime(),
            ]),
    ]);
    }
}