<?php

namespace App\Domain\Fees;

use App\Models\Rental;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Risolve la percentuale fee admin attiva per un'organizzazione in una data
 * e calcola l'importo fee per un noleggio.
 *
 * Convenzioni tabelle:
 * - organization_fees: { id, organization_id, percent, effective_from (date), effective_to (date|null), ... }
 *   La fee è valida se: effective_from <= $date && (effective_to is null || effective_to >= $date)
 */
class AdminFeeResolver
{
    /** TTL cache (minuti) per la percentuale fee attiva per (org, data) */
    private int $ttlMinutes;

    public function __construct(int $ttlMinutes = 10)
    {
        $this->ttlMinutes = $ttlMinutes;
    }

    /**
     * Ritorna la percentuale fee attiva (es. 12.5) per una org in una data (date-string o Carbon).
     * Se non trovata → null.
     */
    public function findActivePercent(int $organizationId, CarbonInterface|string|null $at = null): ?float
    {
        $date = $this->normalizeDate($at);
        $cacheKey = "fees.active_percent.org{$organizationId}.{$date->toDateString()}";

        return Cache::remember($cacheKey, now()->addMinutes($this->ttlMinutes), function () use ($organizationId, $date) {
            $percent = DB::table('organization_fees')
                ->where('organization_id', $organizationId)
                ->where('effective_from', '<=', $date->toDateString())
                ->where(function ($q) use ($date) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date->toDateString());
                })
                ->orderByDesc('effective_from')
                ->value('percent');

            return is_null($percent) ? null : (float) $percent;
        });
    }

    /**
     * Calcola la fee per un noleggio in una data (default: data di return effettiva o oggi).
     * Ritorna array con: ['percent' => ?float, 'commissionable_total' => float, 'amount' => float]
     */
    public function calculateForRental(Rental $rental, CarbonInterface|string|null $at = null): array
    {
        $date = $this->normalizeDate($at ?? ($rental->actual_return_at ?: now()));

        // percentuale attiva per l'org del rental (se manca → null)
        $percent = $rental->organization_id
            ? $this->findActivePercent($rental->organization_id, $date)
            : null;

        // somma righe commissionabili (senza affidarsi a accessor formattati)
        $commissionable = (float) $rental->charges()
            ->where('is_commissionable', true)
            ->sum('amount');

        $amount = $percent !== null
            ? round($commissionable * ($percent / 100), 2)
            : 0.0;

        return [
            'percent'             => $percent,         // es. 12.5 (o null se non definita)
            'commissionable_total'=> round($commissionable, 2),
            'amount'              => $amount,          // es. 123.45 (€)
        ];
    }

    /**
     * Utility: normalizza in Carbon (zona 'Europe/Rome' per coerenza UI/DB)
     */
    private function normalizeDate(CarbonInterface|string|null $at): CarbonInterface
    {
        if ($at instanceof CarbonInterface) {
            return $at->copy()->timezone('Europe/Rome');
        }
        if (is_string($at)) {
            return Carbon::parse($at, 'Europe/Rome');
        }
        return now()->timezone('Europe/Rome');
    }
}
