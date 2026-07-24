<?php

namespace App\Filament\Resources\MailTemplates\Schemas;

use App\Models\SmtpProfile;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                ->live()
                ->options([
                    'asset_assignment'      => 'Asignación de activo',
                    'asset_replacement'     => 'Cambio de equipo',
                    'asset_return'          => 'Devolución de equipo',
                    'onboarding_completed'  => 'Alta de empleado',
                    'offboarding_completed' => 'Baja de empleado',
                    'pending_assets_report' => 'Reporte de equipos pendientes',
                    'shipment_delivered'    => 'Envío entregado',
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
                ]),

            \Filament\Forms\Components\Placeholder::make('variables_help')
                ->label('')
                ->columnSpanFull()
                ->content(fn ($get) => new \Illuminate\Support\HtmlString(
                    '<div class="flex flex-wrap gap-2">'
                    . '<span class="text-xs text-gray-500 mr-2 self-center">Variables (clic para copiar):</span>'
                    . collect(match ($get('code')) {
                        'pending_assets_report' => ['pending_count' => 'Cant. pendientes', 'pending_list' => 'Lista equipos', 'date' => 'Fecha'],
                        'shipment_delivered' => ['person_name' => 'Nombre', 'tracking_number' => 'Nº seguimiento', 'carrier' => 'Transportista', 'date' => 'Fecha'],
                        'asset_assignment' => ['person_name' => 'Nombre', 'asset' => 'Equipo', 'date' => 'Fecha'],
                        'asset_replacement' => ['person_name' => 'Nombre', 'old_asset' => 'Equipo anterior', 'new_asset' => 'Equipo nuevo', 'reason' => 'Motivo', 'date' => 'Fecha'],
                        'asset_return' => ['person_name' => 'Nombre', 'asset' => 'Equipo', 'date' => 'Fecha'],
                        'onboarding_completed' => ['person_name' => 'Nombre', 'asset' => 'Equipo', 'date' => 'Fecha'],
                        'offboarding_completed' => ['person_name' => 'Nombre', 'asset' => 'Equipo', 'date' => 'Fecha'],
                        default => ['person_name' => 'Nombre', 'asset' => 'Equipo', 'date' => 'Fecha'],
                    })->map(fn ($label, $var) =>
                        "<button type=\"button\" onclick=\"var t=document.createElement('textarea');t.value='{{ {$var} }}';document.body.appendChild(t);t.select();document.execCommand('copy');document.body.removeChild(t);this.innerText='✓';setTimeout(()=>this.innerText='{$label}',1200)\" class=\"px-2 py-1 rounded bg-gray-700 hover:bg-amber-600 text-xs text-gray-200 transition cursor-pointer\">{$label}</button>"
                    )->implode('')
                    . '</div>'
                )),

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

            // ─── CONFIGURACIÓN DE ENVÍO ───
            Section::make('Configuración de envío')
                ->description('Destinatario, CC y programación automática de esta plantilla.')
                ->collapsible()
                ->schema([
                    TextInput::make('schedule_to')
                        ->label('Destinatario')
                        ->email()
                        ->placeholder('rrhh@empresa.com')
                        ->helperText('Email principal que recibe este correo. Si está vacío, se usa el del perfil SMTP o el de la persona.'),

                    TextInput::make('schedule_cc')
                        ->label('CC')
                        ->placeholder('gerencia@empresa.com;it@empresa.com')
                        ->helperText('Correos en copia, separados por punto y coma (;).'),

                    Select::make('schedule_frequency')
                        ->label('Frecuencia de envío automático')
                        ->options([
                            'daily_1' => 'Todos los días',
                            'daily_2' => 'Cada 2 días',
                            'daily_3' => 'Cada 3 días',
                            'daily_4' => 'Cada 4 días',
                            'daily_5' => 'Cada 5 días',
                            'weekly'   => '1 vez por semana (lunes)',
                            'biweekly' => '1 vez cada 2 semanas (lunes)',
                            'triweekly' => '1 vez cada 3 semanas (lunes)',
                        ])
                        ->nullable()
                        ->visible(fn ($get) => $get('code') === 'pending_assets_report')
                        ->helperText('Solo aplica al reporte de equipos pendientes.'),
                ]),
        ]);
    }
}
