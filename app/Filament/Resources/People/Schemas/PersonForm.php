<?php

namespace App\Filament\Resources\People\Schemas;
use Filament\Forms\Components\Textarea;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\Asset;

class PersonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),

                TextInput::make('email')
                    ->label('Email'),

                TextInput::make('area')
                    ->label('Área'),

               \Filament\Forms\Components\Toggle::make('send_email')
    ->label('Enviar notificación por correo')
    ->default(true)
    ->helperText('Si está activado, al guardar se enviará la plantilla de alta.')
       

                

            ]);
    }
}