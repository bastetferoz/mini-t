<?php

namespace App\Filament\Resources\SmtpProfiles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SmtpProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre del perfil')
                ->required(),

            TextInput::make('host')
                ->label('Servidor SMTP')
                ->required(),

            TextInput::make('port')
                ->label('Puerto')
                ->numeric()
                ->default(587)
                ->required(),

            TextInput::make('username')
                ->label('Usuario')
                ->required(),

            TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->revealable()
                ->required(),

            Select::make('encryption')
                ->label('Encriptación')
                ->options([
                    'tls' => 'TLS',
                    'ssl' => 'SSL',
                ])
                ->default('tls'),

            TextInput::make('from_address')
                ->label('Correo remitente')
                ->email()
                ->required(),

            TextInput::make('from_name')
                ->label('Nombre remitente')
                ->default('IT'),

            TextInput::make('default_to')
                ->label('Destinatario por defecto')
                ->email(),

            Toggle::make('is_default')
                ->label('Perfil predeterminado')
                ->default(false),

            Toggle::make('is_active')
                ->label('Activo')
                ->default(true),
        ]);
    }
}