<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Modello: VehicleDamage
 *
 * - Danni “persistenti” del veicolo.
 * - Se nasce da un rental (source='rental'): i dettagli (area/severity/description)
 *   si leggono dal first_rental_damage.
 * - Se nasce fuori noleggio (source='manual'|'inspection'|'service'): i dettagli
 *   sono salvati direttamente qui (colonne area/severity/description).
 */
class VehicleDamage extends Model
{
    use HasFactory;
    use LogsActivity;

    /**
     * Attributi assegnabili in massa.
     * Aggiunti: source, area, severity, description (per danni non da rental).
     */
    protected $fillable = [
        'vehicle_id',
        'first_rental_damage_id',
        'last_rental_damage_id',
        'source',          // rental|manual|inspection|service
        'area',            // opzionale: valorizzato per source != rental
        'severity',        // opzionale: valorizzato per source != rental (low|medium|high)
        'description',     // opzionale: valorizzato per source != rental
        'is_open',
        'fixed_at',
        'fixed_by_user_id',
        'repair_cost',
        'notes',
        'created_by',
    ];

    /**
     * Cast degli attributi primitivi.
     */
    protected $casts = [
        'is_open'     => 'boolean',
        'fixed_at'    => 'datetime',
        'repair_cost' => 'decimal:2',
    ];

    /**
     * Appende gli accessor “virtuali” per comodo in JSON/API.
     */
    protected $appends = [
        'resolved_area',
        'resolved_severity',
        'resolved_description',
    ];

    /* -----------------------------------------------------------------
     |  Relazioni
     |------------------------------------------------------------------*/

    public function vehicle()               { return $this->belongsTo(Vehicle::class); }
    public function firstRentalDamage()     { return $this->belongsTo(RentalDamage::class, 'first_rental_damage_id'); }
    public function lastRentalDamage()      { return $this->belongsTo(RentalDamage::class, 'last_rental_damage_id'); }
    public function creator()               { return $this->belongsTo(User::class, 'created_by'); }

    /* -----------------------------------------------------------------
     |  Scope di comodo
     |------------------------------------------------------------------*/

    public function scopeOpen($query)       { return $query->where('is_open', true); }
    public function scopeForVehicle($query, int $vehicleId) { return $query->where('vehicle_id', $vehicleId); }

    /* -----------------------------------------------------------------
     |  Accessor “risolti” (UI-safe)
     |------------------------------------------------------------------*/

    /**
     * Ritorna l’area “risolta”: preferisce l’area locale (manuale/inspection/service),
     * altrimenti usa l’area del first_rental_damage se presente.
     */
    public function getResolvedAreaAttribute(): ?string
    {
        // area locale se valorizzata (danni non da rental)
        if (!empty($this->attributes['area'])) {
            return (string) $this->attributes['area'];
        }
        // fallback: area del primo rental_damage legato
        return $this->firstRentalDamage?->area;
    }

    /**
     * Ritorna la severità “risolta”: preferisce la severità locale,
     * altrimenti quella del first_rental_damage.
     */
    public function getResolvedSeverityAttribute(): ?string
    {
        if (!empty($this->attributes['severity'])) {
            return (string) $this->attributes['severity']; // low|medium|high
        }
        return $this->firstRentalDamage?->severity;
    }

    /**
     * Ritorna la descrizione “risolta”: preferisce la descrizione locale,
     * altrimenti quella del first_rental_damage.
     */
    public function getResolvedDescriptionAttribute(): ?string
    {
        if (!empty($this->attributes['description'])) {
            return (string) $this->attributes['description'];
        }
        return $this->firstRentalDamage?->description;
    }

    /* -----------------------------------------------------------------
     |  Helper di dominio
     |------------------------------------------------------------------*/

    /**
     * Marca come riparato (chiude il danno).
     *
     * @param  \Illuminate\Support\Carbon|string|null  $when
     * @param  int|null  $byUserId
     * @param  float|int|string|null  $cost
     * @return $this
     */
    public function markRepaired($when = null, ?int $byUserId = null, $cost = null): self
    {
        $this->is_open  = false;
        $this->fixed_at = $when ? now()->parse($when) : now();

        if ($byUserId)   { $this->fixed_by_user_id = $byUserId; }
        if ($cost !== null) { $this->repair_cost = (float) $cost; }

        $this->save();

        return $this;
    }

    /**
     * Riapre il danno (se necessario).
     */
    public function reopen(): self
    {
        $this->is_open = true;
        $this->save();

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Activity Log (Spatie)
     |------------------------------------------------------------------*/

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('vehicle_damages')
            ->logOnly([
                'vehicle_id',
                'first_rental_damage_id',
                'last_rental_damage_id',
                'source',
                'area',
                'severity',
                'description',
                'is_open',
                'fixed_at',
                'fixed_by_user_id',
                'repair_cost',
                'notes',
                'created_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Auto-imposta created_by se l’utente è autenticato.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (auth()->check() && !$model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }
}
