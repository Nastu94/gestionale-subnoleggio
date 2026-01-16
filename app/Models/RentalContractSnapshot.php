<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalContractSnapshot extends Model
{
    /**
     * Attributi assegnabili in massa.
     */
    protected $fillable = [
        'rental_id',
        'pricing_snapshot',
        'created_by_user_id',
    ];

    /**
     * Cast per gestire il JSON come array PHP.
     */
    protected $casts = [
        'pricing_snapshot' => 'array',
    ];

    /**
     * Relazione: snapshot -> rental (many-to-one).
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
