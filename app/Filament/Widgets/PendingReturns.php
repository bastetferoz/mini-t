<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Asset;

class PendingReturns extends Widget
{
    protected string $view = 'filament.widgets.pending-returns';

    protected int | string | array $columnSpan = 'full';

public function getGroups()
{
    return Asset::where('status', 'in_transit')
        ->with(['assignments' => fn($q) => $q->withTrashed()->latest()->with('person')])
        ->get()
        ->groupBy(function ($asset) {
            return optional($asset->assignments->first()?->person)->name ?? 'Sin usuario';
        });
}

public function getDias($assets): int
{
    $person = optional($assets->first()->assignments->first())->person;
    if (!$person || !$person->updated_at) return 0;
    return (int) $person->updated_at->diffInDays(now());
}
    public $selectedAssets = [];
public $currentPerson = null;

public function openModal($person)
{
    $this->currentPerson = $person;

    $this->dispatch('open-modal', id: 'procesar-devolucion');
}
}