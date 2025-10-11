<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiclePricelist extends Model
{
    protected $fillable = [
        'vehicle_id','renter_org_id',
        'name','currency',
        'base_daily_cents','weekend_pct',
        'km_included_per_day','extra_km_cents',
        'deposit_cents','rounding',
        'notes',
        // versioning
        'version','status','active_flag','published_at',
        'is_active', // legacy: ancora nel DB, ma non più usato in UI
    ];

    protected $casts = [
        'weekend_pct' => 'integer',
        'km_included_per_day' => 'integer',
        'extra_km_cents' => 'integer',
        'deposit_cents' => 'integer',
        'version' => 'integer',
        'active_flag' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function renter()  { return $this->belongsTo(Organization::class, 'renter_org_id'); }
    public function seasons() { return $this->hasMany(VehiclePricelistSeason::class)->orderByDesc('priority'); }
    public function tiers()   { return $this->hasMany(VehiclePricelistTier::class)->orderByDesc('priority'); }
}
