<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnProcess extends Model
{
    protected $fillable = [
        'person_id',
        'requested_by',
        'status',
        'notes',

        // Confirmación RRHH
        'rrhh_confirmed_at',
        'rrhh_confirmed_by',

        // Confirmación IT/Admin
        'it_confirmed_at',
        'it_confirmed_by',
    ];

    /**
     * La baja queda finalizada cuando RRHH e IT confirmaron.
     */
    public function isCompleted(): bool
    {
        return !is_null($this->rrhh_confirmed_at)
            && !is_null($this->it_confirmed_at);
    }

    /**
     * Relación con la persona.
     */
    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Usuario que solicitó la baja.
     */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Usuario de RRHH que confirmó.
     */
    public function rrhhConfirmedBy()
    {
        return $this->belongsTo(User::class, 'rrhh_confirmed_by');
    }

    /**
     * Usuario de IT/Admin que confirmó.
     */
    public function itConfirmedBy()
    {
        return $this->belongsTo(User::class, 'it_confirmed_by');
    }

    /**
     * Activos incluidos en el proceso de devolución.
     */
    public function assets()
    {
        return $this->belongsToMany(
            Asset::class,
            'return_process_asset',
            'return_process_id',
            'asset'
        )->withPivot(['returned', 'reason', 'notes']);
    }

    /**
     * Envíos asociados a esta devolución.
     */
    public function shipments()
    {
        return $this->hasMany(ReturnShipment::class);
    }
}
