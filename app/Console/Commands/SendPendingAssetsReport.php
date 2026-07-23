<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\MailTemplate;
use App\Services\MailTemplateService;
use Illuminate\Console\Command;

class SendPendingAssetsReport extends Command
{
    protected $signature = 'mail:pending-assets-report';

    protected $description = 'Envía el reporte de equipos pendientes de devolución según la frecuencia configurada.';

    public function handle(): void
    {
        $template = MailTemplate::where('code', 'pending_assets_report')
            ->where('is_active', true)
            ->whereNotNull('schedule_frequency')
            ->first();

        if (! $template) {
            $this->info('No hay plantilla de reporte configurada o está inactiva.');
            return;
        }

        // Verificar si corresponde enviar según frecuencia
        if (! $this->shouldSend($template)) {
            $this->info('Aún no corresponde enviar (frecuencia: ' . $template->schedule_frequency . ').');
            return;
        }

        // Obtener datos de equipos pendientes
        $pendingAssets = Asset::where('status', 'in_transit')
            ->with(['assignments' => fn ($q) => $q->withTrashed()->latest()->with('person')])
            ->get();

        if ($pendingAssets->isEmpty()) {
            $this->info('No hay equipos pendientes. No se envía reporte.');
            return;
        }

        // ─── LISTA EQUIPOS: nombre + equipos asignados ───
        $byPerson = $pendingAssets->groupBy(fn ($asset) =>
            $asset->assignments->first()?->person?->name ?? 'Sin usuario'
        );

        $pendingList = $byPerson->map(function ($assets, $personName) {
            $equipos = $assets->map(fn ($a) => "  - {$a->full_description}")->implode('<br>');
            return "<b>{$personName}</b><br>{$equipos}";
        })->implode('<br><br>');

        // ─── CANT. PENDIENTES: lista con días de atraso o coordinación ───
        $pendingCount = $byPerson->map(function ($assets, $personName) {
            $person = $assets->first()->assignments->first()?->person;
            $days = $person?->updated_at ? (int) $person->updated_at->diffInDays(now()) : 0;

            // Buscar si tiene shipment
            $process = $person ? \App\Models\ReturnProcess::where('person_id', $person->id)->latest()->first() : null;
            $shipment = $process ? \App\Models\ReturnShipment::where('return_process_id', $process->id)->latest()->first() : null;

            if ($shipment && $shipment->logistics_method === 'enviopack') {
                return "• {$personName} — Coordinado EnvíoPack ({$shipment->tracking_number})";
            } elseif ($shipment && $shipment->logistics_method === 'moto') {
                return "• {$personName} — Coordinado con moto";
            } else {
                return "• {$personName} — {$days} días sin coordinar";
            }
        })->implode('<br>');

        $variables = [
            'pending_count' => $pendingCount,
            'pending_list' => $pendingList,
            'date' => now()->format('d/m/Y'),
        ];

        $sent = MailTemplateService::send(
            'pending_assets_report',
            $variables,
            $template->schedule_to
        );

        if ($sent) {
            $template->update(['last_sent_at' => now()]);
            $this->info("Reporte enviado a {$template->schedule_to} ({$pendingAssets->count()} equipos pendientes).");
        } else {
            $this->error('Error al enviar el reporte.');
        }
    }

    /**
     * Determina si corresponde enviar según la frecuencia configurada.
     */
    protected function shouldSend(MailTemplate $template): bool
    {
        $lastSent = $template->last_sent_at;
        $frequency = $template->schedule_frequency;

        // Si nunca se envió, enviar ahora
        if (! $lastSent) {
            return true;
        }

        $daysSince = (int) $lastSent->diffInDays(now());

        return match ($frequency) {
            'daily_1' => $daysSince >= 1,
            'daily_2' => $daysSince >= 2,
            'daily_3' => $daysSince >= 3,
            'daily_4' => $daysSince >= 4,
            'daily_5' => $daysSince >= 5,
            'weekly' => $daysSince >= 7 && now()->isMonday(),
            'biweekly' => $daysSince >= 14 && now()->isMonday(),
            'triweekly' => $daysSince >= 21 && now()->isMonday(),
            default => false,
        };
    }
}
