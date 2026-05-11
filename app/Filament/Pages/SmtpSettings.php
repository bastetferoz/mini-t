<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class SmtpSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string | \UnitEnum | null $navigationGroup = 'Administración';
    protected static ?string $navigationLabel = 'SMTP';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-envelope';
    protected static ?int $navigationSort = 110;
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.smtp-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mail_host' => Setting::get('mail_host'),
            'mail_port' => Setting::get('mail_port', 587),
            'mail_username' => Setting::get('mail_username'),
            'mail_password' => Setting::get('mail_password'),
            'mail_encryption' => Setting::get('mail_encryption', 'tls'),
            'mail_from_address' => Setting::get('mail_from_address'),
            'mail_from_name' => Setting::get('mail_from_name', 'IT'),
            'mail_default_to' => Setting::get('mail_default_to'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Forms\Components\TextInput::make('mail_host')
                    ->label('Servidor SMTP')
                    ->required(),

                Forms\Components\TextInput::make('mail_port')
                    ->label('Puerto')
                    ->numeric()
                    ->default(587)
                    ->required(),

                Forms\Components\TextInput::make('mail_username')
                    ->label('Usuario')
                    ->required(),

                Forms\Components\TextInput::make('mail_password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable(),

                Forms\Components\Select::make('mail_encryption')
                    ->label('Encriptación')
                    ->options([
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                    ])
                    ->default('tls'),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label('Correo remitente')
                    ->email()
                    ->required(),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label('Nombre remitente')
                    ->default('IT'),

                Forms\Components\TextInput::make('mail_default_to')
                    ->label('Destinatario por defecto')
                    ->email(),
            ]);
    }

    public function save(): void
    {
        foreach ($this->data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Configuración SMTP guardada')
            ->success()
            ->send();
    }
}