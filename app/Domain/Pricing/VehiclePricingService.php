<?php

namespace App\Domain\Pricing;

use App\Models\Vehicle;
use App\Models\VehiclePricelist;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class VehiclePricingService
{   
    /**
     * Trova il listino attivo più recente per il veicolo e il renter correnti (se esiste).
     * Ritorna null se non c'è un renter attivo o non c'è un listino attivo per quel renter.
     */
    public function findActivePricelistForCurrentRenter(Vehicle $vehicle): ?VehiclePricelist
    {
        // deduci il renter dall’assegnazione attiva “ora”
        $now = now();
        $renterOrgId = DB::table('vehicle_assignments')
            ->where('vehicle_id', $vehicle->id)
            ->where('status', 'active')
            ->where('start_at', '<=', $now)
            ->where(function($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>', $now);
            })
            ->value('renter_org_id');

        if (!$renterOrgId) return null;

        return VehiclePricelist::where('vehicle_id', $vehicle->id)
            ->where('renter_org_id', $renterOrgId)
            ->where('is_active', true)
            ->latest('published_at')
            ->first();
    }

    /**
     * Calcola il preventivo basato sul listino, le date di ritiro/riconsegna e i km previsti.
     * Ritorna un array con i dettagli del preventivo.
     * - 'days': numero di giorni di noleggio
     * - 'daily_total': totale giornaliero (prima di tiers e arrotondamenti)
     * - 'km_extra': costo km extra
     * - 'tier': dati del tier applicato (se presente)
     * - 'deposit': cauzione (se presente)
     * - 'total': totale finale (dopo tiers e arrotondamenti)
     * - 'currency': valuta del listino
     */
    public function quote(VehiclePricelist $pl, \DateTimeInterface $pickupAt, \DateTimeInterface $dropoffAt, int $expectedKm = 0): array
    {
        $start = CarbonImmutable::instance($pickupAt)->tz('Europe/Rome');
        $end   = CarbonImmutable::instance($dropoffAt)->tz('Europe/Rome');

        // giorni arrotondati per eccesso sulle ORE REALI (gestisce DST)
        $hours = $start->floatDiffInRealHours($end);   // +1h nel weekend di cambio ora
        $days  = max(1, (int) ceil($hours / 24));

        // Precarico in memoria per evitare query per giorno
        $seasons = $pl->seasons()->where('is_active', true)->get();
        $tiers   = $pl->tiers()->where('is_active', true)->get();

        $dailyTotals = 0;

        for ($i=0; $i<$days; $i++) {
            $d = $start->addDays($i);
            $isWeekend = in_array($d->dayOfWeekIso, [6,7], true);

            $season = $seasons->first(fn($s) => $s->matchesDate($d));
            $weekendPct = $season?->weekend_pct_override ?? $pl->weekend_pct;

            $daily = $pl->base_daily_cents;
            if ($isWeekend && $weekendPct > 0) {
                $daily += (int) round($daily * ($weekendPct/100));
            }

            if ($season && $season->season_pct) {
                $daily += (int) round($daily * ($season->season_pct/100));
            }

            $dailyTotals += $daily;
        }

        // km extra
        $extra = 0;
        if ($pl->km_included_per_day && $pl->extra_km_cents) {
            $included = $pl->km_included_per_day * $days;
            $excess   = max(0, $expectedKm - $included);
            $extra    = $excess * $pl->extra_km_cents;
        }

        $subtotal = $dailyTotals + $extra;

        // Applica tier in base ai giorni (il più specifico / priorità più alta)
        $tier = $tiers->first(fn($t) => $t->matchesDays($days));
        if ($tier) {
            if (!is_null($tier->override_daily_cents)) {
                // Override daily = ignora weekend/season per semplicità (MVP)
                $subtotal = $tier->override_daily_cents * $days + $extra;
            } elseif (!is_null($tier->discount_pct) && $tier->discount_pct > 0) {
                $subtotal = (int) round($subtotal * (1 - $tier->discount_pct/100));
            }
        }

        $total = $this->applyRounding($subtotal, $pl->rounding);

        return [
            'days'        => $days,
            'daily_total' => $dailyTotals, // prima dei tiers
            'km_extra'    => $extra,
            'tier'        => $tier?->only(['name','override_daily_cents','discount_pct']),
            'deposit'     => (int) ($pl->deposit_cents ?? 0),
            'total'       => $total,
            'currency'    => $pl->currency,
        ];
    }

    /**
     * Applica l'arrotondamento al totale in cents, secondo la modalità specificata.
     * - 'none': nessun arrotondamento
     * - 'up_1': arrotonda all'euro superiore
     * - 'up_5': arrotonda al multiplo di 5 euro superiore
     */
    private function applyRounding(int $cents, string $mode): int
    {
        if ($mode === 'up_1') {
            $euro = (int) ceil($cents / 100);
            return $euro * 100;
        }
        if ($mode === 'up_5') {
            // arrotonda al multiplo di 5€ superiore
            $unit5 = (int) ceil(($cents / 100) / 5) * 5;
            return $unit5 * 100;
        }
        return $cents;
    }
}
