<?php

namespace App\Filament\Resources\People\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class AssetHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'assetHistories';

    protected static ?string $title = 'Historial de activos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'assignment'  => 'success',
                        'replacement' => 'warning',
                        'return'      => 'gray',
                        'retired'     => 'danger',
                        default       => 'primary',
                    }),

                TextColumn::make('asset.device')
                    ->label('Dispositivo'),

                TextColumn::make('asset.brand')
                    ->label('Marca'),

                TextColumn::make('asset.model')
                    ->label('Modelo'),

                    TextColumn::make('asset.serial')
    ->label('Nº Serie')
    ->searchable()
    ->copyable(),
    
                TextColumn::make('notes')
                    ->label('Observación')
                    ->limit(40),

                    

            ])
            ->defaultSort('created_at', 'desc');
    }
}