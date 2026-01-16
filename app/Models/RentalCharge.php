<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalCharge extends Model
{
    use SoftDeletes;

    protected $table = 'rental_charges';

    public const KIND_BASE             = 'base';
    public const KIND_DISTANCE_OVERAGE = 'distance_overage';
    public const KIND_DAMAGE           = 'damage';
    public const KIND_SURCHARGE        = 'surcharge';
    public const KIND_FINE             = 'fine';
    public const KIND_OTHER            = 'other';
    public const KIND_ACCONTO          = 'acconto';
    public const KIND_BASE_PLUS_DISTANCE_OVERAGE = 'base+distance_overage';

    protected $fillable = [
        'rental_id',
        'kind',
        'is_commissionable',
        'description',
        'amount',
        'payment_recorded',
        'payment_recorded_at',
        'payment_method',
        'created_by',
    ];

    protected $casts = [
        'is_commissionable'   => 'boolean',
        'amount'              => 'decimal:2',
        'payment_recorded'    => 'boolean',
        'payment_recorded_at' => 'datetime',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes utili, se vorrai riusarli
    public function scopeCommissionable($q)   { return $q->where('is_commissionable', true); }
    public function scopeNonCommissionable($q){ return $q->where('is_commissionable', false); }
    public function scopePaid($q)             { return $q->where('payment_recorded', true); }
    public function scopeOfKind($q, string $kind){ return $q->where('kind', $kind); }
}
