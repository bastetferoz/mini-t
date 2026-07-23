<?php

namespace App\Filament\Resources\MailTemplates\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->searchable(),

                TextColumn::make('subject')
                    ->label('Asunto')
                    ->limit(50),

                TextColumn::make('smtpProfile.name')
                    ->label('Perfil SMTP'),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar correo de prueba')
                    ->modalDescription(fn ($record) => "Se enviará un correo de prueba usando la plantilla \"{$record->name}\" con datos genéricos.")
                    ->action(function ($record) {
                        // Variables genéricas para rellenar
                        $testVariables = [
                            'person_name' => 'Juan Pérez (TEST)',
                            'asset' => 'Notebook - Dell - Latitude 5520 - SN: TEST123',
                            'old_asset' => 'Notebook - HP - ProBook 440 - SN: OLD456',
                            'new_asset' => 'Notebook - Lenovo - ThinkPad T14 - SN: NEW789',
                            'reason' => 'Upgrade (TEST)',
                            'date' => now()->format('d/m/Y'),
                            'tracking_number' => 'EP000TEST000',
                            'carrier' => 'EnvíoPack (TEST)',
                            'pending_count' => '• Ana García — 12 días sin coordinar<br>• Pedro López — Coordinado EnvíoPack (EP013090165R)<br>• María Gómez — Coordinado con moto',
                            'pending_list' => '<b>Ana García</b><br>  - Mouse - Logitech - M170<br>  - Auricular - Philips - SH5005<br><br><b>Pedro López</b><br>  - Notebook - Dell - Latitude 5520 - SN: ABC123<br><br><b>María Gómez</b><br>  - Monitor - Samsung - 24" - SN: MON456',
                            'pending_enviopack' => '• Notebook - Dell - Latitude — Pedro López [En tránsito]',
                            'pending_moto' => '• Mouse - Logitech - M170 — Ana García',
                            'pending_sin_coordinar' => '• Auricular - Philips - SH5005 — María Gómez',
                            'delayed_list' => '• Pedro López — 12 días<br>• María Gómez — 8 días',
                            'email' => $record->schedule_to ?? auth()->user()->email,
                        ];

                        // Enviar al destinatario configurado en la plantilla
                        $sent = \App\Services\MailTemplateService::send(
                            $record->code,
                            $testVariables
                        );

                        if ($sent) {
                            $to = $record->schedule_to ?? 'destinatario del perfil SMTP';
                            Notification::make()
                                ->title('Correo de prueba enviado')
                                ->body("Enviado a: {$to}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error al enviar')
                                ->body('Verificá que la plantilla tenga un perfil SMTP válido y esté activa.')
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}