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

            

                // 🔹 Toggle: Nuevo ingreso
                Toggle::make('onboarding_completed')
                    ->label('Nuevo ingreso')
                    ->default(false)
                    ->hiddenOn('view')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('asset_assignment', false);
                        }
                    }),

                // 🔹 Toggle: Asignación de equipo
                Toggle::make('asset_assignment')
                    ->label('Asignación de equipo')
                    ->default(false)
                    ->hiddenOn('view')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('onboarding_completed', false);
                        }
                    }),

            ]);
    }
}