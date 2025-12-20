<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: VehicleDocument
 * - Documenti (assicurazione, libretto, revisione, ecc.)
 */
class VehicleDocument extends Model
{
    use HasFactory;

    /**
     * Mappa “UI-only” per mostrare in italiano i tipi documento.
     * NB: nel database restano in inglese.
     *
     * @var array<string,string>
     */
    public const TYPE_LABELS_IT = [
        'insurance'    => 'RCA',
        'registration' => 'Libretto',
        'inspection'   => 'Revisione',
        'green_card'   => 'Carta verde',
        'ztl_permit'   => 'Permesso ZTL',
        'road_tax'     => 'Bollo',
        'other'        => 'Altro',
    ];

    protected $fillable = [
        'vehicle_id','type','number','issue_date','expiry_date','status',
        'media_uuid','notes',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'expiry_date' => 'date',
    ];

    public function vehicle() { return $this->belongsTo(Vehicle::class); }

    /**
     * Accessor: etichetta italiana del tipo documento.
     * Uso: {{ $doc->type_label }}
     */
    public function getTypeLabelAttribute(): string
    {
        $type = (string) ($this->type ?? '');

        return self::TYPE_LABELS_IT[$type] ?? $type;
    }
}
