<?php

namespace App\Services;

use Spatie\Activitylog\Models\Activity;

class ActivityLogger
{
    public static function log(string $module, string $description, $subject = null): void
    {
        $logger = activity($module)
            ->causedBy(auth()->user());

        if ($subject) {
            $logger->performedOn($subject);
        }

        $logger->log($description);
    }

    public static function facturacion(string $description, $subject = null): void
    {
        self::log('facturacion', $description, $subject);
    }

    public static function activos(string $description, $subject = null): void
    {
        self::log('activos', $description, $subject);
    }

    public static function personas(string $description, $subject = null): void
    {
        self::log('personas', $description, $subject);
    }

    public static function offboarding(string $description, $subject = null): void
    {
        self::log('offboarding', $description, $subject);
    }

    public static function sistema(string $description, $subject = null): void
    {
        self::log('sistema', $description, $subject);
    }
}
