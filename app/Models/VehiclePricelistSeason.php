<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonInterface;   // ✅ giusto
use Carbon\CarbonImmutable;   // (se lo usi dentro)

class VehiclePricelistSeason extends Model
{
    protected $fillable = [
        'vehicle_pricelist_id','name','start_mmdd','end_mmdd',
        'season_pct','weekend_pct_override','priority','is_active',
    ];
    protected $casts = ['is_active'=>'bool'];

    public function pricelist() { return $this->belongsTo(VehiclePricelist::class, 'vehicle_pricelist_id'); }

    public function matchesDate(CarbonInterface $date): bool
    {
        $mm = str_pad((string)$date->month, 2, '0', STR_PAD_LEFT);
        $dd = str_pad((string)$date->day, 2, '0', STR_PAD_LEFT);
        $cur = $mm.'-'.$dd;

        $from = $this->start_mmdd;
        $to   = $this->end_mmdd;

        if ($from <= $to) {
            return $cur >= $from && $cur <= $to;
        }
        // range che "attraversa" fine anno (es. 12-15 .. 01-10)
        return $cur >= $from || $cur <= $to;
    }
}
