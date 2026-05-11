<?php

namespace App\Filament\Resources\Assets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('device')->label('Dispositivo')->searchable(),
        TextColumn::make('brand')->label('Marca'),
        TextColumn::make('model')->label('Modelo'),

        TextColumn::make('cpu')->label('CPU'),
        TextColumn::make('ram')->label('RAM'),
        TextColumn::make('disk')->label('Disco'),

        TextColumn::make('serial')->label('Nº Serie'),
        BadgeColumn::make('status')
    ->label('Estado')
    ->formatStateUsing(fn ($state) => match ($state) {
        'available' => 'Disponible',
        'assigned' => 'En uso',
        'in_transit' => 'En devolución',
        'retired' => 'Dado de baja',
        default => $state,
    })
    ->colors([
        'success' => fn ($state) => $state === 'available',
        'info' => fn ($state) => $state === 'assigned',
        'warning' => fn ($state) => $state === 'in_transit',
        'danger' => fn ($state) => $state === 'retired',
    ]),
    ]);
    }
}
