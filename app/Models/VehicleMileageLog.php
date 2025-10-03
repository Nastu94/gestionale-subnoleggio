<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $vehicle_id
 * @property int|null    $mileage_old
 * @property int         $mileage_new
 * @property int|null    $changed_by
 * @property string      $source   manual|import|api
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $changed_at
 */
class VehicleMileageLog extends Model
{
    // Fillable per mass assignment controllato
    protected $fillable = [
        'vehicle_id',
        'mileage_old',
        'mileage_new',
        'changed_by',
        'source',
        'notes',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'mileage_old' => 'integer',
        'mileage_new' => 'integer',
    ];

    /** Veicolo associato */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Utente che ha effettuato la modifica */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
