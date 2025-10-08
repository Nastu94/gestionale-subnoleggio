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
     * Calcola il preventivo (in cents) per il noleggio con il listino dato.
     * - $pickupAt, $dropoffAt: date/ore di ritiro e riconsegna
     * - $expectedKm: km totali previsti (usati per calcolare gli extra km)
     * 
     * Ritorna un array con:
     * - days: numero di giorni di noleggio (minimo 1)
     * - daily_total: totale parziale per i giorni (senza extra km e deposito)
     * - km_extra: totale parziale per gli extra km
     * - deposit: deposito cauzionale
     * - total: totale complessivo (daily_total + km_extra + deposit, arrotondato)
     * - currency: valuta del listino
     * 
     * Nota: non fa nessun controllo sul fatto che il listino sia attivo o meno.
     */
    public function quote(VehiclePricelist $pl, \DateTimeInterface $pickupAt, \DateTimeInterface $dropoffAt, int $expectedKm = 0): array
    {
        $start = CarbonImmutable::instance($pickupAt);
        $end   = CarbonImmutable::instance($dropoffAt);

        $days = max(1, (int) ceil($end->floatDiffInHours($start) / 24));
        $dailyTotals = 0;

        for ($i=0; $i<$days; $i++) {
            $d = $start->addDays($i);
            $isWeekend = in_array($d->dayOfWeekIso, [6,7], true);
            $daily = $pl->base_daily_cents;
            if ($isWeekend && $pl->weekend_pct > 0) {
                $daily += (int) round($daily * ($pl->weekend_pct/100));
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
        $total = $this->applyRounding($subtotal, $pl->rounding);

        return [
            'days'        => $days,
            'daily_total' => $dailyTotals,
            'km_extra'    => $extra,
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
