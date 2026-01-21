<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: Location
 * - Sedi/filiali di Admin o Renter.
 */
class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id','name','address_line','city','province','postal_code',
        'country_code','lat','lng','notes', 'police_place_code',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }

    // Veicoli che hanno questa location come default pickup
    public function vehiclesDefaultPickup()
    {
        return $this->hasMany(Vehicle::class, 'default_pickup_location_id');
    }
    
    /**
     * Luogo ufficiale CARGOS associato alla location.
     *
     * Ritorna NULL se la location non è ancora mappata.
     */
    public function cargosLuogo()
    {
        return $this->belongsTo(
            CargosLuogo::class,
            'police_place_code', // FK locale
            'code'               // PK CARGOS
        );
    }
}
