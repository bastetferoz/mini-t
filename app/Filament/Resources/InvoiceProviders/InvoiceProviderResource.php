<?php

namespace App\Filament\Resources\InvoiceProviders;

use App\Filament\Resources\InvoiceProviders\Pages\CreateInvoiceProvider;
use App\Filament\Resources\InvoiceProviders\Pages\EditInvoiceProvider;
use App\Filament\Resources\InvoiceProviders\Pages\ListInvoiceProviders;
use App\Models\InvoiceProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TagsInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;

class InvoiceProviderResource extends Resource
{
    protected static ?string $model = InvoiceProvider::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Facturación';
    protected static ?string $navigationLabel = 'Proveedores';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->placeholder('Google, Amazon, Telecom...'),

            TextInput::make('slug')
                ->label('Identificador')
                ->required()
                ->unique(ignoreRecord: true)
                ->placeholder('google, amazon, telecom')
                ->rules(['regex:/^[a-z0-9_-]+$/'])
                ->dehydrateStateUsing(fn ($state) => strtolower(preg_replace('/[^a-z0-9_-]/', '', strtolower($state ?? ''))))
                ->helperText('Solo minúsculas, números, guiones (-) y guiones bajos (_). Sin espacios.'),

            Select::make('category')
                ->label('Categoría')
                ->searchable()
                ->options([
                    'cloud' => 'Cloud / Hosting',
                    'internet' => 'Internet',
                    'telefonia' => 'Telefonía',
                    'licencias' => 'Licencias / Software',
                    'devtool' => 'DevTool',
                    'dominios' => 'Dominios',
                    'ia' => 'Inteligencia Artificial',
                    'seguridad' => 'Seguridad',
                    'comunicaciones' => 'Comunicaciones',
                    'otro' => 'Otro',
                ]),

            Select::make('default_currency')
                ->label('Moneda habitual')
                ->options([
                    'ARS' => 'ARS (Pesos)',
                    'USD' => 'USD (Dólares)',
                ])
                ->default('USD'),

            Select::make('company')
                ->label('Gerencia')
                ->searchable()
                ->options([
                    'novatech' => 'Novatech',
                    'phinxlab' => 'Phinxlab',
                    'novatech/phinxlab' => 'Novatech/Phinxlab',
                    'cryptopatagonia' => 'Cryptopatagonia',
                ])
                ->nullable()
                ->helperText('Empresa/gerencia a la que pertenece este proveedor.'),

            TagsInput::make('detection_keywords')
                ->label('Palabras clave de detección')
                ->placeholder('Agregá palabras clave...')
                ->helperText('Palabras que aparecen en las facturas de este proveedor. La IA las usa para identificarlo. Ej: "Google LLC", "Google Workspace", "GCP"')
                ->required(),

            Textarea::make('custom_prompt')
                ->label('Prompt personalizado')
                ->rows(10)
                ->nullable()
                ->columnSpanFull()
                ->placeholder('Dejá vacío para usar el prompt genérico...')
                ->helperText('Prompt específico para extraer datos de facturas de este proveedor. Debe pedir un JSON con: amount, currency, invoice_date, period, invoice_number, service. Si está vacío se usa el genérico.'),

            Toggle::make('is_active')
                ->label('Activo')
                ->default(true),

            Toggle::make('is_multi')
                ->label('Multi-factura')
                ->default(false)
                ->helperText('Activar si este proveedor puede tener varias facturas en el mismo mes (ej: Google con múltiples dominios, Microsoft con distintos planes).'),

            Toggle::make('is_arrears')
                ->label('Mes vencido')
                ->default(false)
                ->helperText('Activar si factura a mes vencido: emite a principio de mes la factura del servicio del mes anterior (ej: Microsoft emite el 02/08 el servicio de julio). En ese caso la factura se cuenta en el mes anterior a la emisión.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('ID')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('category')
                    ->label('Categoría')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'cloud' => 'Cloud / Hosting',
                        'internet' => 'Internet',
                        'telefonia' => 'Telefonía',
                        'licencias' => 'Licencias / Software',
                        'dominios' => 'Dominios',
                        'ia' => 'IA',
                        'seguridad' => 'Seguridad',
                        'comunicaciones' => 'Comunicaciones',
                        'otro' => 'Otro',
                        default => $state ?? '—',
                    })
                    ->sortable(),

                TextColumn::make('default_currency')
                    ->label('Moneda')
                    ->badge()
                    ->color(fn ($state) => $state === 'USD' ? 'success' : 'primary'),

                TextColumn::make('custom_prompt')
                    ->label('Prompt')
                    ->formatStateUsing(fn ($state) => $state ? '✓ Custom' : 'Genérico')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                IconColumn::make('is_arrears')
                    ->label('Mes vencido')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoiceProviders::route('/'),
            'create' => CreateInvoiceProvider::route('/create'),
            'edit' => EditInvoiceProvider::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}
