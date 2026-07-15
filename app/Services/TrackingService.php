<?php

namespace App\Services\Tracking;

class TrackingService
{
    public function track(
        string $carrier,
        string $tracking
    ): array {

        return [

            'status' => 'En tránsito',

            'last_update' => now(),

            'events' => [

                [
                    'date' => now()->subDays(2),
                    'status' => 'Retirado del domicilio',
                ],

                [
                    'date' => now()->subDay(),
                    'status' => 'En planta Rosario',
                ],

                [
                    'date' => now(),
                    'status' => 'En tránsito',
                ],

            ],

        ];

    }
}