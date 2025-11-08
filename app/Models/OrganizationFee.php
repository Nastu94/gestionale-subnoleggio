<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fee admin per Organization (renter), con intervalli di validità.
 *
 * Regola: per una certa data X deve esistere al più una fee attiva:
 *   effective_from <= X <= effective_to (o effective_to null)
 */
class OrganizationFee extends Model
{
    use SoftDeletes;

    /** @var array<int,string> */
    protected $fillable = [
        'organization_id',
        'percent',
        'effective_from',
        'effective_to',
        'created_by',
        'notes',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'percent'        => 'decimal:2',
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    /** Organization (renter) proprietaria della fee. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Utente che ha creato la fee (se tracciato). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ----------------------- SCOPES ----------------------- */

    /**
     * Fee attive alla data (inclusiva).
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     * @param \DateTimeInterface|string $date
     */
    public function scopeActiveAt($q, $date)
    {
        $d = $date instanceof \DateTimeInterface ? $date : CarbonImmutable::parse($date);
        return $q->whereDate('effective_from', '<=', $d)
                 ->where(function ($w) use ($d) {
                     $w->whereNull('effective_to')
                       ->orWhereDate('effective_to', '>=', $d);
                 });
    }
}
