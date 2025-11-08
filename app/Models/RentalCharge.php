<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modello: riga economica di un noleggio.
 *
 * - Gli importi sono già IVA inclusa (campo unico: amount).
 * - La commissione admin si calcola sommando le righe commissionabili (is_commissionable = true).
 * - Può contenere informazioni di pagamento per SINGOLA riga (recorded/at/method).
 */
class RentalCharge extends Model
{
    use SoftDeletes;

    /**
     * Attributi assegnabili in massa.
     * Manteniamo l'elenco esplicito per sicurezza.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rental_id',
        'kind',
        'is_commissionable',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'payment_recorded',
        'payment_recorded_at',
        'payment_method',
        'created_by',
    ];

    /**
     * Cast degli attributi.
     * - decimal:2 per importi
     * - boolean per flag logici
     * - datetime per timestamp pagamento
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_commissionable'   => 'boolean',
        'quantity'            => 'decimal:2',
        'unit_price'          => 'decimal:2',
        'amount'              => 'decimal:2',
        'payment_recorded'    => 'boolean',
        'payment_recorded_at' => 'datetime',
    ];

    /**
     * Relazione: la riga appartiene ad un noleggio.
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Relazione: utente che ha creato la riga (se tracciato).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =======================
     *        SCOPES
     * ======================= */

    /**
     * Solo righe commissionabili (partecipano a Tᶜ).
     */
    public function scopeCommissionable($query)
    {
        return $query->where('is_commissionable', true);
    }

    /**
     * Solo righe NON commissionabili (danni, franchigia, multe, rimborsi puri...).
     */
    public function scopeNonCommissionable($query)
    {
        return $query->where('is_commissionable', false);
    }

    /**
     * Solo righe già saldate (pagamento registrato).
     */
    public function scopePaid($query)
    {
        return $query->where('payment_recorded', true);
    }

    /**
     * Solo righe non ancora saldate.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_recorded', false);
    }

    /**
     * Filtra per tipologia riga (es.: base, extra, km_over, cleaning, damage, ...).
     */
    public function scopeOfKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }
}
