<?php

namespace App\Filament\Resources\Printers\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;

class PrinterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->placeholder('Ej: Impresora Recepción'),

            Select::make('type')
                ->label('Tipo')
                ->options([
                    'network' => 'De red (SNMP automático)',
                    'manual'  => 'Manual (conteo a mano)',
                ])
                ->default('network')
                ->required()
                ->live()
                ->helperText('"Manual" para impresoras que no están en red: el conteo se carga a mano.'),

            TextInput::make('ip')
                ->label('Dirección IP')
                ->required(fn ($get) => $get('type') === 'network')
                ->rules(['nullable', 'ip'])
                ->placeholder('192.168.1.50')
                ->visible(fn ($get) => $get('type') === 'network'),

            TextInput::make('location')
                ->label('Ubicación')
                ->placeholder('Oficina / piso'),

            TextInput::make('snmp_community')
                ->label('Community SNMP')
                ->default('public')
                ->helperText('Community de lectura SNMP (por defecto "public").')
                ->visible(fn ($get) => $get('type') === 'network'),

            TextInput::make('brand')
                ->label('Marca')
                ->helperText('Se completa solo la primera vez que se verifica. Podés corregirla a mano.'),

            TextInput::make('model')
                ->label('Modelo')
                ->helperText('Se completa solo la primera vez que se verifica. Corregilo si el SNMP trae la placa de red en vez del modelo real.'),

            TextInput::make('serial')
                ->label('Nº Serie')
                ->helperText('Se completa solo la primera vez que se verifica. Podés corregirlo a mano.'),

            Textarea::make('notes')
                ->label('Observaciones')
                ->rows(3),
        ]);
    }
}
