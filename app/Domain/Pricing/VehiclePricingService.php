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
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>', $now);
            })
            ->value('renter_org_id');

        // Fallback: gestione diretta dell'admin org
        if (!$renterOrgId && $vehicle->admin_organization_id) {
            $user = auth()->user();
            if ($user && $user->hasRole('admin')) {
                $renterOrgId = $vehicle->admin_organization_id;
            }
        }

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
     *
     * NOTE LOGICA (modello additivo alla base):
     * - Weekend/listino base, stagione e weekend stagione vengono calcolati SEMPRE sulla tariffa base (non composto).
     * - In caso di sovrapposizione stagioni/tiers: vince la PRIORITÀ più alta dove 0 = più alta (ordinamento ASC).
     * - I km extra vengono sommati DOPO l’applicazione del tier (override/sconto).
     * - Tier override €/g: viene trattato come NUOVA BASE, su cui si ricalcolano le maggiorazioni.
     * - Tier sconto %: viene applicato sulla quota giorni già maggiorata (stagioni/weekend) e prima dei km extra.
     * - La quote include breakdown “client-friendly” (day_groups + subtotali), utile per PDF.
     */
    public function quote(
        VehiclePricelist $pl,
        \DateTimeInterface $pickupAt,
        \DateTimeInterface $dropoffAt,
        int $expectedKm = 0
    ): array {
        $start = CarbonImmutable::instance($pickupAt)->tz('Europe/Rome');
        $end   = CarbonImmutable::instance($dropoffAt)->tz('Europe/Rome');

        // giorni arrotondati per eccesso sulle ORE REALI (gestisce DST)
        $hours = $start->floatDiffInRealHours($end);
        $days  = max(1, (int) ceil($hours / 24));

        /**
         * Precarico stagioni/tiers in memoria per evitare query per giorno.
         * PRIORITÀ: 0 = più alta -> ordinamento ASC.
         * Uso reorder() per annullare eventuali orderBy definiti nella relazione del Model.
         */
        $seasons = $pl->seasons()
            ->where('is_active', true)
            ->reorder('priority', 'asc')
            ->get();

        $tiers = $pl->tiers()
            ->where('is_active', true)
            ->reorder('priority', 'asc')
            ->get();

        $dailyTotals = 0;

        /**
         * Breakdown “client-safe” della quota giorni:
         * raggruppa per (stagione applicata + tipo giorno) per rendere la stampa chiara.
         */
        $dayGroups = [];

        for ($i = 0; $i < $days; $i++) {
            $d = $start->addDays($i);
            $isWeekend = in_array($d->dayOfWeekIso, [6, 7], true);

            /**
             * Stagione del giorno:
             * first() su collection già ordinata per priorità ASC -> 0 vince.
             */
            $season = $seasons->first(fn ($s) => $s->matchesDate($d));

            // Base in cents (es. 3500 = 35,00€)
            $baseDaily = (int) $pl->base_daily_cents;

            /**
             * Componenti (tutte calcolate sulla BASE, modello additivo):
             * - add_weekend_base: % weekend del listino base
             * - add_season: % stagione
             * - add_season_weekend: % weekend della stagione (aggiuntiva, non sostitutiva)
             */
            $addWeekendBase   = 0;
            $addSeason        = 0;
            $addSeasonWeekend = 0;

            if ($isWeekend && (int) $pl->weekend_pct !== 0) {
                $addWeekendBase = (int) round($baseDaily * ((int) $pl->weekend_pct / 100));
            }

            if ($season && (int) $season->season_pct !== 0) {
                $addSeason = (int) round($baseDaily * ((int) $season->season_pct / 100));
            }

            if (
                $isWeekend
                && $season
                && !is_null($season->weekend_pct_override)
                && (int) $season->weekend_pct_override !== 0
            ) {
                $addSeasonWeekend = (int) round($baseDaily * ((int) $season->weekend_pct_override / 100));
            }

            // Tariffa finale del giorno (additiva sulla base)
            $daily = $baseDaily + $addWeekendBase + $addSeason + $addSeasonWeekend;

            // Somma quota giorni (PRIMA dei tiers)
            $dailyTotals += $daily;

            /**
             * Raggruppamento per stampa (stagione + weekday/weekend)
             */
            $seasonId   = $season?->id ?? 'base';
            $seasonName = $season?->name ?? 'Base';
            $seasonPrio = $season?->priority ?? 999;

            $key = $seasonId . '|' . ($isWeekend ? 'WE' : 'WD');

            if (!isset($dayGroups[$key])) {
                $dayGroups[$key] = [
                    'season_name'        => (string) $seasonName,
                    'season_priority'    => (int) $seasonPrio,
                    'is_weekend'         => (bool) $isWeekend,
                    'days'               => 0,
                    'daily_cents'        => (int) $daily,
                    'total_cents'        => 0,

                    // componenti esplicative (cents)
                    'base_daily_cents'   => (int) $baseDaily,
                    'add_weekend_base'   => (int) $addWeekendBase,
                    'add_season'         => (int) $addSeason,
                    'add_season_weekend' => (int) $addSeasonWeekend,

                    // % per etichetta (utile nel PDF)
                    'weekend_pct'        => (int) $pl->weekend_pct,
                    'season_pct'         => (int) ($season?->season_pct ?? 0),
                    'season_weekend_pct' => !is_null($season?->weekend_pct_override)
                        ? (int) $season->weekend_pct_override
                        : null,
                ];
            }

            $dayGroups[$key]['days'] += 1;
            $dayGroups[$key]['total_cents'] += $daily;
        }

        /**
         * Snapshot “senza tier”:
         * utile per:
         * - calcolare lo sconto effettivo (specialmente nel caso override-base)
         * - stampare “risparmio rispetto al listino senza tier”
         */
        $dailyTotalsReference = (int) $dailyTotals;
        $dayGroupsReference   = $dayGroups;

        /**
         * Helper: calcola tariffa giornaliera con modello additivo alla base,
         * usando rounding per componente (coerente con il loop principale).
         *
         * @return array{base:int, add_weekend_base:int, add_season:int, add_season_weekend:int, daily:int}
         */
        $computeDailyFromBase = function (
            int $baseDaily,
            bool $isWeekend,
            int $weekendPct,
            int $seasonPct,
            ?int $seasonWeekendPct
        ): array {
            $addWeekendBase   = 0;
            $addSeason        = 0;
            $addSeasonWeekend = 0;

            // + weekend base (sempre sulla base)
            if ($isWeekend && $weekendPct !== 0) {
                $addWeekendBase = (int) round($baseDaily * ($weekendPct / 100));
            }

            // + stagione (sempre sulla base)
            if ($seasonPct !== 0) {
                $addSeason = (int) round($baseDaily * ($seasonPct / 100));
            }

            // + weekend stagione (sempre sulla base, solo se weekend e se valorizzato)
            if ($isWeekend && !is_null($seasonWeekendPct) && (int) $seasonWeekendPct !== 0) {
                $addSeasonWeekend = (int) round($baseDaily * (((int) $seasonWeekendPct) / 100));
            }

            $daily = $baseDaily + $addWeekendBase + $addSeason + $addSeasonWeekend;

            return [
                'base'               => $baseDaily,
                'add_weekend_base'   => $addWeekendBase,
                'add_season'         => $addSeason,
                'add_season_weekend' => $addSeasonWeekend,
                'daily'              => $daily,
            ];
        };

        /**
         * Helper: ricalcola un gruppo (stagione+tipo giorno) a partire da una base differente (override),
         * mantenendo il modello additivo e il rounding.
         */
        $recomputeGroupForBase = function (array $g, int $baseDailyCents) use ($computeDailyFromBase): array {
            $isWeekend        = (bool) ($g['is_weekend'] ?? false);
            $weekendPct       = (int)  ($g['weekend_pct'] ?? 0);
            $seasonPct        = (int)  ($g['season_pct'] ?? 0);
            $seasonWeekendPct = $g['season_weekend_pct'] ?? null;

            $calc = $computeDailyFromBase(
                $baseDailyCents,
                $isWeekend,
                $weekendPct,
                $seasonPct,
                is_null($seasonWeekendPct) ? null : (int) $seasonWeekendPct
            );

            $daysCount = (int) ($g['days'] ?? 0);

            // Aggiorno SOLO le parti numeriche, lasciando meta-dati invariati
            $g['base_daily_cents']   = (int) $calc['base'];
            $g['add_weekend_base']   = (int) $calc['add_weekend_base'];
            $g['add_season']         = (int) $calc['add_season'];
            $g['add_season_weekend'] = (int) $calc['add_season_weekend'];

            $g['daily_cents'] = (int) $calc['daily'];
            $g['total_cents'] = (int) ($g['daily_cents'] * $daysCount);

            return $g;
        };

        /**
         * Km extra (calcolati ma sommati DOPO il tier).
         * Mantengo anche i dettagli per stampa/trasparenza.
         */
        $extra = 0;
        $included = 0;
        $excess = 0;

        if ($pl->km_included_per_day && $pl->extra_km_cents) {
            $included = (int) ($pl->km_included_per_day * $days);
            $excess   = (int) max(0, $expectedKm - $included);
            $extra    = (int) ($excess * $pl->extra_km_cents);
        }

        /**
         * Subtotale quota giorni (prima del tier) = scenario reference “senza tier”
         */
        $subtotalDaysBeforeTier = (int) $dailyTotalsReference;

        /**
         * Applica tier in base ai giorni:
         * first() su collection ordinata per priorità ASC -> 0 vince.
         */
        $tier = $tiers->first(fn ($t) => $t->matchesDays($days));

        // Quota giorni dopo tier (prima dei km extra)
        $subtotalDaysAfterTier = (int) $subtotalDaysBeforeTier;

        // Differenza tier (negativa = sconto; positiva = aumento)
        $tierAdjustmentCents = 0;

        // Risparmio effettivo (sempre >= 0) rispetto allo scenario senza tier
        $tierSavingsCents = 0;

        // Breakdown post-tier (utile soprattutto per override-base)
        $dayGroupsAfterTier = null;

        if ($tier) {
            if (!is_null($tier->override_daily_cents)) {
                /**
                 * ✅ OVERRIDE €/g = NUOVA BASE:
                 * Applico l’override alla base e ricalcolo TUTTE le maggiorazioni (weekend/stagione/weekend stagione)
                 * sulla base override.
                 *
                 * Per evitare loop sui giorni, ricalcolo sui gruppi (stagione+tipo giorno).
                 */
                $overrideBase = (int) $tier->override_daily_cents;

                $subtotalDaysAfterTier = 0;
                $recomputedGroups = [];

                foreach ($dayGroupsReference as $key => $g) {
                    $recomputedGroups[$key] = $recomputeGroupForBase($g, $overrideBase);
                    $subtotalDaysAfterTier += (int) $recomputedGroups[$key]['total_cents'];
                }

                // Lista ordinata per stampa (0 prima; feriali prima)
                $dayGroupsAfterTierList = array_values($recomputedGroups);
                usort($dayGroupsAfterTierList, function (array $a, array $b): int {
                    if (($a['season_priority'] ?? 999) !== ($b['season_priority'] ?? 999)) {
                        return ((int) $a['season_priority']) <=> ((int) $b['season_priority']);
                    }
                    return ((int) ($a['is_weekend'] ?? 0)) <=> ((int) ($b['is_weekend'] ?? 0));
                });

                $dayGroupsAfterTier = $dayGroupsAfterTierList;
            } elseif (!is_null($tier->discount_pct) && (int) $tier->discount_pct > 0) {
                /**
                 * ✅ SCONTO %:
                 * applicato SOLO alla quota giorni (già maggiorata) e PRIMA dei km extra.
                 */
                $subtotalDaysAfterTier = (int) round(
                    $subtotalDaysBeforeTier * (1 - ((int) $tier->discount_pct / 100))
                );
            }

            $tierAdjustmentCents = (int) ($subtotalDaysAfterTier - $subtotalDaysBeforeTier);
            $tierSavingsCents    = (int) max(0, $subtotalDaysBeforeTier - $subtotalDaysAfterTier);
        }

        /**
         * Subtotale dopo tier (quota giorni) + km extra.
         * I km extra NON vengono scontati dal tier.
         */
        $subtotalAfterTier = (int) ($subtotalDaysAfterTier + $extra);

        /**
         * Arrotondamento (totale senza cauzione)
         */
        $totalRounded = $this->applyRounding($subtotalAfterTier, $pl->rounding);

        // Delta arrotondamento (per stampa trasparente)
        $roundingDeltaCents = (int) ($totalRounded - $subtotalAfterTier);

        /**
         * Ordino i gruppi reference per priorità stagione e poi feriale/weekend (feriali prima).
         * (Così la stampa risulta coerente e leggibile)
         */
        $dayGroupsList = array_values($dayGroups);
        usort($dayGroupsList, function (array $a, array $b): int {
            if (($a['season_priority'] ?? 999) !== ($b['season_priority'] ?? 999)) {
                return ((int) $a['season_priority']) <=> ((int) $b['season_priority']); // 0 prima
            }
            // feriale (false=0) prima del weekend (true=1)
            return ((int) ($a['is_weekend'] ?? 0)) <=> ((int) ($b['is_weekend'] ?? 0));
        });

        // === Costo L/T giornaliero e margine (solo per simulatore interno) ===
        // Prova a recuperare il veicolo (via relazione o vehicle_id)
        $vehicle = $pl->relationLoaded('vehicle') ? $pl->vehicle : ($pl->vehicle ?? null);
        if (!$vehicle && property_exists($pl, 'vehicle_id')) {
            $vehicle = Vehicle::find($pl->vehicle_id);
        }

        $ltDailyCost = 0; // in cents/giorno
        if ($vehicle && !empty($vehicle->lt_rental_monthly_cents)) {
            // mensile * 12 / 365 (arrotondato ai cents)
            $ltDailyCost = (int) round(($vehicle->lt_rental_monthly_cents * 12) / 365);
        }

        // Prezzo medio/giorno simulato (escludendo cauzione, usando il totale arrotondato)
        $avgDailyPrice = (int) round($totalRounded / $days);

        // Margine giornaliero e totale dopo L/T
        $netDailyAfterLt = $avgDailyPrice - $ltDailyCost;
        $netTotalAfterLt = $netDailyAfterLt * $days;

        return [
            'days'        => $days,

            /**
             * Quota giorni “reference” (senza tier):
             * resta utile nel simulatore e nel PDF per mostrare il calcolo base+stagioni+weekend.
             */
            'daily_total' => (int) $dailyTotalsReference,

            'km_extra'    => (int) $extra,
            'tier'        => $tier?->only(['name', 'override_daily_cents', 'discount_pct', 'priority']),
            'deposit'     => (int) ($pl->deposit_cents ?? 0),
            'total'       => (int) $totalRounded, // senza cauzione, già arrotondato
            'currency'    => $pl->currency,

            // === Campi “client-friendly” per stampa / trasparenza ===
            'day_groups'                => $dayGroupsList,                 // reference
            'day_groups_after_tier'     => $dayGroupsAfterTier,            // valorizzato solo per override-base
            'subtotal_days_before_tier' => (int) $subtotalDaysBeforeTier,  // quota giorni reference
            'subtotal_days_after_tier'  => (int) $subtotalDaysAfterTier,   // quota giorni dopo tier
            'tier_adjustment_cents'     => (int) $tierAdjustmentCents,     // after - before (negativo = sconto)
            'tier_savings_cents'        => (int) $tierSavingsCents,        // max(0, before - after)
            'km_included_total'         => (int) $included,
            'km_excess'                 => (int) $excess,
            'extra_km_cents_per_km'     => (int) ($pl->extra_km_cents ?? 0),
            'rounding_delta_cents'      => (int) $roundingDeltaCents,

            // === Campi per simulatore interno (NON per cliente) ===
            'lt_daily_cost'      => $ltDailyCost,
            'avg_daily_price'    => $avgDailyPrice,
            'net_daily_after_lt' => $netDailyAfterLt,
            'net_total_after_lt' => $netTotalAfterLt,
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
