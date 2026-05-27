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
            EditAction::make()
                ->visible(fn () =>
                    auth()->user()->hasRole('admin') ||
                    auth()->user()->hasRole('it')
                ),

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
                ->form([])
                ->modalHeading('Activos asignados')
                ->modalDescription(fn ($record) =>
                    new HtmlString(
                        $record->assignments->map(fn ($a) =>
                            "<div>• {$a->asset->device} - {$a->asset->brand} - {$a->asset->model} - {$a->asset->serial}</div>"
                        )->implode('')
                        . (
                            !empty($record->services)
                                ? '<div class="mt-6 pt-4 border-t border-gray-700">'
                                    . '<br>'
                                    . '<strong>Se dio de baja en:</strong><br>'
                                    . collect($record->services)
                                        ->map(function ($service) {
                                            return '• ' . match ($service) {
                                                'jira'             => 'Jira',
                                                'bitbucket'        => 'Bitbucket',
                                                'monday'           => 'Monday',
                                                'microsoft_365'    => 'Microsoft 365',
                                                'teams'            => 'Teams',
                                                'groups'           => 'Grupos',
                                                'slack'            => 'Slack',
                                                'github'           => 'GitHub',
                                                'google_workspace' => 'Google Workspace',
                                                'zoom'             => 'Zoom',
                                                'trello'           => 'Trello',
                                                default            => ucfirst(str_replace('_', ' ', $service)),
                                            };
                                        })
                                        ->implode('<br>')
                                    . '</div>'
                                : ''
                        )
                    )
                )
                ->action(function ($record, $data) {

                    $assignments = $record->assignments()->whereNull('deleted_at')->get();
                    $assetIds = $assignments->pluck('asset_id');

                    foreach ($assetIds as $assetId) {
                        \App\Models\Asset::where('id', $assetId)
                            ->update(['status' => 'in_transit']);
                    }

                    $record->assignments()->delete();

                    $record->update([
    'status' => 'offboarding',
    'offboarding_started_at' => now(),
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
                                    'ausente'    => 'Ausente',
                                    'roto'       => 'Roto',
                                    'incompleto' => 'Incompleto',
                                ])
                                ->visible(fn ($get) => !$get('devuelto'))
                                ->nullable(),
                            Textarea::make('comentario')
                                ->label('Comentario')
                                ->rows(2)
                                ->nullable(),
                        ])
                        ->default(fn ($record) =>
                            \App\Models\Asset::where('status', 'in_transit')
                                ->whereHas('assignments', fn($q) => $q->where('person_id', $record->id)->withTrashed())
                                ->get()
                                ->map(fn ($a) => [
                                    'asset_id' => $a->id,
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
                            $asset->update(['status' => 'available']);
                            \App\Models\AssetHistory::create([
                                'asset_id'  => $asset->id,
                                'person_id' => $record->id,
                                'action'    => 'Devuelto',
                                'notes'     => trim($item['comentario'] ?? ''),
                            ]);
                        } else {
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

                    $record->update([
    'status' => 'inactive',
    'offboarding_completed_at' => now(),
]);

                    // 📧 ENVIAR CORREO CON RESUMEN
                    $resumen = collect($assets)->map(function ($item) {
                        $asset = \App\Models\Asset::find($item['asset_id']);
                        if (!$asset) return null;

                        $linea = "• {$asset->device} - {$asset->brand} - {$asset->model}";

                        if ($item['devuelto']) {
                            $linea .= " → Devuelto";
                            if (!empty($item['comentario'])) {
                                $linea .= " ({$item['comentario']})";
                            }
                        } else {
                            $motivo = $item['motivo'] ?? 'sin especificar';
                            $linea .= " → No devuelto ({$motivo})";
                            if (!empty($item['comentario'])) {
                                $linea .= " - {$item['comentario']}";
                            }
                        }

                        return $linea;
                    })->filter()->implode("<br>");

                    \App\Services\MailTemplateService::send('offboarding_completed', [
                        'person_name' => $record->name,
                        'asset'       => $resumen,
                        'date'        => now()->format('d/m/Y'),
                        'email'       => $record->email,
                    ]);

                    return redirect()->to(
                        \App\Filament\Resources\People\PersonResource::getUrl('index')
                    );
                }),
        ];
    }
}