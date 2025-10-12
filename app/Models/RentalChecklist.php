<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Manipulations;
use Illuminate\Support\Str;

/**
 * Modello: RentalChecklist
 * - Una per tipo: pickup | return (vincolo UNIQUE a DB).
 */
class RentalChecklist extends Model implements SpatieHasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'rental_id','type','mileage','fuel_percent','cleanliness',
        'signed_by_customer','signed_by_operator','signature_media_uuid',
        'checklist_json','created_by',
    ];

    protected $casts = [
        'mileage'            => 'integer',
        'fuel_percent'       => 'integer',
        'signed_by_customer' => 'boolean',
        'signed_by_operator' => 'boolean',
        'checklist_json'     => 'array',
    ];

    public function rental()  { return $this->belongsTo(Rental::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * Foto veicolo collegate alla checklist (pickup/return)
     * e firme relative allo step di checklist.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->useDisk(config('filesystems.default'));
        $this->addMediaCollection('signatures')->useDisk(config('filesystems.default'));
    }

    /**
     * Conversioni immagini per foto veicolo e firme.
     */
    public function registerMediaConversions(Media $media = null): void
    {
        if ($media && !Str::startsWith($media->mime_type, 'image/')) return;

        $this->addMediaConversion('thumb')
            ->fit(Manipulations::FIT_CROP, 256, 256)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Manipulations::FIT_MAX, 1280, 1280)
            ->keepOriginalImageFormat()
            ->nonQueued();

        $this->addMediaConversion('hd')
            ->fit(Manipulations::FIT_MAX, 1920, 1920)
            ->keepOriginalImageFormat()
            ->performOnCollections('photos')
            ->nonQueued();
    }
}
