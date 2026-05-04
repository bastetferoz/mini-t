<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\UserResource\Pages;
use Spatie\Permission\Models\Permission;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // 🔥 NAVEGACIÓN (IMPORTANTE)
    protected static string | \UnitEnum | null $navigationGroup = 'Administración';
protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
protected static ?string $navigationLabel = 'Usuarios';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            TextInput::make('name')
                ->label('Nombre')
                ->required(),

            TextInput::make('email')
                ->email()
                ->required(),

            TextInput::make('password')
                ->password()
                ->required(fn ($record) => $record === null)
                ->dehydrated(fn ($state) => filled($state))
                ->label('Contraseña'),

            Select::make('roles')
    ->label('Rol')
    ->relationship('roles', 'name')
    ->multiple(false) // 🔥 un solo rol (recomendado)
    ->preload()
    ->required(),
    Select::make('permissions')
    ->label('Permisos')
    ->multiple()
    ->preload()
    ->options([
        'assign assets' => 'Asignar activos',
        'manage users' => 'Gestionar usuarios',
        'replace assets' => 'Reemplazar activos',
        'retire assets' => 'Dar de baja activos',
        'view assets' => 'Ver activos',
    ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('name')->label('Nombre')->searchable(),
            TextColumn::make('email')->label('Email'),

            TextColumn::make('roles.name')
                ->label('Rol')
                ->badge(),
        ])
        ->headerActions([
            \Filament\Actions\CreateAction::make(),
        ])
        ->recordActions([
            \Filament\Actions\EditAction::make(),
        ]);
    }

    // 🔥 ESTO HACE QUE FUNCIONE EL RESOURCE (MUY IMPORTANTE)
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    // ⚠️ SI NO TE APARECE EL MENÚ, COMENTALO TEMPORALMENTE
    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}