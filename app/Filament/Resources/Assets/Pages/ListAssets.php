<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use Filament\Resources\Pages\ListRecords;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [

            CreateAction::make()->label('Alta'),

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

                    if (!$file) return;

                    // 🔥 detectar delimitador real
                    $firstLine = fgets($file);

                    if (substr_count($firstLine, "\t") > 1) {
                        $delimiter = "\t";
                    } elseif (substr_count($firstLine, ";") > 1) {
                        $delimiter = ";";
                    } else {
                        $delimiter = ",";
                    }

                    rewind($file);

                    // 🔥 leer encabezado
                    $header = fgetcsv($file, 0, $delimiter);

// 🔥 fix BOM (Excel/Sheets agrega caracteres invisibles al inicio)
if ($header && isset($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
}

$header = array_map(fn ($h) => strtolower(trim($h)), $header);

                    while (($row = fgetcsv($file, 0, $delimiter)) !== false) {

                        if (empty(array_filter($row))) continue;

                        // asegurar tamaño correcto
                        $row = array_pad($row, count($header), null);

                        $dataRow = array_combine($header, $row);

                        // limpieza general
                        $clean = fn ($v) => preg_replace('/\s+/u', ' ', trim($v ?? ''));

                        $device = $clean($dataRow['device'] ?? '');
                        $brand  = ucfirst(strtolower($clean($dataRow['brand'] ?? '')));
                        $model  = $clean($dataRow['model'] ?? '');
                        $cpu    = $clean($dataRow['cpu'] ?? '');
                        $ram    = strtoupper($clean($dataRow['ram'] ?? ''));
                        $disk   = strtoupper($clean($dataRow['disk'] ?? ''));

                        // limpiar serial
                        $serial = $clean($dataRow['serial'] ?? '');
                        $serial = preg_replace('/^serie:\s*/i', '', $serial);
                        $serial = $serial ?: null;

                        // 🔥 GUARDADO CORRECTO (fix duplicados)
                        if ($serial) {

                            \App\Models\Asset::updateOrCreate(
                                ['serial' => $serial],
                                [
                                    'device' => $device,
                                    'brand'  => $brand,
                                    'model'  => $model,
                                    'cpu'    => $cpu ?: null,
                                    'ram'    => $ram ?: null,
                                    'disk'   => $disk ?: null,
                                    'status' => 'available',
                                ]
                            );

                        } else {

                            \App\Models\Asset::create([
                                'device' => $device,
                                'brand'  => $brand,
                                'model'  => $model,
                                'cpu'    => $cpu ?: null,
                                'ram'    => $ram ?: null,
                                'disk'   => $disk ?: null,
                                'serial' => null,
                                'status' => 'available',
                            ]);
                        }
                    }

                    fclose($file);
                }),

        ];
    }
}