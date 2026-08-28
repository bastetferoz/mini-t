<?php

namespace App\Filament\Resources\Printers\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;

class PrinterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Impresora')
                ->icon('heroicon-o-printer')
                ->schema([
                    TextEntry::make('name')->label('Nombre'),
                    TextEntry::make('ip')->label('IP')->copyable(),
                    TextEntry::make('location')->label('Ubicación')->placeholder('—'),
                    TextEntry::make('brand')->label('Marca')->placeholder('—'),
                    TextEntry::make('model')->label('Modelo')->placeholder('—'),
                    TextEntry::make('serial')->label('Nº Serie')->placeholder('—'),
                    TextEntry::make('snmp_community')->label('Community SNMP'),
                ])
                ->columns(3),

            Section::make('Estado')
                ->icon('heroicon-o-signal')
                ->schema([
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->size('lg')
                        ->color(fn ($state) => match ($state) {
                            'online'  => 'success',
                            'offline' => 'danger',
                            default   => 'gray',
                        })
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'online'  => 'En línea',
                            'offline' => 'Desconectada',
                            default   => 'Desconocido',
                        }),

                    TextEntry::make('page_count')
                        ->label('Contador de páginas')
                        ->numeric()
                        ->placeholder('—'),

                    TextEntry::make('page_count_at')
                        ->label('Última lectura')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),

                    TextEntry::make('last_seen_at')
                        ->label('Último chequeo')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),

                    TextEntry::make('notes')
                        ->label('Observaciones')
                        ->visible(fn ($record) => ! empty($record->notes)),
                ])
                ->columns(3),

            Section::make('Historial de contador')
                ->icon('heroicon-o-clock')
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('readings')
                        ->label('')
                        ->schema([
                            TextEntry::make('read_at')
                                ->label('Fecha')
                                ->dateTime('d/m/Y H:i'),

                            TextEntry::make('page_count')
                                ->label('Contador')
                                ->numeric(),

                            TextEntry::make('source')
                                ->label('Origen')
                                ->badge()
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'manual'    => 'Manual',
                                    'scheduled' => 'Automático',
                                    default     => $state,
                                }),
                        ])
                        ->columns(3),
                ]),
        ]);
    }
}
