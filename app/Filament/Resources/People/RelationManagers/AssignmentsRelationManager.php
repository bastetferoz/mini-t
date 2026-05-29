<?php

namespace App\Filament\Resources\People\RelationManagers;

use App\Models\Asset;
use App\Models\AssetHistory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('asset_id')
                ->label('Activo')
                ->searchable()
                ->preload()
                ->getSearchResultsUsing(function (string $search) {
                    return Asset::query()
                        ->where('status', 'available')
                        ->when($search, function ($query) use ($search) {
                            $query->where(function ($q) use ($search) {
                                $q->where('device', 'like', "%{$search}%")
                                  ->orWhere('model', 'like', "%{$search}%")
                                  ->orWhere('serial', 'like', "%{$search}%");
                            });
                        })
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(function ($asset) {
                            return [
                                $asset->id => "{$asset->device} - {$asset->brand} - {$asset->model} - {$asset->serial}"
                            ];
                        });
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    $asset = Asset::find($value);

                    if (!$asset) {
                        return null;
                    }

                    return "{$asset->device} - {$asset->brand} - {$asset->model} - {$asset->serial}";
                })
                ->required(),
        ]);
    }

    public function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn ($query) => 
            in_array($this->getOwnerRecord()->status, ['offboarding', 'inactive'])
                ? $query->withTrashed()
                : $query
        )
        ->columns([
            TextColumn::make('asset.device')->label('Dispositivo'),
            TextColumn::make('asset.brand')->label('Marca'),
            TextColumn::make('asset.model')->label('Modelo'),

            TextColumn::make('asset.processor')->label('CPU')->toggleable(),
            TextColumn::make('asset.ram')->label('RAM')->toggleable(),
            TextColumn::make('asset.disk')->label('Disco')->toggleable(),

            TextColumn::make('asset.serial')->label('Nº Serie'),
        ])

            ->headerActions([
    CreateAction::make()
        ->label('Asignar equipo')
        ->color('success')
        ->icon('heroicon-o-plus')

        ->modalHeading('Asignar equipo')
        ->modalSubmitActionLabel('Asignar')
        ->createAnother(true)

        ->form([
            Select::make('asset_id')
                ->label('Equipo')
                ->searchable()
                ->preload()
                ->options(fn () =>
                    \App\Models\Asset::where('status', 'available')
                        ->get()
                        ->mapWithKeys(fn ($asset) => [
                            $asset->id => "{$asset->device} - {$asset->brand} - {$asset->model} - {$asset->serial}"
                        ])
                )
                ->required(),
        ])

        // 🔥 ESTO ES LA CLAVE
        ->after(function ($record) {

            \App\Models\Asset::where('id', $record->asset_id)
                ->update(['status' => 'assigned']);
        }),
])

            ->recordActions([

                // 🔴 DESASIGNAR
                Action::make('desasignar')
    ->label('Desasignar')
    ->visible(fn () =>
        auth()->user()->hasRole('admin') ||
        auth()->user()->hasRole('it')
    )
    ->color('warning')
    ->form([
        Select::make('reason')
            ->label('Motivo')
            ->options([
                'upgrade' => 'Upgrade',
                'fault' => 'Avería',
                'return' => 'Devolución',
            ])
            ->required(),

        Textarea::make('notes')
            ->label('Observación'),

        Toggle::make('send_email')
            ->label('Enviar notificación por correo')
            ->default(true),
    ])
    ->action(function ($record, $data) {

        // Guardar referencias antes de borrar el assignment
        $asset = $record->asset;
        $person = $record->person;

        AssetHistory::create([
            'asset_id'  => $record->asset_id,
            'person_id' => $record->person_id,
            'action'    => $data['reason'],
            'notes'     => $data['notes'] ?? null,
        ]);

        if ($data['reason'] === 'fault') {
            Asset::where('id', $record->asset_id)
                ->update(['status' => 'retired']);
        } else {
            Asset::where('id', $record->asset_id)
                ->update(['status' => 'available']);
        }

        $record->delete();

        // 📧 Enviar correo usando la plantilla asset_return
        if (!empty($data['send_email'])) {
            \App\Services\MailTemplateService::send('asset_return', [
                'person_name' => $person->name,
                  'asset' => $asset->full_description,
                'date'        => now()->format('d/m/Y'),
            ]);
        }
    }),
               // 🔵 REEMPLAZAR
Action::make('reemplazar')
    ->label('Reemplazar')
    ->visible(fn () =>
        auth()->user()->hasRole('admin') ||
        auth()->user()->hasRole('it')
    )
    ->color('primary')

    ->form([

        Select::make('new_asset_id')
            ->label('Nuevo equipo')
            ->searchable()
            ->preload()

            ->getSearchResultsUsing(function (string $search) {

                return Asset::query()

                     ->where('status', 'available')
                        ->when($search, function ($query) use ($search) {
                            $query->where(function ($q) use ($search) {
                                $q->where('device', 'like', "%{$search}%")
                                  ->orWhere('model', 'like', "%{$search}%")
                                  ->orWhere('serial', 'like', "%{$search}%");
                            });

                    })

                    ->limit(50)

                    ->get()

                    ->mapWithKeys(fn ($asset) => [
                        $asset->id =>
                            "{$asset->device} - {$asset->brand} - {$asset->model} - {$asset->serial}"
                    ]);
            })

            ->getOptionLabelUsing(function ($value): ?string {

                $asset = Asset::find($value);

                if (! $asset) {
                    return null;
                }

                return "{$asset->device} - {$asset->brand} - {$asset->model} - {$asset->serial}";
            })

            ->required(),

        // 🔥 MOTIVO
        Select::make('reason')
            ->label('Motivo')
            ->required()
            ->options([
                'upgrade'     => 'Upgrade',
                'failure'     => 'Avería',
                'replacement' => 'Reemplazo preventivo',
                'loan'        => 'Préstamo',
            ]),

        // 🔥 OBSERVACIÓN
        Textarea::make('notes')
            ->label('Motivo / Observación')
            ->rows(3),

        // 🔥 EMAIL
        Toggle::make('send_email')
            ->label('Enviar notificación por correo')
            ->default(true),

    ])

    ->action(function ($record, $data) {

        // 🚫 evitar mismo equipo
        if ($record->asset_id == $data['new_asset_id']) {
            return;
        }

        $oldAsset = Asset::find($record->asset_id);
        $newAsset = Asset::find($data['new_asset_id']);
        $person   = $record->person;

        // 📝 Historial
        AssetHistory::create([
            'asset_id'  => $record->asset_id,
            'person_id' => $record->person_id,
            'action'    => $data['reason'],
            'notes'     => $data['notes'] ?? 'Reemplazo de equipo',
        ]);

        // 🔓 Liberar o retirar viejo según motivo
if ($data['reason'] === 'failure') {

    Asset::where('id', $record->asset_id)
        ->update(['status' => 'retired']);

} else {

    Asset::where('id', $record->asset_id)
        ->update(['status' => 'available']);

}

        // 🔁 Asignar nuevo
        $record->update([
            'asset_id' => $data['new_asset_id'],
        ]);

        // 🔒 Nuevo en uso
        Asset::where('id', $data['new_asset_id'])
            ->update(['status' => 'assigned']);

        // 📧 Correo
        if (! empty($data['send_email'])) {

            \App\Services\MailTemplateService::send(
                'asset_replacement',
                [
                    'person_name' => $person->name,

                    'old_asset' => $oldAsset->full_description,

    'new_asset' => $newAsset->full_description,

                    'reason' => $data['reason'],

                    'date' => now()->format('d/m/Y'),
                ]
            );
        }
    }),
    
            ]);
            activity()
    ->causedBy(auth()->user())
    ->log("Reemplazo de equipo para {$person->name}: {$oldLabel} → {$newLabel}");
    }
}