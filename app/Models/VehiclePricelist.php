<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiclePricelist extends Model
{
    protected $fillable = [
        'vehicle_id','renter_org_id','name','currency',
        'base_daily_cents','weekend_pct',
        'km_included_per_day','extra_km_cents',
        'deposit_cents','rounding','is_active','published_at',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'published_at' => 'datetime',
    ];

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function renter()  { return $this->belongsTo(Organization::class, 'renter_org_id'); }
}
