<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit; 
use Illuminate\Support\Str;

/**
 * Modello: Customer
 * - Cliente finale del noleggiatore.
 */
class Customer extends Model implements SpatieHasMedia
{
    use HasFactory, SoftDeletes;
    use InteractsWithMedia;

    protected $fillable = [
        'organization_id',
        'email',
        'phone',

        // Identità
        'first_name',
        'last_name',
        'name', // legacy / display
        'birthdate',
        'birth_place',
        'birth_place_code',

        // Residenza
        'address_line',
        'city',
        'province',
        'postal_code',
        'country_code',
        'police_place_code',

        // Cittadinanza
        'citizenship',
        'citizenship_cargos_code',
        'doc_id_type',

        // Documento identità
        'doc_id_number',
        'identity_document_type_code',
        'identity_document_place_code',

        // Patente
        'driver_license_number',
        'driver_license_expires_at',
        'driver_license_document_type_code',
        'driver_license_place_code',

        // Fiscali
        'tax_code',
        'vat',

        // Altro
        'notes',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'driver_license_expires_at' => 'date',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function rentals()      { return $this->hasMany(Rental::class); }

    // Scope: per organizzazione
    public function scopeForOrganization($q, int $orgId) { return $q->where('organization_id', $orgId); }

    /** Documenti identificativi del cliente (ID, patente, ecc.) */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')->useDisk(config('filesystems.default'));
    }

    /** Conversioni immagini per documenti identificativi. */
    public function registerMediaConversions(Media $media = null): void
    {
        if ($media && !Str::startsWith($media->mime_type, 'image/')) return;

        $this->addMediaConversion('thumb')
            ->fit(Fit::crop, 256, 256)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Fit::max, 1200, 1200)
            ->keepOriginalImageFormat()
            ->nonQueued();
    }
}
