<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Modello: Organization
 * - type: admin | renter
 * - Rappresenta Admin (proprietario parco) o Noleggiatore.
 */
class Organization extends Model implements SpatieHasMedia
{
    use HasFactory, SoftDeletes;
    use InteractsWithMedia;

    protected $fillable = [
        // Base
        'name','legal_name','type',

        // Anagrafica
        'vat','address_line','city','province','postal_code','country_code','phone','email',

        // Stato
        'is_active',

        // Licenza noleggio
        'rental_license','rental_license_number','rental_license_expires_at',

        // Cargos (valori cifrati via cast nel model)
        'cargos_password','cargos_puk',
    ];

    protected $casts = [
        'is_active' => 'boolean',

        /**
         * Licenza:
         * - flag boolean per query rapide
         * - expires_at come data per controlli scadenza
         */
        'rental_license' => 'boolean',
        'rental_license_expires_at' => 'date',

        /**
         * Cargos:
         * Cast cifrato applicativamente (richiede APP_KEY configurata).
         * NB: si possono leggere a video solo se l’admin ha i permessi UI previsti.
         */
        'cargos_password' => 'encrypted',
        'cargos_puk'      => 'encrypted',
    ];

    // --- Relazioni principali ---
    public function users(): HasMany            { return $this->hasMany(User::class); }
    public function locations(): HasMany        { return $this->hasMany(Location::class); }

    // Veicoli di cui l'org è Admin (owner del parco)
    public function vehiclesOwned(): HasMany    { return $this->hasMany(Vehicle::class, 'admin_organization_id'); }

    // Clienti del noleggiatore
    public function customers(): HasMany        { return $this->hasMany(Customer::class); }

    // Affidamenti ricevuti dal noleggiatore
    public function vehicleAssignments(): HasMany { return $this->hasMany(VehicleAssignment::class, 'renter_org_id'); }

    // Blocchi creati da questa org (Admin o Renter)
    public function vehicleBlocks(): HasMany    { return $this->hasMany(VehicleBlock::class); }

    // Rentals creati da questa org (renter)
    public function rentals(): HasMany          { return $this->hasMany(Rental::class); }

    /** Fee admin storicizzate per questo renter. */
    public function fees(): HasMany
    {
        return $this->hasMany(OrganizationFee::class);
    }

    // --- Helper ---
    public function isAdmin(): bool  { return $this->type === 'admin'; }
    public function isRenter(): bool { return $this->type === 'renter'; }

    /**
     * Collection Media:
     * - org_signature: firma aziendale del noleggiante (una sola “corrente”)
     */
    public function registerMediaCollections(): void
    {
        // Firma aziendale di default
        $this->addMediaCollection('signature_company')->singleFile();
    }
}
