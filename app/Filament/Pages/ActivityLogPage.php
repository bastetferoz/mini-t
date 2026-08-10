<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string | \UnitEnum | null $navigationGroup = 'Administración';
    protected static ?string $navigationLabel = 'Log';
    protected static ?int $navigationSort = 140;
    protected static ?string $title = 'Log de actividad';

    protected string $view = 'filament.pages.activity-log';

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->latest())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->default('Sistema')
                    ->searchable(),

                TextColumn::make('log_name')
                    ->label('Módulo')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'facturacion' => 'success',
                        'activos' => 'primary',
                        'personas' => 'info',
                        'offboarding' => 'danger',
                        'sistema' => 'gray',
                        default => 'warning',
                    })
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Evento')
                    ->limit(80)
                    ->searchable(),

                TextColumn::make('subject_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Módulo')
                    ->options([
                        'facturacion' => 'Facturación',
                        'activos' => 'Activos',
                        'personas' => 'Personas',
                        'offboarding' => 'Offboarding',
                        'sistema' => 'Sistema',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}
