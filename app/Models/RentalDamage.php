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

    public function rental()  { return $this->belongsTo(Rental::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

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
            ->fit(Manipulations::FIT_CROP, 256, 256)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Manipulations::FIT_MAX, 1600, 1600)
            ->keepOriginalImageFormat()
            ->nonQueued();

        $this->addMediaConversion('hd')
            ->fit(Manipulations::FIT_MAX, 2048, 2048)
            ->keepOriginalImageFormat()
            ->performOnCollections('photos')
            ->nonQueued();
    }
}
