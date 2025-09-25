<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: AssignmentConstraint
 * - Vincoli opzionali su un affidamento.
 */
class AssignmentConstraint extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id','max_km','min_driver_age','allowed_drivers','geo_fence','notes',
    ];

    protected $casts = [
        'max_km'         => 'integer',
        'min_driver_age' => 'integer',
        'allowed_drivers'=> 'array',
        'geo_fence'      => 'array',
    ];

    public function assignment() { return $this->belongsTo(VehicleAssignment::class, 'assignment_id'); }
}
