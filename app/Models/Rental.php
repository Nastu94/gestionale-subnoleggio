<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modello: Rental
 * - Sub-noleggio (Renter → Cliente)
 * - include pianificate ed effettive, stato, denormalizzazioni km/fuel.
 */
class Rental extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id','vehicle_id','assignment_id','customer_id',
        'planned_pickup_at','planned_return_at','actual_pickup_at','actual_return_at',
        'pickup_location_id','return_location_id','status',
        'mileage_out','mileage_in','fuel_out_percent','fuel_in_percent',
        'notes','created_by',
    ];

    protected $casts = [
        'planned_pickup_at' => 'datetime',
        'planned_return_at' => 'datetime',
        'actual_pickup_at'  => 'datetime',
        'actual_return_at'  => 'datetime',
        'mileage_out'       => 'integer',
        'mileage_in'        => 'integer',
        'fuel_out_percent'  => 'integer',
        'fuel_in_percent'   => 'integer',
    ];

    // --- Relazioni ---
    public function organization()     { return $this->belongsTo(Organization::class); }
    public function vehicle()          { return $this->belongsTo(Vehicle::class); }
    public function assignment()       { return $this->belongsTo(VehicleAssignment::class); }
    public function customer()         { return $this->belongsTo(Customer::class); }
    public function pickupLocation()   { return $this->belongsTo(Location::class, 'pickup_location_id'); }
    public function returnLocation()   { return $this->belongsTo(Location::class, 'return_location_id'); }
    public function creator()          { return $this->belongsTo(User::class, 'created_by'); }

    public function checklists()       { return $this->hasMany(RentalChecklist::class); }
    public function photos()           { return $this->hasMany(RentalPhoto::class); }
    public function damages()          { return $this->hasMany(RentalDamage::class); }

    // Helper per accesso diretto alle due checklist canoniche
    public function pickupChecklist()  { return $this->hasOne(RentalChecklist::class)->where('type','pickup'); }
    public function returnChecklist()  { return $this->hasOne(RentalChecklist::class)->where('type','return'); }

    // Scope: per organizzazione Renter
    public function scopeForOrganization($q, int $orgId) { return $q->where('organization_id', $orgId); }
}
