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
                    ],
                    'google' => [
                        'gemini-2.0-flash' => 'Gemini 2.0 Flash',
                        'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                        'gemini-1.5-flash' => 'Gemini 1.5 Flash',
                    ],
                    'anthropic' => [
                        'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                        'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
                        'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku',
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
