<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modello: Vehicle
 * - Veicolo del parco dell'Admin.
 */
class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'admin_organization_id','vin','plate','make','model','year','color',
        'fuel_type','transmission','seats','segment','mileage_current',
        'default_pickup_location_id','is_active','notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'seats' => 'integer',
        'mileage_current' => 'integer',
        'is_active' => 'boolean',
    ];

    // --- Relazioni ---
    public function adminOrganization() { return $this->belongsTo(Organization::class, 'admin_organization_id'); }
    public function defaultPickupLocation(){ return $this->belongsTo(Location::class, 'default_pickup_location_id'); }
    public function documents()         { return $this->hasMany(VehicleDocument::class); }
    public function states()            { return $this->hasMany(VehicleState::class); }
    public function assignments()       { return $this->hasMany(VehicleAssignment::class); }
    public function rentals()           { return $this->hasMany(Rental::class); }
    public function blocks()            { return $this->hasMany(VehicleBlock::class); }

    // --- Scope utili ---
    /** Veicoli attivi (non dismessi) */
    public function scopeActive($q) { return $q->where('is_active', true); }
}
