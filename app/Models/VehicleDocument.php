<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: VehicleDocument
 * - Documenti (assicurazione, libretto, revisione, ecc.)
 */
class VehicleDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id','type','number','issue_date','expiry_date','status',
        'media_uuid','notes',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'expiry_date' => 'date',
    ];

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
}
