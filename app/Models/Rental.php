<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

// Spatie Media Library
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;

class Rental extends Model implements SpatieHasMedia
{
    use HasFactory, SoftDeletes;
    use InteractsWithMedia;

    protected $fillable = [
        'organization_id','vehicle_id','assignment_id','customer_id',
        'planned_pickup_at','planned_return_at','actual_pickup_at','actual_return_at',
        'pickup_location_id','return_location_id','status',
        'mileage_out','mileage_in','fuel_out_percent','fuel_in_percent',
        'notes','created_by', 'closed_at', 'closed_by',
        // facoltativi/denormalizzati se li usi:
        'amount','admin_fee_percent','admin_fee_amount',
        'second_driver_id',
        'final_amount_override',
    ];

    protected $casts = [
        'planned_pickup_at' => 'datetime',
        'planned_return_at' => 'datetime',
        'actual_pickup_at'  => 'datetime',
        'actual_return_at'  => 'datetime',
        'closed_at'         => 'datetime',
        'closed_by'         => 'integer',
        'mileage_out'       => 'integer',
        'mileage_in'        => 'integer',
        'fuel_out_percent'  => 'integer',
        'fuel_in_percent'   => 'integer',
        'final_amount_override' => 'decimal:2',
    ];

    // -------------------------
    // Relazioni base
    // -------------------------
    public function organization()     { return $this->belongsTo(Organization::class); }
    public function vehicle()          { return $this->belongsTo(Vehicle::class); }
    public function assignment()       { return $this->belongsTo(VehicleAssignment::class); }
    public function customer()         { return $this->belongsTo(Customer::class); }
    public function pickupLocation()   { return $this->belongsTo(Location::class, 'pickup_location_id'); }
    public function returnLocation()   { return $this->belongsTo(Location::class, 'return_location_id'); }
    public function creator()          { return $this->belongsTo(User::class, 'created_by'); }

    public function checklists(): HasMany
    {
        return $this->hasMany(RentalChecklist::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RentalPhoto::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(RentalDamage::class);
    }

    /**
     * Seconda guida (Customer) opzionale.
     */
    public function secondDriver()
    {
        return $this->belongsTo(Customer::class, 'second_driver_id');
    }

    // Helper per accesso diretto alle due checklist canoniche
    public function pickupChecklist(): HasOne
    {
        return $this->hasOne(RentalChecklist::class)->where('type', 'pickup');
    }

    public function returnChecklist(): HasOne
    {
        return $this->hasOne(RentalChecklist::class)->where('type', 'return');
    }

    // -------------------------
    // Righe economiche (pagamenti eseguiti)
    // -------------------------
    public function charges(): HasMany
    {
        return $this->hasMany(RentalCharge::class);
    }

    public function commissionableCharges(): HasMany
    {
        return $this->charges()->where('is_commissionable', true);
    }

    // -------------------------
    // Accessor aggregati utili
    // -------------------------
    public function getChargesTotalAttribute(): string
    {
        $sum = (float) $this->charges()->sum('amount');
        return number_format($sum, 2, '.', '');
    }

    public function getCommissionableTotalAttribute(): string
    {
        $sum = (float) $this->commissionableCharges()->sum('amount');
        return number_format($sum, 2, '.', '');
    }

    public function getChargesPaidTotalAttribute(): string
    {
        $sum = (float) $this->charges()->where('payment_recorded', true)->sum('amount');
        return number_format($sum, 2, '.', '');
    }

    // -------------------------
    // Gate/flag per la UX (checkout/close)
    // -------------------------

    /** True se esiste almeno una riga pagata di tipo BASE */
    public function getHasBasePaymentAttribute(): bool
    {
        return $this->charges()
            ->ofKind(RentalCharge::KIND_BASE)
            ->paid()
            ->exists();
    }

    public function getBasePaymentAtAttribute(): ?Carbon
    {
        $row = $this->charges()
            ->ofKind(RentalCharge::KIND_BASE)
            ->paid()
            ->latest('payment_recorded_at')
            ->first();

        return $row?->payment_recorded_at;
    }

    /** Km eccedenti (se non hai le checklist, cade su mileage_in/out) */
    public function getDistanceOverageKmAttribute(): int
    {
        $pickupKm   = optional($this->pickupChecklist)->odometer ?? $this->mileage_out;
        $returnKm   = optional($this->returnChecklist)->odometer ?? $this->mileage_in;
        $includedKm = $this->included_km ?? 0;

        if ($pickupKm === null || $returnKm === null) return 0;

        $delta = (int) $returnKm - (int) $pickupKm - (int) $includedKm;
        return $delta > 0 ? $delta : 0;
    }

    /** Serve un pagamento per overage? */
    public function getNeedsDistanceOveragePaymentAttribute(): bool
    {
        return $this->distance_overage_km > 0;
    }

    /** True se esiste almeno una riga pagata di tipo DISTANCE_OVERAGE */
    public function getHasDistanceOveragePaymentAttribute(): bool
    {
        return $this->charges()
            ->where('kind', RentalCharge::KIND_DISTANCE_OVERAGE)
            ->where('payment_recorded', true)
            ->exists();
    }

    /** Comodo per la UI: posso fare checkout? */
    public function getCanCheckoutAttribute(): bool
    {
        return in_array($this->status, ['draft','reserved'], true)
            && $this->has_base_payment;
    }

    /** Comodo per la UI: posso chiudere? */
    public function getCanCloseAttribute(): bool
    {
        if ($this->status !== 'checked_in') return false;

        // se ci sono km extra, deve esserci la riga di overage
        if ($this->needs_distance_overage_payment && !$this->has_distance_overage_payment) {
            return false;
        }
        return true;
    }

    // -------------------------
    // Media Library
    // -------------------------
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('contract')->useDisk(config('filesystems.default'));
        $this->addMediaCollection('signatures')->useDisk(config('filesystems.default'));
        $this->addMediaCollection('documents')->useDisk(config('filesystems.default'));
        $this->addMediaCollection('signature_customer')->singleFile();
        $this->addMediaCollection('signature_lessor')->singleFile();
    }

