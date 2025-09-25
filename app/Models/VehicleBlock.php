<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: VehicleBlock
 * - Blocchi calendario (maintenance, legal_hold, custom_block)
 * - Creati da Admin o Renter (organization_id).
 */
class VehicleBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id','organization_id','type','start_at','end_at','status','reason','created_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    public function vehicle()      { return $this->belongsTo(Vehicle::class); }
    public function organization() { return $this->belongsTo(Organization::class); }
    public function creator()      { return $this->belongsTo(User::class, 'created_by'); }

    // Scope: blocchi attivi su finestra temporale
    public function scopeOverlapping($q, $start, $end)
    {
        return $q->where(function ($qq) use ($start, $end) {
            $qq->where('start_at', '<', $end)->where('end_at', '>', $start);
        });
    }
}
