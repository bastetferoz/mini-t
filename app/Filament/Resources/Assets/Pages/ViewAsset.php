<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use App\Models\AssetHistory;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
{
    return [

        EditAction::make(),

        Action::make('reactivate')
            ->label('Reactivar equipo')
            ->icon('heroicon-o-arrow-path')
            ->color('success')

            ->visible(fn () =>
                $this->record->status === 'retired'
            )

            ->form([
                Textarea::make('notes')
                    ->label('Motivo de la reactivación')
                    ->required()
                    ->rows(4),
            ])

            ->action(function (array $data) {

                $this->record->update([
                    'status' => 'available',
                ]);

                AssetHistory::create([
                    'asset_id' => $this->record->id,
                    'person_id' => null,
                    'action' => 'reactivated',
                    'notes' => $data['notes'],
                ]);
            }),
    ];
}
}
