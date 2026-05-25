<?php

namespace App\Filament\Resources\People;

use App\Filament\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Filament\Resources\People\Pages\ViewPerson;
use App\Filament\Resources\People\Schemas\PersonForm;
use App\Filament\Resources\People\Schemas\PersonInfolist;
use App\Filament\Resources\People\Tables\PeopleTable;
use App\Models\Person;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\People\RelationManagers\AssignmentsRelationManager;
use Filament\Forms\Components\CheckboxList;
use App\Filament\Resources\People\RelationManagers\AssetHistoriesRelationManager;


class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static ?string $navigationLabel = 'Gente';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PersonForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PersonInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->whereIn('status', ['active', 'offboarding']);
}

    public static function table(Table $table): Table
    {
        return PeopleTable::configure($table);
    }

   public static function getRelations(): array
{
    return [

        AssignmentsRelationManager::class,

        AssetHistoriesRelationManager::class,

    ];
}

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'create' => CreatePerson::route('/create'),
            'view' => ViewPerson::route('/{record}'),
            'edit' => EditPerson::route('/{record}/edit'),
        ];
    }

 
}
