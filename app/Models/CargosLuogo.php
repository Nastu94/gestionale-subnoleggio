<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modello: CargosLuogo
 *
 * Master data ufficiale CARGOS (Polizia di Stato).
 * NON è un'entità di business.
 */
class CargosLuogo extends Model
{
    /**
     * Tabella associata.
     */
    protected $table = 'cargos_luoghi';

    /**
     * Primary key NON auto-incrementale.
     */
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * Campi assegnabili.
     * (non useremo mass assignment, ma è bene esplicitarli)
     */
    protected $fillable = [
        'code',
        'name',
        'province_code',
        'country_code',
        'is_italian',
        'is_active',
        'raw_payload',
    ];
}
