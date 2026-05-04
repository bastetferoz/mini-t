<?php

namespace App\Filament\Resources\Offboardings;

use App\Models\Person;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class OffboardingResource extends Resource
{
    protected static ?string $model = Person::class;

    // ✅ tipo correcto
    protected static ?string $navigationLabel = 'Bajas';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'inactive');
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\People\Tables\PeopleTable::configure($table);
    }

    public static function getPages(): array
{
    return [
        'index' => Pages\ListOffboardings::route('/'),
        'view' => Pages\ViewOffboarding::route('/{record}'), // 👈 CLAVE
    ];
}
}