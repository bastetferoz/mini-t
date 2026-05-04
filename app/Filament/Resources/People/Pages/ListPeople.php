<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use Filament\Resources\Pages\ListRecords;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;

class ListPeople extends ListRecords
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [

            // 🟢 BOTÓN ALTA
            CreateAction::make()
                ->label('Alta'),

            // 🔵 BOTÓN IMPORTAR
            Action::make('import')
                ->label('Importar')
                ->icon('heroicon-o-arrow-up-tray')

                ->form([
                    FileUpload::make('file')
                        ->required()
                        ->acceptedFileTypes(['text/csv'])
                ])

                ->action(function ($data) {

                    if (!isset($data['file']) || !Storage::exists($data['file'])) {
                        return;
                    }

                    $path = Storage::path($data['file']);
                    $file = fopen($path, 'r');

                    if (!$file) {
                        return;
                    }

                    // 🔥 Detectar separador automáticamente
                    $firstLine = fgets($file);

                    if (str_contains($firstLine, "\t")) {
                        $delimiter = "\t";
                    } elseif (str_contains($firstLine, ";")) {
                        $delimiter = ";";
                    } else {
                        $delimiter = ",";
                    }

                    rewind($file);

                    // 🔥 Saltear encabezado
                    fgetcsv($file, 0, $delimiter);

                    while (($row = fgetcsv($file, 0, $delimiter)) !== false) {

                        // evitar filas vacías
                        if (empty($row[0])) {
                            continue;
                        }

                        \App\Models\Person::create([
                            'name' => preg_replace('/\s+/', ' ', trim($row[0] ?? '')),
                            'email' => trim($row[1] ?? ''),
                            'area' => trim($row[2] ?? ''),
                            'status' => 'active',
                        ]);
                    }

                    fclose($file);
                }),

        ];
    }
}