<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Facturación';
    protected static ?string $navigationLabel = 'Carga';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-plus';
    protected static ?int $navigationSort = 1;
    protected static bool $shouldRegisterNavigation = false; // Oculto del nav, se usa InvoiceBrowser

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('provider')
                ->label('Proveedor')
                ->required()
                ->searchable()
                ->options(fn () => \App\Models\InvoiceProvider::getOptions() + ['otro' => 'Otro']),

            Select::make('company')
                ->label('Empresa')
                ->searchable()
                ->options([
                    'phinxlab' => 'Phinxlab',
                    'novatech' => 'Novatech',
                    'cryptopatagonia' => 'Cryptopatagonia',
                ])
                ->nullable()
                ->helperText('Empresa a la que se imputa este gasto.'),

            TextInput::make('project')
                ->label('Proyecto / Cuenta')
                ->placeholder('odoo, ster, choir...')
                ->nullable(),

            TextInput::make('service')
                ->label('Servicio')
                ->placeholder('Internet, Hosting, Licencias...')
                ->nullable(),

            TextInput::make('reference')
                ->label('Referencia')
                ->placeholder('Dominio, cuenta AWS, etc.')
                ->nullable(),

            TextInput::make('amount')
                ->label('Monto')
                ->numeric()
                ->required()
                ->prefix('$'),

            Select::make('currency')
                ->label('Moneda')
                ->options([
                    'ARS' => 'ARS (Pesos)',
                    'USD' => 'USD (Dólares)',
                ])
                ->default('ARS')
                ->required(),

            DatePicker::make('invoice_date')
                ->label('Fecha de factura')
                ->required()
                ->default(now()),

            TextInput::make('period')
                ->label('Período')
                ->placeholder('2026-07')
                ->required()
                ->helperText('Formato: YYYY-MM (ej: 2026-07)'),

            TextInput::make('invoice_number')
                ->label('Nº Factura')
                ->nullable(),

            Textarea::make('notes')
                ->label('Observaciones')
                ->rows(2)
                ->nullable(),

            FileUpload::make('file_path')
                ->label('Archivo adjunto')
                ->disk('public')
                ->directory('invoices/manual')
                ->acceptedFileTypes([
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/pdf',
                ])
                ->maxSize(10240)
                ->nullable()
                ->helperText('Opcional: adjuntá una copia de la factura (JPG, PNG, PDF).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->label('Proveedor')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'telecom' => 'Telecom',
                        'metrotel' => 'Metrotel',
                        'amazon' => 'Amazon (AWS)',
                        'microsoft' => 'Microsoft',
                        'google' => 'Google',
                        'movistar' => 'Movistar',
                        'claro' => 'Claro',
                        'iplan' => 'iPlan',
                        'otro' => 'Otro',
                        default => $state,
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('service')
                    ->label('Servicio')
                    ->searchable(),

                TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable()
                    ->description(fn ($record) => $record->company ? ucfirst($record->company) : null),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color(fn ($state) => $state === 'USD' ? 'success' : 'primary'),

                TextColumn::make('period')
                    ->label('Período')
                    ->sortable(),

                TextColumn::make('invoice_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('invoice_number')
                    ->label('Nº Factura')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('file_path')
                    ->label('Archivo')
                    ->formatStateUsing(fn ($state) => $state ? '📎' : '—')
                    ->url(fn ($record) => $record->file_path
                        ? asset('storage/' . $record->file_path)
                        : null
                    )
                    ->openUrlInNewTab()
                    ->toggleable(),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->filters([
                SelectFilter::make('provider')
                    ->label('Proveedor')
                    ->options([
                        'telecom' => 'Telecom',
                        'metrotel' => 'Metrotel',
                        'amazon' => 'Amazon (AWS)',
                        'microsoft' => 'Microsoft',
                        'google' => 'Google',
                        'movistar' => 'Movistar',
                        'claro' => 'Claro',
                        'iplan' => 'iPlan',
                        'otro' => 'Otro',
                    ]),
                SelectFilter::make('currency')
                    ->label('Moneda')
                    ->options([
                        'ARS' => 'ARS',
                        'USD' => 'USD',
                    ]),
                SelectFilter::make('year')
                    ->label('Año')
                    ->options(fn () => Invoice::selectRaw('DISTINCT year')->orderByDesc('year')->pluck('year', 'year')->toArray()),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('asignar_empresa')
                    ->label(fn ($record) => $record->company ? ucfirst($record->company) : 'Sin empresa')
                    ->icon('heroicon-o-building-office')
                    ->color(fn ($record) => $record->company ? 'success' : 'warning')
                    ->size('sm')
                    ->form([
                        \Filament\Forms\Components\Select::make('company')
                            ->label('Empresa')
                            ->options([
                                'phinxlab' => 'Phinxlab',
                                'novatech' => 'Novatech',
                                'cryptopatagonia' => 'Cryptopatagonia',
                            ])
                            ->required()
                            ->default(fn ($record) => $record->company),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['company' => $data['company']]);
                        \Filament\Notifications\Notification::make()
                            ->title('Empresa asignada')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('asignar_empresa_masivo')
                        ->label('Asignar empresa')
                        ->icon('heroicon-o-building-office')
                        ->form([
                            \Filament\Forms\Components\Select::make('company')
                                ->label('Empresa')
                                ->options([
                                    'phinxlab' => 'Phinxlab',
                                    'novatech' => 'Novatech',
                                    'cryptopatagonia' => 'Cryptopatagonia',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(fn ($record) => $record->update(['company' => $data['company']]));
                            \Filament\Notifications\Notification::make()
                                ->title(count($records) . ' facturas actualizadas')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('admin') ||
               auth()->user()->hasRole('it');
    }
}
