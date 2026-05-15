<?php

namespace App\Filament\Resources\People\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

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

                Select::make('services')
                    ->label('Servicios')
                    ->multiple()
                    ->searchable()
                    ->preload()
                     ->default([
        'teams',
        'groups',
    ])
                    ->options([
                        'jira' => 'Jira',
                        'bitbucket' => 'Bitbucket',
                        'monday' => 'Monday',
                        'teams' => 'Teams',
                        'groups' => 'Grupos',
                        'ster' => 'Ster',
                        'google_workspace' => 'Google Workspace',
                        'zendesk' => 'Zendesk',
                        'quicksight' => 'Quicksight',
                        'adobe' => 'Adobe',
                    ])
                    ->helperText('Seleccioná las plataformas a las que debe tener acceso el usuario.'),
                    
                Toggle::make('send_email')
                    ->label('Enviar notificación por correo')
                    ->default(true)
                    ->helperText('Si está activado, al guardar se enviará la plantilla de alta.'),

            ]);
    }
}