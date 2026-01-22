<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargosTransmission extends Model
{
    protected $fillable = [
        'rental_id',
        'agency_organization_id',
        'operator_user_id',

        'linked_check_id', // ✅ nuovo
        'action',
        'ok',
        'dry_run',         // ✅ nuovo
        'stage',

        'request_hash',
        'record_length',
        'record_preview',
        'record',

        'validation_errors',
        'api_response',
        'error_message',
    ];

    protected $casts = [
        'ok'                => 'boolean',
        'dry_run'           => 'boolean', // ✅ nuovo
        'validation_errors' => 'array',
        'api_response'      => 'array',

        // Dato sensibile
        'record' => 'encrypted',
    ];

    /**
     * Collega un SEND al CHECK OK usato come preflight.
     */
    public function linkedCheck(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_check_id');
    }
}
