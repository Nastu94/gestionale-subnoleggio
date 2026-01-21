<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modello: CargosDocumentType
 * - Tabella anagrafica tipi documento CARGOS (code stringa).
 */
class CargosDocumentType extends Model
{
    protected $table = 'cargos_document_types';

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'label', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
