<?php

namespace App\Filament\Resources\MailTemplates\Schemas;

use App\Models\SmtpProfile;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required(),

            Select::make('code')
                ->label('Tipo de plantilla')
                ->required()
                ->unique(ignoreRecord: true)
                ->options([
                    'asset_assignment'      => 'Asignación de activo',
                    'asset_replacement'     => 'Cambio de equipo',
                    'asset_return'          => 'Devolución de equipo',
                    'onboarding_completed'  => 'Alta de empleado',
                    'offboarding_completed' => 'Baja de empleado',
                ])
                ->searchable()
                ->preload()
                ->helperText('Seleccioná el proceso al que corresponde esta plantilla.'),

            TextInput::make('subject')
                ->label('Asunto')
                ->required()
                ->helperText('Ejemplo: Cambio de equipo para {{ person_name }}'),

            RichEditor::make('body')
                ->label('Cuerpo')
                ->required()
                ->columnSpanFull()
                ->toolbarButtons([
                    'bold',
                    'italic',
                    'underline',
                    'strike',
                    'bulletList',
                    'orderedList',
                    'blockquote',
                    'h2',
                    'h3',
                    'undo',
                    'redo',
                ])
                ->helperText(
                    'Variables disponibles: {{ person_name }}, {{ old_asset }}, {{ new_asset }}, {{ asset }}, {{ date }}'
                ),

            Select::make('smtp_profile_id')
                ->label('Perfil SMTP')
                ->options(
                    SmtpProfile::where('is_active', true)
                        ->pluck('name', 'id')
                )
                ->searchable()
                ->preload()
                ->nullable(),

            Toggle::make('is_active')
                ->label('Activa')
                ->default(true),
        ]);
    }
}