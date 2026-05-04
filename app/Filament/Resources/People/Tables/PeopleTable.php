<?php

namespace App\Filament\Resources\People\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class PeopleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email'),

                TextColumn::make('area')
                    ->label('Área'),
            ])

            ->recordUrl(function ($record) {

                if ($record->status === 'inactive') {
                    return \App\Filament\Resources\Offboardings\OffboardingResource::getUrl('view', [
                        'record' => $record,
                    ]);
                }

                return \App\Filament\Resources\People\PersonResource::getUrl('view', [
                    'record' => $record,
                ]);
            });
    }
}