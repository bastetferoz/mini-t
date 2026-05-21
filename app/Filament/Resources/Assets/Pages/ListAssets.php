<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Resources\Pages\ListRecords;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
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
                        ->acceptedFileTypes(['text/csv']),
                ])

                ->action(function ($data) {

                    if (! isset($data['file']) || ! Storage::exists($data['file'])) {
                        return;
                    }

                    $path = Storage::path($data['file']);
                    $file = fopen($path, 'r');

                    if (! $file) {
                        return;
                    }

                    // Detectar delimitador real
                    $firstLine = fgets($file);

                    if (substr_count($firstLine, "\t") > 1) {
                        $delimiter = "\t";
                    } elseif (substr_count($firstLine, ';') > 1) {
                        $delimiter = ';';
                    } else {
                        $delimiter = ',';
                    }

                    rewind($file);

                    // Leer encabezado
                    $header = fgetcsv($file, 0, $delimiter);

                    // Fix BOM (Excel/Sheets agrega caracteres invisibles)
                    if ($header && isset($header[0])) {
                        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                    }

                    $header = array_map(
                        fn ($h) => strtolower(trim($h)),
                        $header
                    );

                    // Control de duplicados
                    $serialsInFile = [];
                    $duplicatesInFile = [];
                    $duplicatesInDatabase = [];

                    while (($row = fgetcsv($file, 0, $delimiter)) !== false) {

                        if (empty(array_filter($row))) {
                            continue;
                        }

                        // Asegurar tamaño correcto
                        $row = array_pad($row, count($header), null);

                        $dataRow = array_combine($header, $row);

                        // Limpieza general
                        $clean = fn ($v) => preg_replace('/\s+/u', ' ', trim($v ?? ''));

                        $device = $clean($dataRow['device'] ?? '');
                        $brand  = ucfirst(strtolower($clean($dataRow['brand'] ?? '')));
                        $model  = $clean($dataRow['model'] ?? '');
                        $cpu    = $clean($dataRow['cpu'] ?? '');
                        $ram    = strtoupper($clean($dataRow['ram'] ?? ''));
                        $disk   = strtoupper($clean($dataRow['disk'] ?? ''));

                        // Limpiar serial
                        $serial = $clean($dataRow['serial'] ?? '');
                        $serial = preg_replace('/^serie:\s*/i', '', $serial);
                        $serial = $serial ?: null;

                        /*
                         |--------------------------------------------------------------------------
                         | Validar duplicados dentro del mismo archivo
                         |--------------------------------------------------------------------------
                         */
                        if ($serial) {
                            if (in_array($serial, $serialsInFile, true)) {
                                $duplicatesInFile[] = $serial;
                                continue; // No importar esta fila
                            }

                            $serialsInFile[] = $serial;
                        }

                        /*
                         |--------------------------------------------------------------------------
                         | Si existe serial:
                         | - Actualiza el activo
                         | - Recalcula el estado
                         |--------------------------------------------------------------------------
                         */
                        if ($serial) {

                            $alreadyExists = Asset::where('serial', $serial)->exists();

                            if ($alreadyExists) {
                                $duplicatesInDatabase[] = $serial;
                            }

                            $asset = Asset::updateOrCreate(
                                ['serial' => $serial],
                                [
                                    'device' => $device,
                                    'brand'  => $brand,
                                    'model'  => $model,
                                    'cpu'    => $cpu ?: null,
                                    'ram'    => $ram ?: null,
                                    'disk'   => $disk ?: null,
                                ]
                            );

                            // Si no está retirado, recalcular estado real
                            if ($asset->status !== 'retired') {
                                $asset->status = $asset->assignments()->exists()
                                    ? 'assigned'
                                    : 'available';

                                $asset->save();
                            }

                        } else {
                            /*
                             |--------------------------------------------------------------------------
                             | Sin serial: siempre crear nuevo
                             |--------------------------------------------------------------------------
                             */
                            Asset::create([
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

                    /*
                     |--------------------------------------------------------------------------
                     | Mostrar resultado de la importación
                     |--------------------------------------------------------------------------
                     */
                    $duplicatesInFile = array_unique($duplicatesInFile);
                    $duplicatesInDatabase = array_unique($duplicatesInDatabase);

                    if (! empty($duplicatesInFile) || ! empty($duplicatesInDatabase)) {

                        $message = '';

                        if (! empty($duplicatesInFile)) {
                            $message .= 'Duplicados dentro del archivo (no importados): '
                                . implode(', ', $duplicatesInFile) . '. ';
                        }

                        if (! empty($duplicatesInDatabase)) {
                            $message .= 'Seriales existentes en la base (actualizados, no duplicados): '
                                . implode(', ', $duplicatesInDatabase) . '.';
                        }

                        Notification::make()
                            ->title('Importación finalizada con observaciones')
                            ->warning()
                            ->body($message)
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Importación completada')
                            ->success()
                            ->body('No se detectaron números de serie duplicados.')
                            ->send();
                    }
                }),

        ];
    }
}