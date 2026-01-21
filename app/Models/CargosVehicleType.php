<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modello: CargosVehicleType
 *
 * Master data ufficiale CARGOS (Polizia di Stato).
 * Contiene le tipologie veicolo (code => label) da usare in UI e mapping.
 *
 * NOTA:
 * - PK stringa "code" (non incrementale)
 * - Non è un'entità di business: serve per lookup/validazione
 */
class CargosVehicleType extends Model
{
    use HasFactory;

    /**
     * Tabella associata.
     *
     * @var string
     */
    protected $table = 'cargos_vehicle_types';

    /**
     * Primary key non auto-incrementale.
     *
     * @var string
     */
    protected $primaryKey = 'code';

    /**
     * PK non incrementale.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipo PK.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Campi assegnabili.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'code',
        'label',
        'is_active',
    ];

    /**
     * Cast attributi.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope: solo record attivi.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
