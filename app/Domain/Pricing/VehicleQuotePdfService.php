<?php

namespace App\Domain\Pricing;

use App\Models\Vehicle;
use App\Models\VehiclePricelist;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeInterface;

class VehicleQuotePdfService
{
    /**
     * Genera il PDF del preventivo (solo dati cliente, nessun margine/costo interno).
     *
     * @param Vehicle          $vehicle     Veicolo oggetto del preventivo
     * @param VehiclePricelist $pricelist   Listino utilizzato
     * @param DateTimeInterface $pickupAt   Data/ora ritiro
     * @param DateTimeInterface $dropoffAt  Data/ora riconsegna
     * @param int              $expectedKm  Km previsti
     * @param array            $quote       Output di VehiclePricingService::quote()
     *
     * @return string PDF binary (output dompdf)
     */
    public function render(
        Vehicle $vehicle,
        VehiclePricelist $pricelist,
        DateTimeInterface $pickupAt,
        DateTimeInterface $dropoffAt,
        int $expectedKm,
        array $quote
    ): string {
        /**
         * ✅ Sanitizzazione: passiamo alla view SOLO i campi “client-safe”.
         * Evitiamo volutamente lt_daily_cost / avg_daily_price / net_*.
         */
        $safeQuote = [
            'days'        => (int) ($quote['days'] ?? 0),
            'daily_total' => (int) ($quote['daily_total'] ?? 0),
            'km_extra'    => (int) ($quote['km_extra'] ?? 0),
            'deposit'     => (int) ($quote['deposit'] ?? 0),
            'total'       => (int) ($quote['total'] ?? 0),
            'currency'    => (string) ($quote['currency'] ?? 'EUR'),

            // Tier “safe”
            'tier'                   => $quote['tier'] ?? null,
            'subtotal_days_before_tier' => (int) ($quote['subtotal_days_before_tier'] ?? 0),
            'subtotal_days_after_tier'  => (int) ($quote['subtotal_days_after_tier'] ?? 0),
            'tier_adjustment_cents'     => (int) ($quote['tier_adjustment_cents'] ?? 0),

            // Breakdown giornaliero (safe)
            'day_groups' => is_array($quote['day_groups'] ?? null) ? $quote['day_groups'] : [],

            // Km extra (safe + trasparente)
            'km_included_total'      => (int) ($quote['km_included_total'] ?? 0),
            'km_excess'              => (int) ($quote['km_excess'] ?? 0),
            'extra_km_cents_per_km'  => (int) ($quote['extra_km_cents_per_km'] ?? 0),

            // Arrotondamento (safe)
            'rounding_delta_cents'   => (int) ($quote['rounding_delta_cents'] ?? 0),
        ];

        $pdf = Pdf::loadView('pdfs.vehicle-quote', [
            'vehicle'    => $vehicle,
            'pricelist'  => $pricelist,
            'pickupAt'   => $pickupAt,
            'dropoffAt'  => $dropoffAt,
            'expectedKm' => $expectedKm,
            'quote'      => $safeQuote,
            'generatedAt'=> now(),
        ])->setPaper('a4');

        return $pdf->output();
    }

    /**
     * Crea un nome file “safe” e leggibile per il preventivo.
     */
    public function filename(Vehicle $vehicle, DateTimeInterface $pickupAt, DateTimeInterface $dropoffAt): string
    {
        // Tentiamo campi comuni, senza assumere troppo sul model.
        $plate = $vehicle->plate ?? $vehicle->license_plate ?? $vehicle->targa ?? $vehicle->code ?? $vehicle->id;

        $from = (new \Carbon\CarbonImmutable($pickupAt))->format('Ymd');
        $to   = (new \Carbon\CarbonImmutable($dropoffAt))->format('Ymd');

        return 'preventivo_' . $plate . '_' . $from . '_' . $to . '.pdf';
    }
}
