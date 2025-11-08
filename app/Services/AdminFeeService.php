<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationFee;
use App\Models\Rental;
use App\Models\RentalCharge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Calcolo e snapshot delle fee admin.
 *
 * - resolvePercentForOrganization(): trova la percentuale attiva in una data.
 * - snapshotOnClose(): fotografa percentuale e importo su un rental che sta chiudendo.
 */
class AdminFeeService
{
    /**
     * Restituisce la percentuale fee attiva per l'organization alla data $date.
     * Se non trovata, ritorna 0.00.
     */
    public function resolvePercentForOrganization(Organization $org, \DateTimeInterface|string $date): float
    {
        $d = $date instanceof \DateTimeInterface ? $date : CarbonImmutable::parse($date);

        /** @var OrganizationFee|null $fee */
        $fee = $org->fees()
            ->activeAt($d)
            ->orderByDesc('effective_from')  // in caso di multipli edge-case, prendi la più recente
            ->first();

        return $fee ? (float) $fee->percent : 0.0;
    }

    /**
     * Fotografa percentuale e importo sul rental (idempotente).
     * - Base Tᶜ: somma amount delle righe commissionabili (is_commissionable = 1).
     * - Percentuale: fee attiva per l'organization alla data $at (default: now()).
     * - Arrotondamento 2 decimali (mezzi su).
     */
    public function snapshotOnClose(Rental $rental, ?\DateTimeInterface $at = null): void
    {
        // Se c'è già uno snapshot, non sovrascrivo (idempotenza)
        if (!is_null($rental->admin_fee_percent) && !is_null($rental->admin_fee_amount)) {
            return;
        }

        $org = $rental->organization; // relazione già presente nel tuo dominio
        if (!$org) {
            // Nessuna organization: non calcolo nulla
            $rental->admin_fee_percent = 0.00;
            $rental->admin_fee_amount  = 0.00;
            $rental->save();
            return;
        }

        $atDate = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        // 1) Percentuale attiva per il renter
        $percent = $this->resolvePercentForOrganization($org, $atDate);
        $percent = max(0.0, $percent); // evita percent negative per sicurezza

        // 2) Base commissionabile Tᶜ = somma righe commissionabili (amount già IVA inclusa)
        $base = (float) $rental->charges()
            ->where('is_commissionable', true)
            ->sum('amount');

        // 3) Calcolo e salvataggio snapshot
        $fee  = round($base * ($percent / 100), 2);

        DB::transaction(function () use ($rental, $percent, $fee) {
            $rental->admin_fee_percent = $percent;
            $rental->admin_fee_amount  = $fee;
            $rental->save();
        });
    }
}