    public function registerMediaConversions(Media $media = null): void
    {
        if ($media && !Str::startsWith($media->mime_type, 'image/')) {
            return;
        }

        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 256, 256)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Fit::Max, 1024, 1024)
            ->keepOriginalImageFormat()
            ->nonQueued();

        $this->addMediaConversion('hd')
            ->fit(Fit::Max, 1920, 1920)
            ->keepOriginalImageFormat()
            ->performOnCollections('signatures','documents', 'id_card', 'driver_license', 'privacy')
            ->nonQueued();
    }

    // -------------------------
    // Coperture noleggio (1:1)
    // -------------------------
    public function coverage(): HasOne
    {
        return $this->hasOne(RentalCoverage::class);
    }

    public function ensureCoverage(): RentalCoverage
    {
        return $this->coverage()->firstOrCreate([
            'rental_id' => $this->getKey(),
        ], [
            'rca' => true, 'kasko' => false, 'furto_incendio' => false, 'cristalli' => false, 'assistenza' => false,
            'franchise_rca' => null, 'franchise_kasko' => null, 'franchise_furto_incendio' => null, 'franchise_cristalli' => null,
            'notes' => null,
        ]);
    }

    /**
     * Risoluzione firma noleggiante da usare nel contratto:
     * - prima prova a prendere l’override sul noleggio
     * - se manca, usa la firma aziendale dell’organizzazione noleggiante
     */
    public function resolveLessorSignatureMedia(): ?Media
    {
        $order = config('rental.signatures.lessor_precedence', ['organization', 'rental']);

        foreach ($order as $source) {
            if ($source === 'organization') {
                $org = $this->organization ?? null;
                if ($org) {
                    $m = $org->getFirstMedia('signature_company');
                    if ($m) return $m;
                }
            }

            if ($source === 'rental') {
                $m = $this->getFirstMedia('signature_lessor'); // override sul noleggio
                if ($m) return $m;
            }
        }

        return null;
    }

    /**
     * URL firma noleggiante da usare nel contratto (se esiste).
     */
    public function resolveLessorSignatureUrl(): ?string
    {
        $m = $this->resolveLessorSignatureMedia();

        // Se usi il tuo endpoint protetto "open":
        return $m ? route('media.open', $m) : null;
        // In alternativa (se tutto è pubblico):
        // return $m ? $m->getUrl() : null;
    }
}
