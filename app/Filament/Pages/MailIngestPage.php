<?php

namespace App\Filament\Pages;

use App\Models\MailIngestConfig;
use App\Services\MailIngestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class MailIngestPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static string | \UnitEnum | null $navigationGroup = 'Administración';
    protected static ?string $navigationLabel = 'Ingesta de correo';
    protected static ?int $navigationSort = 150;
    protected static ?string $title = 'Ingesta automática de facturas';

    protected string $view = 'filament.pages.mail-ingest';

    public ?array $data = [];

    public function mount(): void
    {
        $config = MailIngestConfig::first();

        $this->form->fill([
            'name' => $config?->name ?? '',
            'email' => $config?->email ?? '',
            'provider' => $config?->provider ?? 'microsoft',
            'tenant_id' => $config?->tenant_id ?? '',
            'client_id' => $config?->client_id ?? '',
            'client_secret' => $config?->client_secret ?? '',
            'folder' => $config?->folder ?? 'INBOX',
            'check_interval_minutes' => $config?->check_interval_minutes ?? 30,
            'is_active' => $config?->is_active ?? false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->placeholder('Facturación Phinxlab'),

                TextInput::make('email')
                    ->label('Buzón de correo')
                    ->email()
                    ->required()
                    ->placeholder('it-facturacion@phinxlab.com'),

                Select::make('provider')
                    ->label('Proveedor de correo')
                    ->options([
                        'microsoft' => 'Microsoft 365 (Graph API)',
                    ])
                    ->default('microsoft')
                    ->required(),

                TextInput::make('tenant_id')
                    ->label('Tenant ID (Azure)')
                    ->required()
                    ->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
                    ->helperText('Lo encontrás en Azure AD → App Registrations → tu app → Overview'),

                TextInput::make('client_id')
                    ->label('Client ID (Azure)')
                    ->required()
                    ->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),

                TextInput::make('client_secret')
                    ->label('Client Secret (Azure)')
                    ->password()
                    ->revealable()
                    ->required()
                    ->helperText('Azure AD → App → Certificates & secrets → New client secret'),

                TextInput::make('folder')
                    ->label('Carpeta')
                    ->default('INBOX')
                    ->helperText('Carpeta del buzón a monitorear (INBOX por defecto).'),

                Select::make('check_interval_minutes')
                    ->label('Frecuencia de revisión')
                    ->options([
                        15 => 'Cada 15 minutos',
                        30 => 'Cada 30 minutos',
                        60 => 'Cada 1 hora',
                        120 => 'Cada 2 horas',
                        360 => 'Cada 6 horas',
                        720 => 'Cada 12 horas',
                        1440 => 'Una vez al día',
                    ])
                    ->default(30),

                Toggle::make('is_active')
                    ->label('Activar ingesta automática')
                    ->helperText('Cuando está activo, el sistema revisa el buzón periódicamente y procesa las facturas adjuntas.'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $config = MailIngestConfig::first();

        if ($config) {
            $config->update($data);
        } else {
            MailIngestConfig::create($data);
        }

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }

    public function testConnection(): void
    {
        $config = MailIngestConfig::first();

        if (! $config) {
            Notification::make()
                ->title('Sin configuración')
                ->body('Guardá la configuración primero.')
                ->danger()
                ->send();
            return;
        }

        $service = new MailIngestService($config);

        // Intentar obtener token
        try {
            $response = \Illuminate\Support\Facades\Http::asForm()->post(
                "https://login.microsoftonline.com/{$config->tenant_id}/oauth2/v2.0/token",
                [
                    'client_id' => $config->client_id,
                    'client_secret' => $config->client_secret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            );

            if ($response->successful()) {
                Notification::make()
                    ->title('✅ Conexión exitosa')
                    ->body("Autenticación OK con Microsoft Graph para {$config->email}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('❌ Error de conexión')
                    ->body("HTTP {$response->status()}: " . substr($response->body(), 0, 200))
                    ->danger()
                    ->persistent()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('❌ Excepción')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function processNow(): void
    {
        $config = MailIngestConfig::where('is_active', true)->first();

        if (! $config) {
            Notification::make()
                ->title('Ingesta no activa')
                ->body('Activá la ingesta primero.')
                ->warning()
                ->send();
            return;
        }

        $service = new MailIngestService($config);
        $stats = $service->process();

        Notification::make()
            ->title('Ingesta ejecutada')
            ->body("Procesadas: {$stats['processed']} | Errores: {$stats['errors']} | Omitidas: {$stats['skipped']}")
            ->success()
            ->send();
    }

    public function getConfig(): ?MailIngestConfig
    {
        return MailIngestConfig::first();
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}
