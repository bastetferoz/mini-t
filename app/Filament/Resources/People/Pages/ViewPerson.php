<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;

class ViewPerson extends ViewRecord
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [

            // ✏️ EDITAR
            EditAction::make(),

            // 🔴 SOLICITAR BAJA
            Action::make('baja')
                ->label('Solicitar baja')
                ->color('danger')
                ->modalSubmitActionLabel('Confirmar baja')
                ->modalCancelActionLabel('Cancelar')

                ->visible(fn ($record) =>
                    ($record->status ?? 'active') === 'active' &&
                    (
                        auth()->user()->hasRole('admin') ||
                        auth()->user()->hasRole('it')
                    )
                )

                ->modalHeading('Activos asignados')
                ->modalDescription(fn ($record) =>
                    new HtmlString(
                        $record->assignments->map(fn ($a) =>
                            "<div>• {$a->asset->device} - {$a->asset->brand} - {$a->asset->model} - {$a->asset->serial}</div>"
                        )->implode('')
                    )
                )

                ->action(function ($record) {

                    foreach ($record->assignments as $assignment) {

                        \App\Models\Asset::where('id', $assignment->asset_id)
                            ->update([
                                'status' => 'in_transit'
                            ]);
                    }

                    $record->update([
                        'status' => 'offboarding'
                    ]);
                }),

            // 🟢 REGISTRAR RECEPCIÓN
            Action::make('recibir')
                ->label('Registrar recepción')
                ->color('success')

                ->visible(fn ($record) =>
                    in_array(($record->status ?? 'active'), ['offboarding', 'inactive']) &&
                    (
                        auth()->user()->hasRole('admin') ||
                        auth()->user()->hasRole('it')
                    )
                )

                ->form([

    Repeater::make('assets')
        ->label('Recepción de equipos')
        ->schema([

            Hidden::make('asset_id'),

            Placeholder::make('equipo')
                ->label('Equipo')
                ->content(fn ($get) =>
                    optional(\App\Models\Asset::find($get('asset_id')))
                        ?->device . ' - ' .
                    optional(\App\Models\Asset::find($get('asset_id')))
                        ?->serial
                ),

            Toggle::make('devuelto')
                ->label('¿Devuelto?')
                ->reactive(),

            Select::make('motivo')
                ->label('Motivo')
                ->options([
                    'ausente' => 'Ausente',
                    'roto' => 'Roto',
                    'incompleto' => 'Incompleto',
                ])
                ->visible(fn ($get) => !$get('devuelto')) // 🔥 clave
                ->nullable(),

            Textarea::make('comentario')
                ->label('Comentario')
                ->rows(2)
                ->visible(fn ($get) => !$get('devuelto')) // 🔥 clave
                ->nullable(),

        ])

        ->default(fn ($record) =>
            $record->assignments
                ->filter(fn ($a) => $a->asset->status === 'in_transit')
                ->map(fn ($a) => [
                    'asset_id' => $a->asset->id,
                    'devuelto' => false,
                ])
                ->values()
                ->toArray()
        )
])

                ->action(function ($record, $data) {

    $assets = $data['assets'] ?? [];

    foreach ($assets as $item) {

        $asset = \App\Models\Asset::find($item['asset_id']);

        if (!$asset || $asset->status !== 'in_transit') {
            continue;
        }

        if ($item['devuelto']) {

            // ✔ DEVUELTO
            $asset->update(['status' => 'available']);

            \App\Models\AssetHistory::create([
                'asset_id' => $asset->id,
                'action'   => 'Devuelto',
                'notes'    => 'Ex ' . $record->name,
            ]);

        } else {

            // ❌ NO DEVUELTO
            $motivo     = $item['motivo'] ?? 'sin especificar';
            $comentario = $item['comentario'] ?? '';

            $asset->update(['status' => 'retired']);

           \App\Models\AssetHistory::create([
    'asset_id'  => $asset->id,
    'person_id' => $record->id,
    'action'    => 'No devuelto',
    'notes'     => trim($motivo . ($comentario ? ' - ' . $comentario : '')),
]);
        }
    }

    $record->update(['status' => 'inactive']);
}),
        ];
    }
}