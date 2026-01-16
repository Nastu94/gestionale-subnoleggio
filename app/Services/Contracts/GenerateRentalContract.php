<?php

namespace App\Services\Contracts;

use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\Organization;
use App\Models\Location;
use App\Models\Customer;
use App\Models\RentalContractSnapshot;
use App\Domain\Pricing\VehiclePricingService;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\DB;

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
        ?int $expectedKm = null,
        bool $forceUnsigned = false,
        bool $forceSigned = false 
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
        // 2) PRICING
        // ---------------------------------------------------------------------
        // ✅ Se esiste già un contratto, riuso lo snapshot per NON farmi influenzare da cambi listino
        $storedSnap = $this->getStoredPricingSnapshot($rental);

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

        /**
         * Ponte per usare il totale calcolato dal pricing e il listino attivo
         * senza cambiare la struttura esistente.
         */
        $quoteTotalCents = null;
        $quoteCurrency   = 'EUR';
        $activePricelist = null;

        // ✅ valori "derivati" usati anche dopo
        $kmDailyLimit = null;
        $includedKmTotal = null;
        $extraKmRate = null;

        // ✅ snapshot che poi salverai nel Media del contratto
        $pricingSnapshot = null;

        if (is_array($storedSnap)) {
            // ==========================
            // (A) USO SNAPSHOT CONGELATO
            // ==========================
            $days          = (int) ($storedSnap['days'] ?? 1);
            $days          = max(1, $days);

            $quoteCurrency = (string) ($storedSnap['currency'] ?? 'EUR');

            $quoteTotalCents = array_key_exists('tariff_total_cents', $storedSnap)
                ? (int) $storedSnap['tariff_total_cents']
                : null;

            $kmDailyLimit = $storedSnap['km_daily_limit'] ?? null;

            // extra km: nello snapshot salviamo i cents/km (opzionale)
            $extraKmRate = isset($storedSnap['extra_km_cents']) && is_numeric($storedSnap['extra_km_cents'])
                ? ((int) $storedSnap['extra_km_cents'] / 100)
                : null;

            $includedKmTotal = is_null($kmDailyLimit) ? null : ((int) $kmDailyLimit * $days);

            $depositStr = null;
            if (isset($storedSnap['deposit_cents']) && is_numeric($storedSnap['deposit_cents'])) {
                $depositStr = number_format(((int)$storedSnap['deposit_cents']) / 100, 2, ',', '.') . ' ' . $quoteCurrency;
            }

            $pricingData = [
                'days'              => $days,
                'base_total'        => $quoteTotalCents !== null
                    ? number_format($quoteTotalCents / 100, 2, ',', '.') . ' ' . $quoteCurrency
                    : null,
                'extras'            => [],
                'currency'          => $quoteCurrency,
                'deposit'           => $depositStr,
                'km_daily_limit'    => $kmDailyLimit,
                'included_km_total' => $includedKmTotal,
                'extra_km_rate'     => $extraKmRate,
            ];

            // Lo snapshot è già quello
            $pricingSnapshot = $storedSnap;

        } else {
            // ==========================
            // (B) CALCOLO DA LISTINO ATTIVO
            // ==========================
            if ($vehicle) {
                $pl = $this->pricing->findActivePricelistForCurrentRenter($vehicle);

                if ($pl) {
                    $q = $this->pricing->quote(
                        $pl,
                        $rental->planned_pickup_at ?? Carbon::now(),
                        $rental->planned_return_at ?? Carbon::now()->addDay(),
                        (int) ($expectedKm ?? 0)
                    );

                    // memorizzo listino e totale quote per calcolare extra “seconda guida”
                    $activePricelist = $pl;
                    $quoteTotalCents = isset($q['total']) ? (int) $q['total'] : null;
                    $quoteCurrency   = (string) ($q['currency'] ?? 'EUR');

                    $days = (int) ($q['days'] ?? 1);
                    $days = max(1, $days);

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

                    // ✅ Costruisco lo snapshot congelato (da salvare nel Media)
                    $pricingSnapshot = [
                        'pricelist_id'              => $pl->id,
                        'currency'                  => (string) ($q['currency'] ?? 'EUR'),
                        'days'                      => $days,
                        'tariff_total_cents'        => (int) ($quoteTotalCents ?? 0),
                        'km_daily_limit'            => $kmDailyLimit,
                        'extra_km_cents'            => $pl->extra_km_cents,
                        'deposit_cents'             => $q['deposit'] ?? null,
                        'second_driver_daily_cents' => (int) ($pl->second_driver_daily_cents ?? 0),
                    ];
                }
            }
        }

        // ✅ (opzionale ma utile) se non sono riuscito a calcolare nulla, fallback “robusto”
        if ($pricingSnapshot === null) {
            $pricingSnapshot = [
                'pricelist_id'              => null,
                'currency'                  => (string) $quoteCurrency,
                'days'                      => (int) max(1, $days),
                'tariff_total_cents'        => (int) ($quoteTotalCents ?? 0),
                'km_daily_limit'            => $kmDailyLimit,
                'extra_km_cents'            => null,
                'deposit_cents'             => null,
                'second_driver_daily_cents' => 0,
            ];
        }

        /**
         * ✅ Freeze-once: se non esiste snapshot in tabella lo salvo ORA,
         * prima di generare qualsiasi PDF, così anche il firmato "auto" userà lo stesso snapshot.
         */
        $this->persistPricingSnapshotOnce($rental, $pricingSnapshot);

        // ---------------------------------------------------------------------
        // 2.b) NEW — SECONDA GUIDA (da listino attivo del veicolo)
        // ---------------------------------------------------------------------
        $secondDriver = $rental->secondDriver()->first();

        if (is_array($pricingSnapshot) && isset($pricingSnapshot['second_driver_daily_cents'])) {
            $secondDriverFeeDailyCents = (int) $pricingSnapshot['second_driver_daily_cents'];
        } elseif ($activePricelist && !is_null($activePricelist->second_driver_daily_cents)) {
            $secondDriverFeeDailyCents = (int) $activePricelist->second_driver_daily_cents;
        }

        /**
         * Recupero costo seconda guida dal listino attivo:
         * - se non valorizzato o listino assente => 0
         * - unità: cents/giorno (coerente con il resto del pricing)
         */
        $secondDriverFeeDailyCents = 0;

        if ($activePricelist && !is_null($activePricelist->second_driver_daily_cents)) {
            $secondDriverFeeDailyCents = (int) $activePricelist->second_driver_daily_cents;
        }

        /**
         * Mantengo anche la versione “EUR” perché la usi nel DTO/BLADE.
         */
        $secondDriverFeeDaily = $secondDriverFeeDailyCents / 100;

        /**
         * Totale seconda guida:
         * - solo se esiste la seconda guida
         * - giorni = quelli calcolati dalla quote (già coerenti con DST/ore reali)
         */
        $secondDriverTotalCents = $secondDriver
            ? ($secondDriverFeeDailyCents * max(1, $days))
            : 0;

        $secondDriverTotal = $secondDriverTotalCents / 100;

        // Se vuoi già avere l’extra in pricingData (senza rompere nulla in Blade)
        if ($secondDriver && $secondDriverTotalCents > 0) {
            $pricingData['extras'][] = [
                'label'      => 'Seconda guida',
                'qty'        => max(1, $days),
                'unit_cents' => $secondDriverFeeDailyCents,
                'total_cents'=> $secondDriverTotalCents,
                'unit'       => number_format($secondDriverFeeDaily, 2, ',', '.') . ' ' . $quoteCurrency,
                'total'      => number_format($secondDriverTotal, 2, ',', '.') . ' ' . $quoteCurrency,
            ];
        }

        // ---------------------------------------------------------------------
        // 2.b.1) NEW — TOTALI "CONGELATI" (snapshot) per PDF/DTO
        // ---------------------------------------------------------------------
        /**
         * ⚠️ IMPORTANTISSIMO:
         * - Il PDF deve mostrare SEMPRE i valori congelati nello snapshot,
         *   per NON essere influenzato da cambi listino successivi.
         * - Questo vale sia per tariffa che per seconda guida.
         */
        $snapDays = (int) ($pricingSnapshot['days'] ?? $days ?? 1);
        $snapDays = max(1, $snapDays);

        $snapCurrency = (string) ($pricingSnapshot['currency'] ?? $quoteCurrency ?? 'EUR');

        // Tariffa congelata nello snapshot (NO seconda guida)
        $tariffTotalCents = (int) ($pricingSnapshot['tariff_total_cents'] ?? ($quoteTotalCents ?? 0));

        // Seconda guida: calcolo da daily congelato nello snapshot
        $snapSecondDriverDailyCents = (int) ($pricingSnapshot['second_driver_daily_cents'] ?? 0);

        $snapSecondDriverTotalCents = $secondDriver
            ? ($snapSecondDriverDailyCents * $snapDays)
            : 0;

        $snapComputedTotalCents = $tariffTotalCents + $snapSecondDriverTotalCents;

        // Coerenza per le variabili usate in 2.c e nel DTO
        $baseTotalCents = $tariffTotalCents;               // tariffa (congelata)
        $secondDriverTotalCents = $snapSecondDriverTotalCents; // seconda guida (congelata)
        $secondDriverFeeDailyCents = $snapSecondDriverDailyCents; // daily (congelato)

        $secondDriverFeeDaily = $secondDriverFeeDailyCents / 100;
        $secondDriverTotal    = $secondDriverTotalCents / 100;

        // Aggiorno anche extras in modo coerente (senza rompere nulla)
        // NOTA: lasciamo pure l'extra già aggiunto sopra, ma qui garantiamo coerenza snapshot.
        if ($secondDriver) {
            // rimuovo eventuale "Seconda guida" calcolata da listino attivo (se presente)
            $pricingData['extras'] = collect($pricingData['extras'])
                ->reject(fn ($e) => isset($e['label']) && $e['label'] === 'Seconda guida')
                ->values()
                ->all();

            if ($secondDriverTotalCents > 0) {
                $pricingData['extras'][] = [
                    'label'      => 'Seconda guida',
                    'qty'        => $snapDays,
                    'unit_cents' => $secondDriverFeeDailyCents,
                    'total_cents'=> $secondDriverTotalCents,
                    'unit'       => number_format($secondDriverFeeDaily, 2, ',', '.') . ' ' . $snapCurrency,
                    'total'      => number_format($secondDriverTotal, 2, ',', '.') . ' ' . $snapCurrency,
                ];
            }
        }

        // ---------------------------------------------------------------------
        // 2.c) NEW — PREZZO FINALE (override) + seconda guida DOPO sconti/maggiorazioni
        // ---------------------------------------------------------------------
        /**
         * Base “calcolata”:
         * - se ho una quote => uso il totale in cents (include già stagioni/weekend/tier/km + rounding)
         * - fallback: uso rental->amount (ma convertito in cents)
         */
        $baseAmount = (float) ($rental->amount ?? 0);
        $baseTotalCents = !is_null($quoteTotalCents)
            ? (int) $quoteTotalCents
            : (int) round($baseAmount * 100);

        /**
         * Final:
         * - se esiste override, vince (in EUR -> convertito in cents per coerenza)
         * - altrimenti: totale quote + totale seconda guida
         */
        if ($rental->final_amount_override !== null) {
            $finalAmount = (float) $rental->final_amount_override;
        } else {
            $finalTotalCents = (int) ($baseTotalCents + $secondDriverTotalCents);
            $finalAmount = $finalTotalCents / 100;
        }

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

        $normalizeMoney = function ($raw): ?float {
            if ($raw === null) return null;

            // numeri veri
            if (is_int($raw) || is_float($raw)) return (float) $raw;

            $v = trim((string) $raw);
            if ($v === '') return null;

            // tieni solo cifre, segni e separatori
            $v = preg_replace('/[^\d,\.\-]/', '', $v);
            if ($v === '' || $v === '-' || $v === '-.' || $v === '-,') return null;

            $hasDot   = str_contains($v, '.');
            $hasComma = str_contains($v, ',');

            // Caso 1: contiene sia "." che "," → l’ultimo è il separatore decimale
            if ($hasDot && $hasComma) {
                $lastDot   = strrpos($v, '.');
                $lastComma = strrpos($v, ',');
                $decSep    = ($lastComma > $lastDot) ? ',' : '.';
                $thSep     = ($decSep === ',') ? '.' : ',';

                $v = str_replace($thSep, '', $v);
                if ($decSep === ',') $v = str_replace(',', '.', $v);
            }
            // Caso 2: solo "," → di solito decimale IT, ma se ci sono più virgole -> migliaia
            elseif ($hasComma) {
                $parts = explode(',', $v);
                if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                    $v = str_replace('.', '', $parts[0]) . '.' . $parts[1];
                } else {
                    $v = str_replace(',', '', $v);
                }
            }
            // Caso 3: solo "." → può essere decimale EN oppure migliaia
            elseif ($hasDot) {
                $parts = explode('.', $v);
                if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                    $v = str_replace(',', '', $parts[0]) . '.' . $parts[1];
                } else {
                    $v = str_replace('.', '', $v);
                }
            }

            return is_numeric($v) ? (float) $v : null;
        };

        $dbOverrides = [
            'rca'            => $normalizeMoney($rc?->franchise_rca),
            'kasko'          => $normalizeMoney($rc?->franchise_kasko),
            'furto_incendio' => $normalizeMoney($rc?->franchise_furto_incendio),
            'cristalli'      => $normalizeMoney($rc?->franchise_cristalli),
        ];

        $reqOverrides = [
            'rca'            => $normalizeMoney($franchise['rca'] ?? null),
            'kasko'          => $normalizeMoney($franchise['kasko'] ?? null),
            'furto_incendio' => $normalizeMoney($franchise['furto_incendio'] ?? null),
            'cristalli'      => $normalizeMoney($franchise['cristalli'] ?? null),
        ];

        $finalFranchise = [];
        foreach (['rca','kasko','cristalli','furto_incendio'] as $key) {
            $base = $baseFranchise[$key] ?? null; // euro
            // ✅ precedenza: override richiesta > override DB > base veicolo
            $finalFranchise[$key] = $reqOverrides[$key] ?? $dbOverrides[$key] ?? $base;
        }

        // ---------------------------------------------------------------------
        // 4) CLAUSOLE (INVARIATO PER ORA)
        // ---------------------------------------------------------------------
        $clauses = config('rental.clauses', []);

        // ======================================================
        // RECUPERO FIRME (per stampa nel PDF)
        // - Cliente: override sul Rental -> signature_customer
        // - Noleggiante: prima default Organization -> signature_company
        //              se non presente, fallback su override Rental -> signature_lessor
        // Output: data-uri (DOMPDF friendly)
        // ======================================================

        $customerSignatureMedia = method_exists($rental, 'getFirstMedia')
            ? $rental->getFirstMedia('signature_customer')
            : null;

        $organization = $lessorOrg;

        $lessorSignatureMedia = null;
        if ($organization && method_exists($organization, 'getFirstMedia')) {
            $lessorSignatureMedia = $organization->getFirstMedia('signature_company');
        }
        if (!$lessorSignatureMedia && method_exists($rental, 'getFirstMedia')) {
            $lessorSignatureMedia = $rental->getFirstMedia('signature_lessor');
        }

        $signatureCustomerDataUri = $this->mediaToDataUri($customerSignatureMedia);
        $signatureLessorDataUri   = $this->mediaToDataUri($lessorSignatureMedia);
        $logoAmdDataUri = $this->fileToDataUri(public_path('images/logo-amd.png'));

        /**
         * ✅ Flag esplicito per la view:
         * - Base: NON stampare firme anche se esistono
         * - Firmato: stampare firme
         */
        $renderSignatures = $forceSigned ? true : ($forceUnsigned ? false : (bool) $customerSignatureMedia);
        // Se non devo renderizzare firme, le "spengo" (così il Blade non le vede)
        if ($renderSignatures === false) {
            $signatureCustomerDataUri = null;
            $signatureLessorDataUri   = null;
        }

        // ---------------------------------------------------------------------
        // 5) DTO PER BLADE (ESTESO, NON ROTTO)
        // ---------------------------------------------------------------------
        $vars = [
            'org' => [
                'name'    => $lessorOrg?->name ?? '—',
                'vat'     => $lessorOrg?->vat ?? null,
                'address' => $lessorOrg?->address_line ?? null,
                'zip'     => $lessorOrg?->postal_code ?? null,
                'city'    => $lessorOrg?->city ?? null,
                'phone'   => $lessorOrg?->phone ?? null,
                'email'   => $lessorOrg?->email ?? null,
            ],

            'pricing_totals' => [
                'currency'              => (string) $quoteCurrency,
                'tariff_total_cents'    => (int) $baseTotalCents,
                'second_driver_cents'   => (int) $secondDriverTotalCents,
                'computed_total_cents'  => (int) ($baseTotalCents + $secondDriverTotalCents),
            ],

            // NEW
            'vehicle_owner_name' => $rental->organization->name,
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
                'number_label'    => $rental->display_number_label,
            ],

            'customer' => [
                'name'          => $customer?->name ?? '—',
                'doc_id_type'   => $customer?->doc_id_type ?? null,
                'doc_id_number' => $customer?->doc_id_number ?? null,
                'address'       => $customer?->address_line ?? null,
                'zip'           => $customer?->postal_code ?? null,
                'city'          => $customer?->city ?? null,
                'province'      => $customer?->province ?? null,
                'phone'         => $customer?->phone ?? null,
                'email'         => $customer?->email ?? null,

                'driver_license_number' => data_get($customer, 'driver_license_number')
                    ?? data_get($customer, 'license_number')
                    ?? data_get($customer, 'driving_license_number')
                    ?? null,

                'tax_id' => data_get($customer, 'vat')
                    ?? data_get($customer, 'piva')
                    ?? data_get($customer, 'tax_code')
                    ?? data_get($customer, 'fiscal_code')
                    ?? data_get($customer, 'codice_fiscale')
                    ?? null,
            ],

            'vehicle' => [
                'make'  => $vehicle?->make ?? '—',
                'model' => $vehicle?->model ?? null,
                'plate' => $vehicle?->plate ?? null,
                'color' => $vehicle?->color ?? null,
                'vin'   => $vehicle?->vin ?? null,
            ],

            'logos' => [
                'amd'    => $logoAmdDataUri,
            ],

            'pricing'    => $pricingData,
            'coverages'  => $coverage,
            'franchigie' => $finalFranchise,
            'clauses'    => $clauses,
            'render_signatures' => $renderSignatures,
            'signature_customer' => $signatureCustomerDataUri,
            'signature_lessor'   => $signatureLessorDataUri,
        ];

        // ---------------------------------------------------------------------
        // 6–8) PDF + MEDIA LIBRARY (FIX: salva in "signatures" se c'è firma cliente)
        // ---------------------------------------------------------------------
        $html = $this->view->make('contracts.rental', $vars)->render();

        $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait')->loadHTML($html);
        $binary = $pdf->output();

        /**
         * ✅ Modalità di salvataggio:
         * - forceUnsigned = salva SEMPRE come base (contract)
         * - forceSigned   = salva SEMPRE come firmato (signatures)
         * - default       = firmato solo se esiste firma cliente
         */
        if ($forceSigned) {
            $storeAsSigned = true;
        } elseif ($forceUnsigned) {
            $storeAsSigned = false;
        } else {
            $storeAsSigned = (bool) $customerSignatureMedia;
        }
        $targetCollection = $storeAsSigned ? 'signatures' : 'contract';

        // Versioniamo SOLO dentro la collection di destinazione (non tocchiamo l'altra)
        $rental->getMedia($targetCollection)->each(function ($m) {
            $m->setCustomProperty('current', false);
            $m->save();
        });

        $prefix = $storeAsSigned ? 'contratto-firmato' : 'contratto';

        $media = $rental->addMediaFromString($binary)
            ->usingFileName($prefix.'-'.$rental->id.'-'.now()->format('Ymd_His').'.pdf')
            ->withCustomProperties([
                'current' => true,
                'generated_with_signatures' => $storeAsSigned,
                'pricing_snapshot' => $pricingSnapshot,
            ])
            ->toMediaCollection($targetCollection);

        return $media;
    }

    /**
     * Legge lo snapshot:
     * 1) tabella dedicata (RentalContractSnapshot)
     * 2) fallback legacy: media corrente (signatures > contract)
     */
    private function getStoredPricingSnapshot(Rental $rental): ?array
    {
        // ✅ Fonte primaria: tabella snapshot
        if (method_exists($rental, 'contractSnapshot')) {
            $snapModel = $rental->contractSnapshot()->first();
            if ($snapModel instanceof RentalContractSnapshot) {
                $snap = $snapModel->pricing_snapshot;
                return is_array($snap) ? $snap : null;
            }
        }

        // ✅ Fallback legacy: media
        $m = $this->resolveCurrentContractMedia($rental);
        if (!$m) return null;

        $snap = $m->getCustomProperty('pricing_snapshot');
        return is_array($snap) ? $snap : null;
    }

    /**
     * Salva lo snapshot in tabella SOLO se non esiste (freeze-once).
     * Usiamo transazione + unique(rental_id) per evitare race condition.
     */
    private function persistPricingSnapshotOnce(Rental $rental, array $pricingSnapshot): void
    {
        DB::transaction(function () use ($rental, $pricingSnapshot) {
            // Se esiste già, non faccio nulla.
            $exists = RentalContractSnapshot::query()
                ->where('rental_id', (int) $rental->id)
                ->exists();

            if ($exists) {
                return;
            }

            // Creo lo snapshot (freeze-once).
            RentalContractSnapshot::query()->create([
                'rental_id'           => (int) $rental->id,
                'pricing_snapshot'    => $pricingSnapshot,
                'created_by_user_id'  => auth()->id(),
            ]);
        });
    }

    /**
     * Prova a risalire all'Organization del noleggio.
     * Adatta la lista delle relazioni se nel tuo progetto si chiama diversamente.
     */
    private function resolveOrganizationFromRental(Rental $rental): ?Organization
    {
        // Relazioni più probabili (safe)
        foreach (['organization', 'org'] as $rel) {
            if (method_exists($rental, $rel)) {
                $o = $rental->{$rel};
                if ($o instanceof Organization) {
                    return $o;
                }
            }
        }

        // Fallback su foreign key, se esiste
        if (!empty($rental->organization_id)) {
            return Organization::find($rental->organization_id);
        }

        return null;
    }

    /**
     * Converte un Media (PNG/JPG) in data-uri per includerlo nel PDF senza URL esterni.
     */
    private function mediaToDataUri(?Media $media): ?string
    {
        if (!$media) {
            return null;
        }

        $path = $media->getPath(); // path assoluto
        if (!is_string($path) || !is_file($path)) {
            return null;
        }

        $mime = $media->mime_type
            ?: (function_exists('mime_content_type') ? mime_content_type($path) : null)
            ?: 'image/png';

        $bin = @file_get_contents($path);
        if ($bin === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    /**
     * Converte un file locale (es. public/images/logo.png) in data-uri DOMPDF friendly.
     */
    private function fileToDataUri(string $absolutePath): ?string
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        $mime = (function_exists('mime_content_type') ? mime_content_type($absolutePath) : null) ?: 'image/png';

        $bin = @file_get_contents($absolutePath);
        if ($bin === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    /**
     * Ritorna il "contratto corrente" (firmato > non firmato), se esiste.
     */
    private function resolveCurrentContractMedia(Rental $rental): ?Media
    {
        if (!method_exists($rental, 'getMedia')) {
            return null;
        }

        foreach (['signatures', 'contract'] as $col) {
            $items = $rental->getMedia($col)->sortByDesc('created_at');

            // preferisci quello marcato current=true
            $current = $items->first(fn (Media $m) => (bool) $m->getCustomProperty('current'));
            if ($current) return $current;

            // fallback: ultimo
            if ($items->isNotEmpty()) return $items->first();
        }

        return null;
    }
}
