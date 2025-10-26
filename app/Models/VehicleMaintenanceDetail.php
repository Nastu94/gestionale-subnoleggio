<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenanceDetail extends Model
{
    protected $table = 'vehicle_maintenance_details';

    protected $fillable = [
        'vehicle_state_id',
        'workshop',
        'cost_cents',
        'currency',
        'notes',
    ];

    protected $casts = [
        'cost_cents' => 'integer',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(VehicleState::class, 'vehicle_state_id');
    }
}
