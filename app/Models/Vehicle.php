<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Image\Manipulations;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

/**
 * Modello: Vehicle
 * - Veicolo del parco dell'Admin.
 */
class Vehicle extends Model implements SpatieHasMedia
{
    use HasFactory, SoftDeletes;
    use InteractsWithMedia;

    protected $fillable = [
        'admin_organization_id','vin','plate','make','model','year','color',
        'fuel_type','transmission','seats','segment','mileage_current','default_pickup_location_id',
        'is_active','notes','lt_rental_monthly_cents','insurance_kasko_cents',
        'insurance_rca_cents','insurance_cristalli_cents','insurance_furto_cents',
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
    public function mileageLogs()       { return $this->hasMany(VehicleMileageLog::class); }
    public function pricelists()        { return $this->hasMany(VehiclePricelist::class); }

    // --- Scope utili ---
    /** Veicoli attivi (non dismessi) */
    public function scopeActive($q) { return $q->where('is_active', true); }

    /**
     * Registra le collection media per il veicolo.
     * Usiamo una sola collection "vehicle_photos" (galleria).
     */
    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('vehicle_photos')
            ->useDisk(config('filesystems.default', 'public')) // di default "public"
            ->acceptsMimeTypes(['image/jpeg','image/png','image/webp'])
            ->withResponsiveImages(); // opzionale, utile sui cover
    }

    /**
     * Conversioni immagine per le varie viste.
     * - thumb_48: per index/tabella (avatar piccolo)
     * - thumb_160: per card/list
     * - cover_1200x675: hero 16:9 per la show
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Thumbnail "contenuta" in 300x200, nessun ritaglio
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 300, 200)   // niente crop
            ->nonQueued();

        // Anteprima grande contenuta
        $this->addMediaConversion('preview')
            ->fit(Fit::Max, 1200, 800)      // ridimensiona entro, senza taglio
            ->nonQueued();

        // Thumbnail piccolo per liste/icone
        $this->addMediaConversion('thumb_48')
            ->fit(Fit::Contain, 48, 48)     // niente crop
            ->queued();
        
        // Thumbnail medio per card/list
        $this->addMediaConversion('thumb_160')
            ->fit(Fit::Contain, 160, 160)   // niente crop
            ->queued();

        // Cover 16:9 per la show (ritaglio centrato)
        $this->addMediaConversion('cover_1200x675')
            ->fit(Fit::Crop, 1200, 675)     // crop centrato
            ->queued();
        
        // Card 3:2 per liste veicoli (ritaglio centrato)
        $this->addMediaConversion('card_branded')
            ->fit(Fit::Contain, 900, 600)
            ->queued();
    }

    /**
     * URL della cover (prima foto disponibile) con conversione richiesta.
     * Se non esiste una foto, torna null.
     */
    public function coverUrl(string $conversion = 'cover_1200x675'): ?string
    {
        return $this->getFirstMediaUrl('vehicle_photos', $conversion) ?: null;
    }

    /**
     * URL del thumbnail piccolo per liste/icone.
     */
    public function thumbUrl(): ?string
    {
        return $this->getFirstMediaUrl('vehicle_photos', 'thumb_48') ?: null;
    }
}
