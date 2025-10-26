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
}

