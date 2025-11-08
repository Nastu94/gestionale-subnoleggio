<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: Organization
 * - type: admin | renter
 * - Rappresenta Admin (proprietario parco) o Noleggiatore.
 */
class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name','type','vat','address_line','city','province','postal_code',
        'country_code','phone','email','is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // --- Relazioni principali ---
    public function users()            { return $this->hasMany(User::class); }
    public function locations()        { return $this->hasMany(Location::class); }

    // Veicoli di cui l'org è Admin (owner del parco)
    public function vehiclesOwned()    { return $this->hasMany(Vehicle::class, 'admin_organization_id'); }

    // Clienti del noleggiatore
    public function customers()        { return $this->hasMany(Customer::class); }

    // Affidamenti ricevuti dal noleggiatore
    public function vehicleAssignments(){ return $this->hasMany(VehicleAssignment::class, 'renter_org_id'); }

    // Blocchi creati da questa org (Admin o Renter)
    public function vehicleBlocks()    { return $this->hasMany(VehicleBlock::class); }

    // Rentals creati da questa org (renter)
    public function rentals()          { return $this->hasMany(Rental::class); }

    /** Fee admin storicizzate per questo renter. */
    public function fees(): HasMany
    {
        return $this->hasMany(OrganizationFee::class);
    }

    // --- Helper ---
    public function isAdmin(): bool  { return $this->type === 'admin'; }
    public function isRenter(): bool { return $this->type === 'renter'; }
}
