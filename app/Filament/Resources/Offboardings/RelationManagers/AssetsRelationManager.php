<?php

namespace App\Filament\Resources\Offboardings\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Equipos devueltos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('device')
                    ->label('Dispositivo'),

                TextColumn::make('brand')
                    ->label('Marca'),

                TextColumn::make('model')
                    ->label('Modelo'),

                TextColumn::make('serial')
                    ->label('Nº Serie'),

                TextColumn::make('pivot.returned')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) =>
                        $state ? 'Devuelto' : 'Pendiente'
                    )
                    ->color(fn ($state) =>
                        $state ? 'success' : 'warning'
                    ),

                TextColumn::make('pivot.notes')
                    ->label('Observaciones'),

            ]);
    }
}