<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiclePricelistTier extends Model
{
    protected $fillable = [
        'vehicle_pricelist_id','name','min_days','max_days',
        'override_daily_cents','discount_pct','priority','is_active',
    ];
    protected $casts = ['is_active'=>'bool'];

    public function pricelist() { return $this->belongsTo(VehiclePricelist::class, 'vehicle_pricelist_id'); }

    public function matchesDays(int $days): bool
    {
        if ($days < $this->min_days) return false;
        if (!is_null($this->max_days) && $days > $this->max_days) return false;
        return true;
    }
}
