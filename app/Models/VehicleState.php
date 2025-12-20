<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: VehicleState
 * - Log degli stati: available | assigned | rented | maintenance | blocked
 * - ended_at NULL = stato corrente.
 */
class VehicleState extends Model
{
    use HasFactory;

    /**
     * Mappa “UI-only” per mostrare in italiano gli stati del veicolo.
     *
     * Nota: includo anche 'out_of_service' perché nel codice lo usi nei filtri
     * (states in ['maintenance','out_of_service']).
     *
     * @var array<string,string>
     */
    public const STATE_LABELS_IT = [
        'available'      => 'Disponibile',
        'assigned'       => 'Assegnato',
        'rented'         => 'Noleggiato',
        'maintenance'    => 'Manutenzione',
        'blocked'        => 'Bloccato',
        'out_of_service' => 'Fuori servizio',
    ];

    protected $fillable = [
        'vehicle_id','state','started_at','ended_at','reason','created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function vehicle()   { return $this->belongsTo(Vehicle::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function maintenanceDetail() { return $this->hasOne(VehicleMaintenanceDetail::class, 'vehicle_state_id'); }

    /**
     * Accessor: etichetta italiana dello stato veicolo.
     * Uso: {{ $state->state_label }}
     */
    public function getStateLabelAttribute(): string
    {
        $state = (string) ($this->state ?? '');

        return self::STATE_LABELS_IT[$state] ?? $state;
    }
}

