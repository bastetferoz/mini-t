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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportAssignments extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected string $view = 'filament.pages.import-assignments';

    protected static string | \UnitEnum | null $navigationGroup = 'Utilidades';

    protected static ?string $title = 'Importar asignaciones';

    public ?array $data = [];

    public ?array $backupData = [];

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

    public function backupForm(Schema $schema): Schema
    {
        return $schema
            ->components([

                FileUpload::make('backup_file')
                    ->label('Archivo SQL de backup')
                    ->required()
                    ->acceptedFileTypes([
                        'application/sql',
                        'text/plain',
                        'application/octet-stream',
                    ]),

            ])
            ->statePath('backupData');
    }

    /*
    |--------------------------------------------------------------------------
    | Exportar Backup
    |--------------------------------------------------------------------------
    */

    public function exportBackup(): StreamedResponse
    {
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $filename = 'backup_' . $database . '_' . now()->format('Y-m-d_His') . '.sql';

        return response()->streamDownload(function () use ($host, $port, $database, $username, $password) {
            $command = sprintf(
                'mysqldump -h%s -P%s -u%s -p%s %s 2>/dev/null',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database)
            );

            passthru($command);
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Importar Backup
    |--------------------------------------------------------------------------
    */

    public function importBackup(): void
    {
        $file = collect($this->backupData['backup_file'])->first();

        if (! $file) {
            Notification::make()
                ->title('Archivo inválido')
                ->danger()
                ->send();

            return;
        }

        $path = $file->getRealPath();

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $command = sprintf(
            'mysql -h%s -P%s -u%s -p%s %s < %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($path)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            Notification::make()
                ->title('Error al importar backup')
                ->body(implode("\n", $output))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Backup importado correctamente')
            ->success()
            ->send();
    }

    protected function getForms(): array
    {
        return [
            'form',
            'backupForm',
        ];
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