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
    $data = $this->form->getState();

    // Variables para la plantilla
    $variables = [
        'person_name' => $this->record->name,
        'email'       => $this->record->email,
        'asset'       => $this->record->assignments
            ->map(fn ($a) =>
                $a->asset->device . ' - ' .
                $a->asset->brand . ' - ' .
                $a->asset->model
            )
            ->implode("\n"),
        'date' => now()->format('d/m/Y'),
    ];

    // Nuevo ingreso
    if ($data['onboarding_completed'] ?? false) {
        \App\Services\MailTemplateService::send(
            'onboarding_completed',
            $variables
            
        );
    }

    // Asignación de equipo
    if ($data['asset_assignment'] ?? false) {
        \App\Services\MailTemplateService::send(
            'asset_assignment',
            $variables
            
            
        );
    }
}

    /**
     * Después de guardar, volver al listado de Gente.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}