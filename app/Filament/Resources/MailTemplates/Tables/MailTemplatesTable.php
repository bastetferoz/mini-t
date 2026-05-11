<?php

namespace App\Filament\Resources\MailTemplates\Tables;

use Filament\Actions\EditAction;
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
            ]);
    }
}