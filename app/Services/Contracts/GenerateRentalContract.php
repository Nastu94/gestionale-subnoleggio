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

/**
 * Service responsabile di:
 * - raccogliere i dati (org/rental/customer/vehicle/pricing/coverage/clausole)
 * - renderizzare il Blade del contratto
 * - generare il PDF
 * - salvare il PDF su Media Library (Rental → contract)
 * - marcare come “valido” SOLO l’ultimo contratto generato (custom_property)
 *
 * Dipendenze:
 * - VehiclePricingService: per trovare il listino e calcolare giorni/extra km/total ecc.
 * - Dompdf (barryvdh/laravel-dompdf) o equivalente: qui usiamo Dompdf.
 */
class GenerateRentalContract
{
    public function __construct(
        protected VehiclePricingService $pricing,
        protected ViewFactory $view // per render blade in stringa HTML
    ) {}

    /**
     * Genera e salva il PDF del contratto per un Rental.
     *
     * @param  \App\Models\Rental  $rental  Noleggio target (deve avere vehicle_id, planned_* e idealmente customer)
     * @param  array|null $coverage  Flags coperture (kasko, furto_incendio, cristalli, assistenza) [bool]
     * @param  array|null $franchise Importi franchigie (kasko, furto_incendio, cristalli) [float|int]
     * @param  int|null   $expectedKm  Km previsti (per preventivo; opzionale)
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Media  media del contratto appena salvato
     */
    public function handle(Rental $rental, ?array $coverage = null, ?array $franchise = null, ?int $expectedKm = null)
    {
        // --- 1) Carica relazioni e contesto
        /** @var Vehicle|null $vehicle */
        $vehicle = $rental->vehicle()->first();
        /** @var Customer|null $customer */
        $customer = $rental->customer()->first();

        // Organization del renter corrente: prendo quella legata al rental/utente/azienda
        // Adatta se hai relazione diretta (es: $rental->organization).
        /** @var Organization|null $org */
        $org = $rental->organization()->first() ?: Organization::query()->first();

        /** @var Location|null $pickup */
        $pickup = $rental->pickupLocation()->first();
        /** @var Location|null $return */
        $return = $rental->returnLocation()->first();

        // --- 2) Pricing: listino attivo + quote
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

        if ($vehicle) {
            $pl = $this->pricing->findActivePricelistForCurrentRenter($vehicle);
            if ($pl) {
                // expectedKm può essere null ⇒ lo tratto come 0 (preventivo base)
                $q = $this->pricing->quote(
                    $pl,
                    $rental->planned_pickup_at ?? Carbon::now(),
                    $rental->planned_return_at ?? Carbon::now()->addDay(),
                    (int) ($expectedKm ?? 0)
                );

                // compongo i dati utili al template
                $days = (int) ($q['days'] ?? 1);
                $kmDailyLimit = $pl->km_included_per_day;             // int|null
                $includedKmTotal = is_null($kmDailyLimit) ? null : ($kmDailyLimit * $days);
                $extraKmRate = $pl->extra_km_cents ? ($pl->extra_km_cents / 100) : null;

                $pricingData = [
                    'days'              => $days,
                    'base_total'        => isset($q['total']) ? number_format($q['total']/100, 2, ',', '.') . ' ' . ($q['currency'] ?? '') : null,
                    'extras'            => [], // se/come decidi di passarli
                    'currency'          => $q['currency'] ?? 'EUR',
                    'deposit'           => isset($q['deposit']) ? number_format($q['deposit']/100, 2, ',', '.') . ' ' . ($q['currency'] ?? '') : null,
                    'km_daily_limit'    => $kmDailyLimit,                    // int|null (null = illimitato)
                    'included_km_total' => $includedKmTotal,                 // int|null (se illimitato)
                    'extra_km_rate'     => $extraKmRate,                     // float|null
                ];
            }
        }

        // --- 3) Coperture & franchigie (flags + override + fallback da DB/veicolo) -----

        // 3.a) Legge dal DB (rental_coverages) se presente
        $rc = $rental->coverage()->first(); // relazione 1:1; può essere null

        // Flags dal DB (default sicuri). NOTA: rca rimane true per policy.
        $dbCoverageFlags = [
            'rca'            => (bool)($rc->rca ?? true),
            'kasko'          => (bool)($rc->kasko ?? false),
            'furto_incendio' => (bool)($rc->furto_incendio ?? false),
            'cristalli'      => (bool)($rc->cristalli ?? false),
            'assistenza'     => (bool)($rc->assistenza ?? false),
        ];

        // Franchigie dal DB (decimal:2 → stringhe): normalizziamo a float|null
        $dbFranchise = [
            'rca'             => isset($rc?->franchise_rca)              ? (float)$rc->franchise_rca : null,
            'kasko'           => isset($rc?->franchise_kasko)            ? (float)$rc->franchise_kasko : null,
            'furto_incendio'  => isset($rc?->franchise_furto_incendio)   ? (float)$rc->franchise_furto_incendio : null,
            'cristalli'       => isset($rc?->franchise_cristalli)        ? (float)$rc->franchise_cristalli : null,
        ];

        // 3.b) Flags finali: DB come base, eventuali override dal parametro $coverage
        //     (se l’array è passato dal wizard, vince sui valori del DB)
        $coverage = array_merge($dbCoverageFlags, $coverage ?? []);
        $coverage['rca'] = true; // cintura & bretelle: RCA sempre attiva

        // 3.c) Franchigie “base” dal veicolo (come prima, in EUR)
        $baseFranchise = [
            'rca'             => $vehicle?->insurance_rca_cents        !== null ? $vehicle->insurance_rca_cents        / 100 : null,
            'kasko'           => $vehicle?->insurance_kasko_cents      !== null ? $vehicle->insurance_kasko_cents      / 100 : null,
            'cristalli'       => $vehicle?->insurance_cristalli_cents  !== null ? $vehicle->insurance_cristalli_cents  / 100 : null,
            // DB: insurance_furto_cents → chiave logica 'furto_incendio'
            'furto_incendio'  => $vehicle?->insurance_furto_cents      !== null ? $vehicle->insurance_furto_cents      / 100 : null,
        ];

        // 3.d) Franchigie finali: priorità (1) param → (2) DB → (3) veicolo
        $inputFranchise = $franchise ?? []; // se passato, sovrascrive DB
        $finalFranchise = [];

        foreach (['rca','kasko','cristalli','furto_incendio'] as $key) {
            $overrideRaw = $inputFranchise[$key] ?? null;
            $fromDb      = $dbFranchise[$key]    ?? null;
            $fromVeh     = $baseFranchise[$key]  ?? null;

            // Normalizza stringhe vuote a null
            $override = (is_string($overrideRaw) && trim($overrideRaw) === '') ? null : $overrideRaw;

            // Scelta valore con precedenza: param → DB → veicolo
            if (is_numeric($override)) {
                $value = (float)$override;
            } elseif ($fromDb !== null) {
                $value = (float)$fromDb;
            } else {
                $value = $fromVeh; // può essere null
            }

            $finalFranchise[$key] = $value;
        }

        // (facciamo ricadere le variabili usate nel template)
        $coverage['rca'] = true; // doppia garanzia

        /**
         * Nuova logica deterministica:
         * - includiamo SEMPRE tutte le chiavi in $finalFranchise
         * - valore = override numerico se presente, altrimenti base del veicolo
         * - il Blade decide se mostrarlo: se copertura attiva (o RCA) e valore non null → stampa "Franchigia: € …"
         */
        $finalFranchise = [];
        foreach (['rca','kasko','cristalli','furto_incendio'] as $key) {
            $overrideRaw = $franchise[$key]   ?? null;
            $base        = $baseFranchise[$key] ?? null;

            // Normalizza override: stringa vuota → null
            $override = (is_string($overrideRaw) && trim($overrideRaw) === '') ? null : $overrideRaw;

            // Usa override se numerico, altrimenti la base
            $value = is_numeric($override) ? (float) $override : $base;

            // Inseriamo SEMPRE la chiave (anche null) per semplificare il Blade
            $finalFranchise[$key] = $value;
        }

        // Sovrascrivi i dati passati al Blade
        $coverage['rca'] = true; // doppia cintura

        // --- 4) Clausole standard da config
        $clauses = config('rental.clauses', []);

        // --- 5) DTO per il Blade
        $vars = [
            'org' => [
                'name'    => $org?->name ?? '—',
                'vat'     => $org?->vat ?? null,
                'address' => $org?->address ?? null,
                'zip'     => $org?->zip ?? null,
                'city'    => $org?->city ?? null,
                'phone'   => $org?->phone ?? null,
                'email'   => $org?->email ?? null,
            ],
            'rental' => [
                'id'               => $rental->id,
                'issued_at'        => now()->format('d/m/Y'),
                'pickup_at'        => optional($rental->planned_pickup_at)->timezone('Europe/Rome')?->format('d/m/Y H:i'),
                'return_at'        => optional($rental->planned_return_at)->timezone('Europe/Rome')?->format('d/m/Y H:i'),
                'pickup_location'  => $pickup?->name,
                'return_location'  => $return?->name,
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
                'make'   => $vehicle?->make ?? '—', // ⚠️ usa make, non brand
                'model'  => $vehicle?->model ?? null,
                'plate'  => $vehicle?->plate ?? null,
                'color'  => $vehicle?->color ?? null,
                'vin'    => $vehicle?->vin ?? null,
            ],
            'pricing'    => $pricingData,
            'coverages'  => $coverage,        // include rca=true
            'franchigie' => $finalFranchise,  // valori finali (base + eventuale override)
            'clauses'    => $clauses,
        ];

        // --- 6) Render HTML del contratto
        $html = $this->view->make('contracts.rental', $vars)->render();

        // --- 7) Genera PDF (Dompdf).
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper')
            ->setPaper('a4', 'portrait')
            ->loadHTML($html);

        $binary = $pdf->output();

        // --- 8) Salvataggio su Media Library (Rental → contract), marcando solo l’ultimo come “valido”
        // Disattivo il flag "current" sui precedenti
        $rental->getMedia('contract')->each(function ($m) {
            $m->setCustomProperty('current', false)->save();
        });

        $ts = Carbon::now()->format('Ymd_His');
        $filename = 'contratto-'.$rental->id.'-'.$ts.'.pdf';

        $media = $rental->addMediaFromString($binary)
            ->usingFileName($filename)
            ->withCustomProperties([
                'current' => true,              // l’ultimo è quello valido
                'generated_at' => now()->toIso8601String(),
            ])
            ->toMediaCollection('contract');

        return $media;
    }
}
