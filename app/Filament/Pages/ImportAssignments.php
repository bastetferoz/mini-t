<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Person;
use App\Models\AssetHistory;

use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;

use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

use Filament\Schemas\Schema;

class ImportAssignments extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected string $view = 'filament.pages.import-assignments';

    protected static string | \UnitEnum | null $navigationGroup = 'Utilidades';

    protected static ?string $title = 'Importar asignaciones';

    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                FileUpload::make('file')
                    ->label('Archivo CSV')
                    ->required()
                    ->acceptedFileTypes([
                        'text/csv',
                        'application/vnd.ms-excel',
                    ]),

            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [

            Action::make('import')
                ->label('Importar')
                ->submit('import'),

        ];
    }

    public function import(): void
    {
        $file = collect($this->data['file'])->first();

        if (! $file) {

            Notification::make()
                ->title('Archivo inválido')
                ->danger()
                ->send();

            return;
        }

        $path = $file->getRealPath();

        $handle = fopen($path, 'r');

        if (! $handle) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Detectar delimitador
        |--------------------------------------------------------------------------
        */

        $firstLine = fgets($handle);

        if (substr_count($firstLine, "\t") > 1) {

            $delimiter = "\t";

        } elseif (substr_count($firstLine, ";") > 1) {

            $delimiter = ";";

        } else {

            $delimiter = ",";
        }

        rewind($handle);

        /*
        |--------------------------------------------------------------------------
        | Leer encabezado
        |--------------------------------------------------------------------------
        */

        $header = fgetcsv($handle, 0, $delimiter);

        // 🔥 Fix BOM UTF-8
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $header = array_map(
            fn ($h) => strtolower(trim($h)),
            $header
        );

        /*
        |--------------------------------------------------------------------------
        | Procesar filas
        |--------------------------------------------------------------------------
        */

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {

            // ignorar filas vacías
            if (empty(array_filter($row))) {
                continue;
            }

            $row = array_pad($row, count($header), null);

            $data = array_combine($header, $row);

            $clean = fn ($v) => trim($v ?? '');

            /*
            |--------------------------------------------------------------------------
            | PERSONA
            |--------------------------------------------------------------------------
            */

            $personName = $clean($data['person'] ?? '');

            if (! $personName) {
                continue;
            }

            $person = Person::firstOrCreate([
                'name' => $personName,
            ]);

            /*
            |--------------------------------------------------------------------------
            | ASSET
            |--------------------------------------------------------------------------
            */

            $serial = $clean($data['serial'] ?? '');

            if ($serial) {

                $asset = Asset::firstOrCreate(

                    ['serial' => $serial],

                    [
                        'device' => $clean($data['device'] ?? ''),
                        'brand'  => $clean($data['brand'] ?? ''),
                        'model'  => $clean($data['model'] ?? ''),
                        'cpu'    => $clean($data['cpu'] ?? ''),
                        'ram'    => $clean($data['ram'] ?? ''),
                        'disk'   => $clean($data['disk'] ?? ''),
                        'status' => 'available',
                    ]
                );

            } else {

                // 🔥 Sin serial: crear siempre nuevo
                $asset = Asset::create([

                    'device' => $clean($data['device'] ?? ''),
                    'brand'  => $clean($data['brand'] ?? ''),
                    'model'  => $clean($data['model'] ?? ''),
                    'cpu'    => $clean($data['cpu'] ?? ''),
                    'ram'    => $clean($data['ram'] ?? ''),
                    'disk'   => $clean($data['disk'] ?? ''),
                    'serial' => null,
                    'status' => 'available',

                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            // evitar duplicados exactos
           $exists = Assignment::where('person_id', $person->id)
    ->where('asset_id', $asset->id)
    ->exists();

            if (! $exists) {

                Assignment::create([
    'person_id' => $person->id,
    'asset_id'  => $asset->id,
]);

                // marcar asset en uso
                $asset->status = 'assigned';
                $asset->save();

                // historial
                AssetHistory::create([
                    'asset_id'  => $asset->id,
                    'person_id' => $person->id,
                    'action'    => 'assignment',
                    'reason'    => 'import',
                ]);
            }
        }

        fclose($handle);

        Notification::make()
            ->title('Importación completada')
            ->success()
            ->send();
    }
}