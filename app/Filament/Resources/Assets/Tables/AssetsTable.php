<?php

namespace App\Filament\Resources\Assets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('device')
                    ->label('Dispositivo')
                    ->searchable(),

                TextColumn::make('brand')
                    ->label('Marca')
                    ->searchable(),

                TextColumn::make('model')
                    ->label('Modelo')
                    ->searchable(),

                TextColumn::make('cpu')
                    ->label('CPU')
                    ->searchable(),

                TextColumn::make('ram')
                    ->label('RAM')
                    ->searchable(),

                TextColumn::make('disk')
                    ->label('Disco')
                    ->searchable(),

                TextColumn::make('serial')
                    ->label('Nº Serie')
                    ->searchable(),

                BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'available'  => 'Disponible',
                        'assigned'   => 'En uso',
                        'in_transit' => 'En devolución',
                        'retired'    => 'Dado de baja',
                        default      => $state,
                    })
                    ->colors([
                        'success' => fn ($state) => $state === 'available',
                        'info'    => fn ($state) => $state === 'assigned',
                        'warning' => fn ($state) => $state === 'in_transit',
                        'danger'  => fn ($state) => $state === 'retired',
                    ])
                    ->searchable(query: function ($query, string $search) {
                        $search = mb_strtolower(trim($search));

                        $statusMap = [
                            'available'  => 'disponible',
                            'assigned'   => 'en uso',
                            'in_transit' => 'en devolución',
                            'retired'    => 'dado de baja',
                        ];

                        foreach ($statusMap as $dbValue => $label) {
                            if (stripos($label, $search) !== false) {
                                $query->orWhere('status', $dbValue);
                            }
                        }

                        $query->orWhere('status', 'like', "%{$search}%");
                    }),
            ])
            ->searchPlaceholder('Buscar por dispositivo, marca, modelo, CPU, RAM, disco, serie o estado')
            ->defaultSort('id', 'desc')

            // ❌ Sin botones View / Edit
            ->actions([])

            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}