<?php

namespace App\Filament\Resources\AiProfiles;

use App\Filament\Resources\AiProfiles\Pages\CreateAiProfile;
use App\Filament\Resources\AiProfiles\Pages\EditAiProfile;
use App\Filament\Resources\AiProfiles\Pages\ListAiProfiles;
use App\Models\AiProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class AiProfileResource extends Resource
{
    protected static ?string $model = AiProfile::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Administración';
    protected static ?string $navigationLabel = 'IA';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?int $navigationSort = 130;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre del perfil')
                ->required()
                ->placeholder('GPT-4o Producción'),

            Select::make('provider')
                ->label('Proveedor')
                ->required()
                ->live()
                ->options([
                    'openai' => 'OpenAI (GPT)',
                    'google' => 'Google (Gemini)',
                    'anthropic' => 'Anthropic (Claude)',
                    'groq' => 'Groq',
                ]),

            Select::make('model')
                ->label('Modelo')
                ->required()
                ->searchable()
                ->options(fn ($get) => match ($get('provider')) {
                    'openai' => [
                        'gpt-4o' => 'GPT-4o',
                        'gpt-4o-mini' => 'GPT-4o Mini',
                        'gpt-4-turbo' => 'GPT-4 Turbo',
                        'gpt-4.1' => 'GPT-4.1',
                        'gpt-4.1-mini' => 'GPT-4.1 Mini',
                        'o3' => 'o3',
                        'o3-mini' => 'o3 Mini',
                        'o4-mini' => 'o4 Mini',
                        'gpt-5' => 'GPT-5',
                    ],
                    'google' => [
                        'gemini-3.5-flash' => 'Gemini 3.5 Flash (más inteligente)',
                        'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash-Lite (económico)',
                        'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                        'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite',
                        'gemini-2.5-pro' => 'Gemini 2.5 Pro (avanzado)',
                    ],
                    'anthropic' => [
                        'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                        'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
                        'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku',
                    ],
                    'groq' => [
                        'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
                        'llama-3.1-8b-instant' => 'Llama 3.1 8B Instant',
                        'llama-4-scout-17b-16e-instruct' => 'Llama 4 Scout 17B',
                        'meta-llama/llama-4-maverick-17b-128e-instruct' => 'Llama 4 Maverick 17B',
                        'mixtral-8x7b-32768' => 'Mixtral 8x7B',
                    ],
                    default => [],
                })
                ->helperText('Seleccioná primero el proveedor.'),

            TextInput::make('api_key')
                ->label('API Key')
                ->password()
                ->revealable()
                ->required()
                ->placeholder('sk-... / AIza... / sk-ant-...')
                ->helperText('La clave de API del proveedor seleccionado.'),

            TextInput::make('endpoint')
                ->label('Endpoint personalizado')
                ->url()
                ->nullable()
                ->placeholder('https://...')
                ->helperText('Opcional. Dejalo vacío para usar el endpoint oficial del proveedor.'),

            Toggle::make('is_active')
                ->label('Activo')
                ->default(true),

            Toggle::make('is_default')
                ->label('Perfil predeterminado')
                ->default(false)
                ->helperText('El perfil por defecto se usa para analizar facturas.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Perfil')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('provider')
                    ->label('Proveedor')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'openai' => 'OpenAI',
                        'google' => 'Google',
                        'anthropic' => 'Anthropic',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'openai' => 'success',
                        'google' => 'info',
                        'anthropic' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('model')
                    ->label('Modelo')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_default')
                    ->label('Predeterminado')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('set_default')
                    ->label('Usar por defecto')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn ($record) => ! $record->is_default)
                    ->requiresConfirmation()
                    ->modalHeading('¿Establecer como predeterminado?')
                    ->modalDescription(fn ($record) => "Se usará \"{$record->name}\" para analizar facturas.")
                    ->action(function ($record) {
                        // Quitar default de todos
                        AiProfile::where('is_default', true)->update(['is_default' => false]);

                        // Establecer este como default
                        $record->update(['is_default' => true]);

                        Notification::make()
                            ->title('Perfil predeterminado actualizado')
                            ->body("{$record->name} es ahora el perfil por defecto.")
                            ->success()
                            ->send();
                    }),

                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->action(function ($record) {
                        try {
                            $prompt = 'Respondé solo con este JSON: {"status":"ok","model":"' . $record->model . '"}';

                            $response = match ($record->provider) {
                                'openai', 'groq' => \Illuminate\Support\Facades\Http::withHeaders([
                                    'Authorization' => "Bearer {$record->api_key}",
                                ])->timeout(15)->post($record->getEndpointUrl(), [
                                    'model' => $record->model,
                                    'messages' => [['role' => 'user', 'content' => $prompt]],
                                    'max_tokens' => 100,
                                ]),

                                'google' => \Illuminate\Support\Facades\Http::timeout(15)->post(
                                    "https://generativelanguage.googleapis.com/v1beta/models/{$record->model}:generateContent?key={$record->api_key}",
                                    ['contents' => [['parts' => [['text' => $prompt]]]]]
                                ),

                                'anthropic' => \Illuminate\Support\Facades\Http::withHeaders([
                                    'x-api-key' => $record->api_key,
                                    'anthropic-version' => '2023-06-01',
                                    'content-type' => 'application/json',
                                ])->timeout(15)->post($record->endpoint ?: 'https://api.anthropic.com/v1/messages', [
                                    'model' => $record->model,
                                    'max_tokens' => 100,
                                    'messages' => [['role' => 'user', 'content' => $prompt]],
                                ]),

                                default => null,
                            };

                            if (! $response || ! $response->successful()) {
                                $body = $response ? substr($response->body(), 0, 200) : 'Sin respuesta';
                                Notification::make()
                                    ->title('❌ Error')
                                    ->body("HTTP {$response?->status()}: {$body}")
                                    ->danger()
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            // Extraer el texto de la respuesta según proveedor
                            $text = match ($record->provider) {
                                'openai', 'groq' => $response->json('choices.0.message.content'),
                                'google' => $response->json('candidates.0.content.parts.0.text'),
                                'anthropic' => $response->json('content.0.text'),
                                default => 'OK',
                            };

                            Notification::make()
                                ->title('✅ Conexión exitosa')
                                ->body("Respuesta: " . substr($text ?? 'OK', 0, 150))
                                ->success()
                                ->persistent()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('❌ Excepción')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiProfiles::route('/'),
            'create' => CreateAiProfile::route('/create'),
            'edit' => EditAiProfile::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}
