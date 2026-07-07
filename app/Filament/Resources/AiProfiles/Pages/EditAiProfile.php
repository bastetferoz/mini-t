<?php

namespace App\Filament\Resources\AiProfiles\Pages;

use App\Filament\Resources\AiProfiles\AiProfileResource;
use App\Models\AiProfile;
use Filament\Resources\Pages\EditRecord;

class EditAiProfile extends EditRecord
{
    protected static string $resource = AiProfileResource::class;

    protected function afterSave(): void
    {
        // Si se marcó como default, quitar default de los demás
        if ($this->record->is_default) {
            AiProfile::where('id', '!=', $this->record->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
