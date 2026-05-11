<?php

namespace App\Filament\Resources\Assets\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
    TextInput::make('device')->label('Dispositivo')->required(),
    TextInput::make('brand')->label('Marca'),
    TextInput::make('model')->label('Modelo'),

    TextInput::make('cpu')->label('Procesador'),
    TextInput::make('ram')->label('Memoria'),
    TextInput::make('disk')->label('Disco'),

    Select::make('status')
        ->label('Estado')
        ->options([
            'available' => 'Disponible',
            'assigned' => 'En uso',
            'in_return' => 'En devolución',
            'retired' => 'Dado de baja',
        ])
        
        ->default('available')
        ->required(),

    TextInput::make('serial')
        ->label('Nº Serie')
        ->placeholder('Opcional')
        ->nullable(),

    Textarea::make('notes')
        ->label('Observaciones')
        ->rows(3),
]);
    }
}