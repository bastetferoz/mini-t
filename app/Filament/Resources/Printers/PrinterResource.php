<?php

namespace App\Filament\Resources\Printers;

use App\Filament\Resources\Printers\Pages\CreatePrinter;
use App\Filament\Resources\Printers\Pages\EditPrinter;
use App\Filament\Resources\Printers\Pages\ListPrinters;
use App\Filament\Resources\Printers\Pages\ViewPrinter;
use App\Filament\Resources\Printers\Schemas\PrinterForm;
use App\Filament\Resources\Printers\Schemas\PrinterInfolist;
use App\Filament\Resources\Printers\Tables\PrintersTable;
use App\Models\Printer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PrinterResource extends Resource
{
    protected static ?string $model = Printer::class;

    protected static string|UnitEnum|null $navigationGroup = 'Infra';

    protected static ?string $navigationLabel = 'Impresoras';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'impresora';

    protected static ?string $pluralModelLabel = 'impresoras';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('admin') ||
               auth()->user()->hasRole('it');
    }

    public static function form(Schema $schema): Schema
    {
        return PrinterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PrinterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrintersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrinters::route('/'),
            'create' => CreatePrinter::route('/create'),
            'view' => ViewPrinter::route('/{record}'),
            'edit' => EditPrinter::route('/{record}/edit'),
        ];
    }
}
