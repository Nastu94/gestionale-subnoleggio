<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: VehicleAssignment
 * - Affidamento veicolo: Admin → Renter
 * - end_at NULL = affidamento aperto.
 */
class VehicleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id','renter_org_id','start_at','end_at','status',
        'mileage_start','mileage_end','notes','created_by',
    ];

    protected $casts = [
        'start_at'      => 'datetime',
        'end_at'        => 'datetime',
        'mileage_start' => 'integer',
        'mileage_end'   => 'integer',
    ];

    public function vehicle()      { return $this->belongsTo(Vehicle::class); }
    public function renterOrg()    { return $this->belongsTo(Organization::class, 'renter_org_id'); }
    public function creator()      { return $this->belongsTo(User::class, 'created_by'); }
    public function constraint()   { return $this->hasOne(AssignmentConstraint::class, 'assignment_id'); }
    public function rentals()      { return $this->hasMany(Rental::class, 'assignment_id'); }

    // Scope: attivi al momento
    public function scopeActive($q)
    {
        $now = now();
        return $q->where('status', 'active')
            ->where('start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>', $now);
            });
    }
}
