<?php

namespace App\Filament\Resources\SmtpProfiles;

use App\Filament\Resources\SmtpProfiles\Pages\CreateSmtpProfile;
use App\Filament\Resources\SmtpProfiles\Pages\EditSmtpProfile;
use App\Filament\Resources\SmtpProfiles\Pages\ListSmtpProfiles;
use App\Filament\Resources\SmtpProfiles\Schemas\SmtpProfileForm;
use App\Filament\Resources\SmtpProfiles\Tables\SmtpProfilesTable;
use App\Models\SmtpProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SmtpProfileResource extends Resource
{
    protected static ?string $model = SmtpProfile::class;

    // Navegación
    protected static string | \UnitEnum | null $navigationGroup = 'Administración';
    protected static ?string $navigationLabel = 'SMTP';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-envelope';
    protected static ?int $navigationSort = 110;

    // Título del registro
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SmtpProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SmtpProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmtpProfiles::route('/'),
            'create' => CreateSmtpProfile::route('/create'),
            'edit' => EditSmtpProfile::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}