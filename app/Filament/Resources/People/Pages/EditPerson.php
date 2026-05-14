<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditPerson extends EditRecord
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Volver'),

            DeleteAction::make()
                ->label('Borrar Agente'),
        ];
    }

    /**
     * Se ejecuta después de guardar los cambios.
     */
    protected function afterSave(): void
{
    $sendEmail = $this->form->getComponent('send_email')?->getState();

    if (! $sendEmail) {
        return;
    }

    // Obtener todos los activos asignados a la persona
    $assets = $this->record->assignments()
        ->with('asset')
        ->get()
        ->map(function ($assignment) {
            $asset = $assignment->asset;

            if (! $asset) {
                return null;
            }

            return '- ' . $asset->device .
                   ' - ' . $asset->brand .
                   ' - ' . $asset->model .
                   (!empty($asset->serial) ? ' - ' . $asset->serial : '');
        })
        ->filter()
        ->implode("\n");

    // Enviar correo
    \App\Services\MailTemplateService::send('onboarding_completed', [
        'person_name' => $this->record->name,
        'asset'       => $assets,
        'date'        => now()->format('d/m/Y'),
    ]);
}

    /**
     * Después de guardar, volver al listado de Gente.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}