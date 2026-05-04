<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Select;

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

                    // 🔥 SOLO PASAMOS LOS EQUIPOS A EN TRANSITO
                    foreach ($record->assignments as $assignment) {

                        \App\Models\Asset::where('id', $assignment->asset_id)
                            ->update([
                                'status' => 'in_transit'
                            ]);
                    }

                    // 🔥 OPCIONAL (recomendado)
                    // estado intermedio
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

                    CheckboxList::make('assets')
                        ->label('Equipos que volvieron')
                        ->options(fn ($record) =>
                            $record->assignments
                                ->filter(fn ($a) => $a->asset->status === 'in_transit')
                                ->mapWithKeys(fn ($a) => [
                                    $a->asset->id =>
                                        "{$a->asset->device} - {$a->asset->serial}"
                                ])
                        ),

                    Select::make('motivo')
                        ->label('Motivo de los no devueltos')
                        ->options([
                            'ausente' => 'Ausente',
                            'roto' => 'Roto',
                            'incompleto' => 'Incompleto',
                        ])
                        ->required()

                ])

                ->action(function ($record, $data) {

                    $selected = $data['assets'] ?? [];
                    $motivo = $data['motivo'] ?? 'sin especificar';

                    foreach ($record->assignments as $assignment) {

                        $asset = $assignment->asset;

                        if ($asset->status !== 'in_transit') {
                            continue;
                        }

                        if (in_array($asset->id, $selected)) {

                            // ✔ DEVUELTO
                            $asset->update([
                                'status' => 'available'
                            ]);

                            \App\Models\AssetHistory::create([
                                'asset_id' => $asset->id,
                                'action' => 'Devuelto',
                                'notes' => 'Ex ' . $record->name,
                            ]);

                        } else {

                            // ❌ NO DEVUELTO
                            $asset->update([
                                'status' => 'retired'
                            ]);

                            \App\Models\AssetHistory::create([
                                'asset_id' => $asset->id,
                                'action' => 'No devuelto',
                                'notes' => $motivo, // 👈 más limpio
                            ]);
                        }
                    }

                    // 🔥 RECIÉN ACÁ SE COMPLETA LA BAJA
                    $record->update([
                        'status' => 'inactive'
                    ]);
                }),
        ];
    }
}