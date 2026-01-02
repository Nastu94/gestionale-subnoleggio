<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit; 
use Illuminate\Support\Str;

/**
 * Modello: RentalDamage
 * - Danni rilevati (pickup/return/during).
 */
class RentalDamage extends Model implements SpatieHasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'rental_id','phase','area','severity','description',
        'estimated_cost','photos_count','created_by',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'photos_count'   => 'integer',
    ];

    /**
     * Mappe “UI-only” per mostrare in italiano l'area del danno.
     * NB: nel database restano in inglese.
     *
     * @var array<string,string>
     */
    public const DAMAGE_AREA_LABELS = [
        // Inglese -> Italiano
        'front' => 'Anteriore',
        'rear' => 'Posteriore',
        'left' => 'Sinistra',
        'right' => 'Destra',
        'interior' => 'Interno',
        'roof' => 'Tetto',
        'windshield' => 'Parabrezza',
        'wheel' => 'Ruota',
        'other' => 'Altro',

        // Italiano già salvato -> Italiano (per robustezza)
        'anteriore' => 'Anteriore',
        'posteriore' => 'Posteriore',
        'sinistra' => 'Sinistra',
        'destra' => 'Destra',
        'interno' => 'Interno',
        'tetto' => 'Tetto',
        'parabrezza' => 'Parabrezza',
        'ruota' => 'Ruota',
        'altro' => 'Altro',
    ];

    /**
     * Mappa “UI-only” per mostrare in italiano la severità del danno.
     * NB: nel database restano in inglese.
     *
     * @var array<string,string>
     */
    public const DAMAGE_SEVERITY_LABELS = [
        'low' => 'Bassa',
        'medium' => 'Media',
        'high' => 'Alta',

        // Se per caso arrivano già in italiano
        'bassa' => 'Bassa',
        'media' => 'Media',
        'alta' => 'Alta',
    ];

    public function rental()  { return $this->belongsTo(Rental::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function vehicleDamages() { return $this->hasMany(VehicleDamage::class, 'first_rental_damage_id'); }

    /** Foto specifiche del danno. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->useDisk(config('filesystems.default'));
    }

    /** Conversioni immagini per foto danni. */
    public function registerMediaConversions(Media $media = null): void
    {
        if ($media && !Str::startsWith($media->mime_type, 'image/')) return;

        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 256, 256)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Fit::Max, 1600, 1600)
            ->keepOriginalImageFormat()
            ->nonQueued();

        $this->addMediaConversion('hd')
            ->fit(Fit::Max, 2048, 2048)
            ->keepOriginalImageFormat()
            ->performOnCollections('photos')
            ->nonQueued();
    }

    /**
     * Accessor: etichetta italiana dell'area danno.
     * Uso: {{ $damage->area_label }}
     */
    public function getAreaLabelAttribute(): ?string
    {
        $area = (string) ($this->area ?? '');
        if (!$area) {
            return null;
        }
        return self::DAMAGE_AREA_LABELS[$area] ?? $area;
    }

    /**
     * Accessor: etichetta italiana della severità danno.
     * Uso: {{ $damage->severity_label }}
     */
    public function getSeverityLabelAttribute(): ?string
    {
        $severity = (string) ($this->severity ?? '');
        if (!$severity) {
            return null;
        }
        return self::DAMAGE_SEVERITY_LABELS[$severity] ?? $severity;
    }
}
