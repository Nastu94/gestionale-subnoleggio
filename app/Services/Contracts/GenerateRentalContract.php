<?php

namespace App\Services\Contracts;

use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\Organization;
use App\Models\Location;
use App\Models\Customer;
use App\Domain\Pricing\VehiclePricingService;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GenerateRentalContract
{
    public function __construct(
        protected VehiclePricingService $pricing,
        protected ViewFactory $view
    ) {}

    public function handle(
        Rental $rental,
        ?array $coverage = null,
        ?array $franchise = null,
        ?int $expectedKm = null
    ) {
        // ---------------------------------------------------------------------
        // 1) RELAZIONI BASE (INVARIATO)
        // ---------------------------------------------------------------------
        $vehicle  = $rental->vehicle()->first();
        $customer = $rental->customer()->first();

        /** @var Organization|null $org */
        $org = $rental->organization()->first() ?: Organization::query()->first();

        $pickup = $rental->pickupLocation()->first();
        $return = $rental->returnLocation()->first();

        // ---------------------------------------------------------------------
        // 1.b) NEW — ORGANIZZAZIONE VEICOLO (per nome sotto titolo)
        // ---------------------------------------------------------------------
        /** 
         * Organization a cui è assegnato il veicolo.
         * Serve SOLO per la dicitura "Nome Noleggiante: ..."
         */
        $vehicleOrg = $vehicle?->adminOrganization()->first();

        // ---------------------------------------------------------------------
        // 1.c) NEW — NOLEGGIANTE EFFETTIVO (licenza → fallback AMD)
        // ---------------------------------------------------------------------
        $hasValidLicense =
            $org &&
            $org->rental_license &&
            (
                !$org->rental_license_expires_at ||
                $org->rental_license_expires_at->isFuture()
            );

        /** @var Organization|null $lessorOrg */
        $lessorOrg = $hasValidLicense
            ? $org
            : Organization::query()
                ->where('name', 'AMD Mobility')
                ->first();

        // ---------------------------------------------------------------------
        // 2) PRICING (INVARIATO)
        // ---------------------------------------------------------------------
        $pricingData = [
            'days'              => null,
            'base_total'        => null,
            'extras'            => [],
            'currency'          => null,
            'deposit'           => null,
            'km_daily_limit'    => null,
            'included_km_total' => null,
            'extra_km_rate'     => null,
        ];

        $days = 1;

        if ($vehicle) {
            $pl = $this->pricing->findActivePricelistForCurrentRenter($vehicle);
            if ($pl) {
                $q = $this->pricing->quote(
                    $pl,
                    $rental->planned_pickup_at ?? Carbon::now(),
                    $rental->planned_return_at ?? Carbon::now()->addDay(),
                    (int) ($expectedKm ?? 0)
                );

                $days = (int) ($q['days'] ?? 1);

                $kmDailyLimit = $pl->km_included_per_day;
                $includedKmTotal = is_null($kmDailyLimit) ? null : ($kmDailyLimit * $days);
                $extraKmRate = $pl->extra_km_cents ? ($pl->extra_km_cents / 100) : null;

                $pricingData = [
                    'days'              => $days,
                    'base_total'        => isset($q['total'])
                        ? number_format($q['total'] / 100, 2, ',', '.') . ' ' . ($q['currency'] ?? '')
                        : null,
                    'extras'            => [],
                    'currency'          => $q['currency'] ?? 'EUR',
                    'deposit'           => isset($q['deposit'])
                        ? number_format($q['deposit'] / 100, 2, ',', '.') . ' ' . ($q['currency'] ?? '')
                        : null,
                    'km_daily_limit'    => $kmDailyLimit,
                    'included_km_total' => $includedKmTotal,
                    'extra_km_rate'     => $extraKmRate,
                ];
            }
        }

        // ---------------------------------------------------------------------
        // 2.b) NEW — SECONDA GUIDA
        // ---------------------------------------------------------------------
        $secondDriver = $rental->secondDriver()->first();

        $secondDriverFeeDaily = 6.00;
        $secondDriverTotal = $secondDriver
            ? ($secondDriverFeeDaily * max(1, $days))
            : 0.0;

        // ---------------------------------------------------------------------
        // 2.c) NEW — PREZZO FINALE (override)
        // ---------------------------------------------------------------------
        $baseAmount = (float) ($rental->amount ?? 0);

        $finalAmount = $rental->final_amount_override !== null
            ? (float) $rental->final_amount_override
            : $baseAmount + $secondDriverTotal;

        // ---------------------------------------------------------------------
        // 3) COPERTURE E FRANCHIGIE (INVARIATO)
        // ---------------------------------------------------------------------
        $rc = $rental->coverage()->first();

        $dbCoverageFlags = [
            'rca'            => (bool)($rc->rca ?? true),
            'kasko'          => (bool)($rc->kasko ?? false),
            'furto_incendio' => (bool)($rc->furto_incendio ?? false),
            'cristalli'      => (bool)($rc->cristalli ?? false),
            'assistenza'     => (bool)($rc->assistenza ?? false),
        ];

        $dbFranchise = [
            'rca'             => isset($rc?->franchise_rca)            ? (float)$rc->franchise_rca : null,
            'kasko'           => isset($rc?->franchise_kasko)          ? (float)$rc->franchise_kasko : null,
            'furto_incendio'  => isset($rc?->franchise_furto_incendio) ? (float)$rc->franchise_furto_incendio : null,
            'cristalli'       => isset($rc?->franchise_cristalli)      ? (float)$rc->franchise_cristalli : null,
        ];

        $coverage = array_merge($dbCoverageFlags, $coverage ?? []);
        $coverage['rca'] = true;

        $baseFranchise = [
            'rca'            => $vehicle?->insurance_rca_cents       !== null ? $vehicle->insurance_rca_cents / 100 : null,
            'kasko'          => $vehicle?->insurance_kasko_cents     !== null ? $vehicle->insurance_kasko_cents / 100 : null,
            'cristalli'      => $vehicle?->insurance_cristalli_cents !== null ? $vehicle->insurance_cristalli_cents / 100 : null,
            'furto_incendio' => $vehicle?->insurance_furto_cents     !== null ? $vehicle->insurance_furto_cents / 100 : null,
        ];

        $finalFranchise = [];
        foreach (['rca','kasko','cristalli','furto_incendio'] as $key) {
            $overrideRaw = $franchise[$key] ?? null;
            $base        = $baseFranchise[$key] ?? null;

            $override = (is_string($overrideRaw) && trim($overrideRaw) === '') ? null : $overrideRaw;
            $finalFranchise[$key] = is_numeric($override) ? (float) $override : $base;
        }

        // ---------------------------------------------------------------------
        // 4) CLAUSOLE (INVARIATO PER ORA)
        // ---------------------------------------------------------------------
        $clauses = config('rental.clauses', []);

        // ---------------------------------------------------------------------
        // 5) DTO PER BLADE (ESTESO, NON ROTTO)
        // ---------------------------------------------------------------------
        $vars = [
            'org' => [
                'name'    => $lessorOrg?->name ?? '—',
                'vat'     => $lessorOrg?->vat ?? null,
                'address' => $lessorOrg?->address ?? null,
                'zip'     => $lessorOrg?->zip ?? null,
                'city'    => $lessorOrg?->city ?? null,
                'phone'   => $lessorOrg?->phone ?? null,
                'email'   => $lessorOrg?->email ?? null,
            ],

            // NEW
            'vehicle_owner_name' => $vehicleOrg?->name ?? $org?->name ?? '—',
            'final_amount'       => $finalAmount,
            'second_driver'      => $secondDriver,
            'second_driver_fee'  => $secondDriverFeeDaily,
            'second_driver_total'=> $secondDriverTotal,

            'rental' => [
                'id'              => $rental->id,
                'issued_at'       => now()->format('d/m/Y'),
                'pickup_at'       => optional($rental->planned_pickup_at)->timezone('Europe/Rome')?->format('d/m/Y H:i'),
                'return_at'       => optional($rental->planned_return_at)->timezone('Europe/Rome')?->format('d/m/Y H:i'),
                'pickup_location' => $pickup?->name,
                'return_location' => $return?->name,
            ],

            'customer' => [
                'name'          => $customer?->name ?? '—',
                'doc_id_type'   => $customer?->doc_id_type ?? null,
                'doc_id_number' => $customer?->doc_id_number ?? null,
                'address'       => $customer?->address ?? null,
                'zip'           => $customer?->zip ?? null,
                'city'          => $customer?->city ?? null,
                'province'      => $customer?->province ?? null,
                'phone'         => $customer?->phone ?? null,
                'email'         => $customer?->email ?? null,
            ],

            'vehicle' => [
                'make'  => $vehicle?->make ?? '—',
                'model' => $vehicle?->model ?? null,
                'plate' => $vehicle?->plate ?? null,
                'color' => $vehicle?->color ?? null,
                'vin'   => $vehicle?->vin ?? null,
            ],

            'pricing'    => $pricingData,
            'coverages'  => $coverage,
            'franchigie' => $finalFranchise,
            'clauses'    => $clauses,
        ];

        // ---------------------------------------------------------------------
        // 6–8) PDF + MEDIA LIBRARY (INVARIATO)
        // ---------------------------------------------------------------------
        $html = $this->view->make('contracts.rental', $vars)->render();

        $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait')->loadHTML($html);
        $binary = $pdf->output();

        $rental->getMedia('contract')->each(fn ($m) => $m->setCustomProperty('current', false)->save());

        $media = $rental->addMediaFromString($binary)
            ->usingFileName('contratto-'.$rental->id.'-'.now()->format('Ymd_His').'.pdf')
            ->withCustomProperties(['current' => true])
            ->toMediaCollection('contract');

        return $media;
    }
}
