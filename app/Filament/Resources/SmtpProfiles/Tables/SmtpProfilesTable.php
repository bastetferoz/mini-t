<?php

namespace App\Filament\Resources\SmtpProfiles\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;

class SmtpProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Perfil')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('username')
                    ->label('Usuario SMTP')
                    ->searchable(),

                TextColumn::make('from_address')
                    ->label('Correo remitente')
                    ->searchable(),

                IconColumn::make('is_default')
                    ->label('Predeterminado')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('test')
                    ->label('Probar perfil')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->action(function ($record) {
                        try {
                            // Configurar SMTP dinámicamente
                            config([
                                'mail.default' => 'smtp',
                                'mail.mailers.smtp.transport' => 'smtp',
                                'mail.mailers.smtp.host' => $record->host,
                                'mail.mailers.smtp.port' => $record->port,
                                'mail.mailers.smtp.username' => $record->username,
                                'mail.mailers.smtp.password' => $record->password,
                                'mail.mailers.smtp.encryption' => $record->encryption,
                                'mail.from.address' => $record->from_address,
                                'mail.from.name' => $record->from_name,
                            ]);

                            // Destino del correo de prueba
                            $to = $record->default_to ?: $record->from_address;

                            // Enviar correo
                            Mail::raw('test', function ($message) use ($record, $to) {
                                $message->to($to)
                                    ->subject('Test SMTP - ' . $record->name);

                                // CC opcionales separados por ;
                                if (!empty($record->cc_addresses)) {
                                    $cc = array_filter(
                                        array_map('trim', explode(';', $record->cc_addresses))
                                    );

                                    if (!empty($cc)) {
                                        $message->cc($cc);
                                    }
                                }
                            });

                            Notification::make()
                                ->title('Correo de prueba enviado')
                                ->body("Se envió un correo de prueba a {$to}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al enviar el correo')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}