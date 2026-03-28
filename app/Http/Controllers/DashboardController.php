<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\{
    Vehicle,
    VehicleState,
    VehicleAssignment,
    VehicleBlock,
    VehicleDocument,
    Rental,
    Customer,
    Organization
};

/**
 * Controller: Dashboard – Gestionale SubNoleggio
 *
 * Calcola i KPI mostrati nelle tiles della dashboard.
 * - Nessuna modifica ai nomi/variabili delle view esistenti.
 * - Query robuste, commentate e incapsulate in metodi privati.
 *
 * Visibilità:
 * - ADMIN => conteggi globali
 * - RENTER => conteggi filtrati sulla propria organization
 */
class DashboardController extends Controller
{
    /**
     * Punto di ingresso della dashboard.
     * Raccoglie i KPI e li passa alla view 'dashboard' come $badges.
     */
    public function __invoke(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $org  = $user?->organization;       // relazione User → Organization
        $isAdmin  = (bool) ($org?->isAdmin());
        $orgId    = $org?->id;

        // KPI con scoping in base al ruolo
        $vehiclesAvailable = $isAdmin
            ? $this->countVehiclesAvailableGlobal()
            : ($orgId ? $this->countVehiclesAvailableForRenter($orgId) : 0);

        $contractsOpen = $isAdmin
            ? $this->countContractsOpenGlobal()
            : ($orgId ? $this->countContractsOpenForRenter($orgId) : 0);

        $assignmentsToday = $isAdmin
            ? $this->countAssignmentsTodayGlobal()
            : ($orgId ? $this->countAssignmentsTodayForRenter($orgId) : 0);

        $blocksActive = $isAdmin
            ? $this->countBlocksActiveGlobal()
            : ($orgId ? $this->countBlocksActiveForRenter($orgId) : 0);

        $vehicleDocsDue = $isAdmin
            ? $this->countVehicleDocsDueGlobal()
            : ($orgId ? $this->countVehicleDocsDueForRenter($orgId) : 0);

        $customersTotal = $isAdmin
            ? $this->countCustomersTotalGlobal()
            : ($orgId ? $this->countCustomersTotalForRenter($orgId) : 0);

        // Mapping CHIAVE -> VALORE coerente con config('menu.dashboard_tiles.*.badge_count')
        $badges = [
            'vehicles_available'  => $vehiclesAvailable,
            'contracts_open'      => $contractsOpen,
            'assignments_today'   => $assignmentsToday,
            'blocks_active'       => $blocksActive,
            'vehicle_docs_due'    => $vehicleDocsDue,
            'customers_total'     => $customersTotal,
        ];

        return view('dashboard', compact('badges'));
    }

    /**
     * Stampa un contratto vuoto di emergenza.
     *
     * Obiettivo:
     * - usare la stessa Blade del contratto reale;
     * - precompilare solo i dati del noleggiante secondo la logica della licenza;
     * - lasciare il resto il più possibile vuoto per la compilazione manuale.
     */
    public function printBlankContract(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        /** @var \App\Models\Organization|null $userOrganization */
        $userOrganization = $user?->organization;

        /**
         * Risolve l'organizzazione "noleggiante" da usare nel PDF vuoto.
         *
         * Regola applicata:
         * - se l'organizzazione corrente è admin, usiamo quella;
         * - se è renter con licenza valida e non scaduta, usiamo quella;
         * - altrimenti fallback su una organization admin.
         *
         * Nota:
         * questa è la miglior approssimazione possibile qui, senza avere
         * il contesto di uno specifico noleggio/veicolo.
         */
        $lessorOrganization = $this->resolveBlankContractLessorOrganization($userOrganization);

        /**
         * Stabilisce se l'organizzazione dell'utente ha una licenza valida.
         *
         * Se non la possiede, nel PDF mostreremo:
         * - a sinistra il noleggiante effettivo
         * - a destra l'AMD Point (organizzazione dell'utente)
         */
        $hasValidLicense = $this->organizationHasValidRentalLicense($userOrganization);

        /**
         * Dati AMD Point:
         * li valorizziamo solo quando l'organizzazione dell'utente
         * non è il noleggiante effettivo.
         */
        $pointOrg = (!$hasValidLicense && $userOrganization)
            ? [
                'name'    => $userOrganization->name ?? '',
                'vat'     => $userOrganization->vat ?? '',
                'address' => $userOrganization->address_line ?? '',
                'zip'     => $userOrganization->postal_code ?? '',
                'city'    => $userOrganization->city ?? '',
                'phone'   => $userOrganization->phone ?? '',
                'email'   => $userOrganization->email ?? '',
            ]
            : null;

        /*
         * Dati noleggiante.
         * Manteniamo le stesse chiavi già attese dalla Blade esistente.
         */
        $org = [
            'name'    => $lessorOrganization?->name ?? '',
            'vat'     => $lessorOrganization?->vat ?? '',
            'address' => $lessorOrganization?->address_line ?? '',
            'zip'     => $lessorOrganization?->postal_code ?? '',
            'city'    => $lessorOrganization?->city ?? '',
            'phone'   => $lessorOrganization?->phone ?? '',
            'email'   => $lessorOrganization?->email ?? '',
        ];

        /*
         * Dati contratto placeholder.
         * Numero contratto volutamente vuoto come richiesto.
         */
        $rental = [
            'number_label'    => '',
            'issued_at'       => now()->format('d/m/Y'),
            'pickup_at'       => '',
            'pickup_location' => '',
            'return_at'       => '',
            'return_location' => '',
        ];

        /*
         * Dati cliente lasciati vuoti per compilazione manuale.
         */
        $customer = [
            'name'                  => '',
            'tax_id'                => '',
            'driver_license_number' => '',
            'doc_id_type'           => '',
            'doc_id_number'         => '',
            'address'               => '',
            'zip'                   => '',
            'city'                  => '',
            'province'              => '',
            'phone'                 => '',
            'email'                 => '',
        ];

        /*
         * Dati veicolo lasciati vuoti per compilazione manuale.
         */
        $vehicle = [
            'make'   => '',
            'model'  => '',
            'plate'  => '',
            'color'  => '',
            'vin'    => '',
        ];

        /*
         * Pricing placeholder.
         *
         * Nota:
         * la Blade oggi forza la stampa numerica della tariffa,
         * quindi lato controller possiamo solo passare valori neutri.
         * Per avere davvero il campo vuoto servirà un piccolo ritocco in Blade.
         */
        $pricing = [
            'days'              => '',
            'km_daily_limit'    => null,
            'included_km_total' => '',
            'extra_km_rate'     => '',
            'deposit'           => '',
        ];

        /*
         * Totali coerenti con la struttura usata dalla Blade.
         * Anche qui, tariffa a zero solo per compatibilità del template attuale.
         */
        $pricing_totals = [
            'currency'               => 'EUR',
            'tariff_total_cents'     => 0,
            'tariff_effective_cents' => 0,
            'second_driver_cents'    => 0,
            'computed_total_cents'   => 0,
        ];

        /*
         * Coperture e franchigie volutamente vuote.
         *
         * Nota:
         * la Blade oggi trasforma false in "Non inclusa", quindi per averle davvero
         * vuote servirà una piccola modifica nel template.
         */
        $coverages = [
            'kasko'          => null,
            'furto_incendio' => null,
            'cristalli'      => null,
            'assistenza'     => null,
        ];

        $franchigie = [
            'rca'             => null,
            'kasko'           => null,
            'furto_incendio'  => null,
            'cristalli'       => null,
        ];

        /*
         * Manteniamo la compatibilità col fallback della Blade.
         */
        $clauses = [];

        /*
         * Nessuna seconda guida nel modulo vuoto.
         */
        $second_driver = null;

        /*
         * Nessuna firma grafica renderizzata nel modulo di emergenza.
         */
        $render_signatures = false;
        $signature_customer = null;
        $signature_lessor = null;

        /*
         * Loghi contratto.
         *
         * Replichiamo la stessa logica del servizio GenerateRentalContract:
         * - conversione file locale -> data URI
         * - path statici in public/images
         */
        $logos = [
            'amd' => $this->fileToDataUri(public_path('images/logo-amd.png')),
            'era' => $this->fileToDataUri(public_path('images/erarent.png')),
        ];

        $viewData = [
            'org'                => $org,
            'rental'             => $rental,
            'customer'           => $customer,
            'vehicle'            => $vehicle,
            'pricing'            => $pricing,
            'pricing_totals'     => $pricing_totals,
            'coverages'          => $coverages,
            'franchigie'         => $franchigie,
            'clauses'            => $clauses,
            'vehicle_owner_name' => $lessorOrganization?->name ?? '',
            'second_driver'      => $second_driver,
            'final_amount'       => null,
            'render_signatures'  => $render_signatures,
            'signature_customer' => $signature_customer,
            'signature_lessor'   => $signature_lessor,
            'logos'              => $logos,
            'show_dual_lessor_box' => !$hasValidLicense && !empty($pointOrg),
            'point_org'            => $pointOrg,
        ];

        $pdf = Pdf::loadView('contracts.rental', $viewData)
            ->setPaper('a4');

        $fileName = sprintf('contratto-vuoto-%s.pdf', now()->format('Ymd_His'));

        return $pdf->stream($fileName);
    }

    /**
     * Stampa una checklist vuota di emergenza.
     *
     * Obiettivo:
     * - usare la Blade checklist esistente;
     * - non indicare pickup/return nel titolo;
     * - lasciare i campi dati base vuoti invece di mostrare 0 o trattini.
     */
    public function printBlankChecklist(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        /** @var \App\Models\Organization|null $organization */
        $organization = $user?->organization;

        /*
         * Customer placeholder.
         */
        $customer = (object) [
            'name'          => '',
            'surname'       => '',
            'business_name' => '',
            'tax_code'      => '',
            'vat_number'    => '',
        ];

        /*
         * Vehicle placeholder.
         */
        $vehicle = (object) [
            'brand' => '',
            'model' => '',
            'plate' => '',
            'id'    => null,
        ];

        /*
         * Rental placeholder.
         */
        $rental = (object) [
            'display_number_label' => '',
            'vehicle'              => $vehicle,
            'customer'             => $customer,
            'organization'         => (object) [
                'name' => $organization?->name ?? '',
            ],
        ];

        /*
         * Checklist placeholder.
         *
         * Nota:
         * type lo lasciamo vuoto così il titolo non mostra PICKUP/RETURN.
         */
        $checklist = new class($rental) {
            /**
             * Tipo checklist lasciato vuoto per stampa manuale.
             *
             * @var string
             */
            public $type = '';

            /**
             * Identificativo placeholder.
             *
             * @var string
             */
            public $id = '';

            /**
             * Nessuna checklist sostituita.
             *
             * @var int|null
             */
            public $replaces_checklist_id = null;

            /**
             * Rental placeholder associato.
             *
             * @var object
             */
            public $rental;

            /**
             * Costruttore.
             *
             * @param object $rental
             */
            public function __construct(object $rental)
            {
                $this->rental = $rental;
            }

            /**
             * Il modulo vuoto non è mai bloccato.
             */
            public function isLocked(): bool
            {
                return false;
            }
        };

        /*
         * Payload placeholder.
         *
         * Per i dati base usiamo stringhe vuote così la Blade possa stampare campi vuoti
         * quando andremo ad adeguarla nel punto successivo.
         */
        $payload = [
            'base' => [
                'mileage'      => '',
                'fuel_percent' => '',
                'cleanliness'  => '',
            ],
            'json' => [
                'documents' => [
                    'id_card'        => false,
                    'driver_license' => false,
                    'contract_copy'  => false,
                ],
                'equipment' => [
                    'spare_wheel' => false,
                    'jack'        => false,
                    'triangle'    => false,
                    'vest'        => false,
                ],
                'vehicle' => [
                    'lights_ok'     => false,
                    'horn_ok'       => false,
                    'brakes_ok'     => false,
                    'tires_ok'      => false,
                    'windshield_ok' => false,
                ],
                'notes' => '',
            ],
            'damages' => [],
        ];

        $viewData = [
            'checklist'    => $checklist,
            'payload'      => $payload,
            'generated_at' => now(),
            'signatures'   => [],
        ];

        $pdf = Pdf::loadView('pdfs.checklist', $viewData)
            ->setPaper('a4');

        $fileName = sprintf('checklist-vuota-%s.pdf', now()->format('Ymd_His'));

        return $pdf->stream($fileName);
    }

    /**
     * Risolve l'organizzazione noleggiante da usare nel contratto vuoto.
     *
     * Regole:
     * - organization admin => usa quella;
     * - organization renter con licenza valida => usa quella;
     * - organization renter senza licenza valida/scaduta => fallback a una admin organization.
     *
     * @param \App\Models\Organization|null $organization
     * @return \App\Models\Organization|null
     */
    private function resolveBlankContractLessorOrganization(?Organization $organization): ?Organization
    {
        if (!$organization) {
            return Organization::query()
                ->where('type', 'admin')
                ->orderBy('id')
                ->first();
        }

        if ($organization->isAdmin()) {
            return $organization;
        }

        $hasValidLicense = (bool) $organization->rental_license
            && (
                is_null($organization->rental_license_expires_at)
                || $organization->rental_license_expires_at->isToday()
                || $organization->rental_license_expires_at->isFuture()
            );

        if ($hasValidLicense) {
            return $organization;
        }

        return Organization::query()
            ->where('type', 'admin')
            ->orderBy('id')
            ->first();
    }

    /**
     * Verifica se l'organizzazione possiede una licenza noleggio valida.
     *
     * @param \App\Models\Organization|null $organization
     * @return bool
     */
    private function organizationHasValidRentalLicense(?Organization $organization): bool
    {
        if (!$organization) {
            return false;
        }

        return (bool) $organization->rental_license
            && (
                is_null($organization->rental_license_expires_at)
                || $organization->rental_license_expires_at->isToday()
                || $organization->rental_license_expires_at->isFuture()
            );
    }

    /**
     * Converte un file locale in data URI compatibile con DomPDF.
     *
     * Questo approccio evita problemi di risoluzione URL/path nel rendering PDF.
     *
     * @param string $absolutePath
     * @return string|null
     */
    private function fileToDataUri(string $absolutePath): ?string
    {
        /*
         * Se il file non esiste, non mostriamo il logo.
         */
        if (!is_file($absolutePath)) {
            return null;
        }

        /*
         * Proviamo a rilevare il MIME reale del file.
         * Fallback: image/png.
         */
        $mime = (function_exists('mime_content_type') ? mime_content_type($absolutePath) : null) ?: 'image/png';

        /*
         * Lettura binaria del file.
         */
        $bin = @file_get_contents($absolutePath);

        if ($bin === false) {
            return null;
        }

        /*
         * Restituisce una data URI DOMPDF-friendly.
         */
        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    /* =========================================================
     |  KPI: Veicoli disponibili
     |=========================================================*/

    /**
     * Global: veicoli con stato corrente = 'available'.
     * Criteri:
     *  - (facoltativo) vehicles.is_active = true, se la colonna esiste
     *  - esiste vehicle_states (ended_at NULL, state='available') per il veicolo
     */
    private function countVehiclesAvailableGlobal(): int
    {
        try {
            return Vehicle::query()
                ->when(Schema::hasColumn('vehicles', 'is_active'), fn($q) => $q->where('is_active', true))
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('vehicle_states as vs')
                      ->whereColumn('vs.vehicle_id', 'vehicles.id')
                      ->whereNull('vs.ended_at')
                      ->where('vs.state', 'available');
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Renter: veicoli assegnati al renter e attualmente 'available'.
     * Criteri:
     *  - vehicle_assignments attivo (end_at NULL) con renter_org_id = $orgId
     *  - stato corrente 'available' in vehicle_states
     */
    private function countVehiclesAvailableForRenter(int $orgId): int
    {
        try {
            return Vehicle::query()
                ->whereExists(function ($q) use ($orgId) {
                    $q->select(DB::raw(1))
                      ->from('vehicle_assignments as va')
                      ->whereColumn('va.vehicle_id', 'vehicles.id')
                      ->whereNull('va.end_at')
                      ->where('va.renter_org_id', $orgId);
                })
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('vehicle_states as vs')
                      ->whereColumn('vs.vehicle_id', 'vehicles.id')
                      ->whereNull('vs.ended_at')
                      ->where('vs.state', 'available');
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /* =========================================================
     |  KPI: Contratti aperti (rentals)
     |=========================================================*/

    /**
     * Global: contratti aperti.
     * Criterio robusto:
     *  - (actual_return_at IS NULL) OR (status IN ['open','active'])
     */
    private function countContractsOpenGlobal(): int
    {
        try {
            return Rental::query()
                ->where(function ($q) {
                    $q->whereNull('actual_return_at')
                      ->orWhereIn('status', ['open','active']);
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Renter: contratti aperti dell'organizzazione.
     */
    private function countContractsOpenForRenter(int $orgId): int
    {
        try {
            return Rental::query()
                ->where('organization_id', $orgId)
                ->where(function ($q) {
                    $q->whereNull('actual_return_at')
                      ->orWhereIn('status', ['open','active']);
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /* =========================================================
     |  KPI: Assegnazioni di oggi
     |=========================================================*/

    /**
     * Global: assignment con start_at = oggi.
     * (Opzionale) esclude status 'cancelled' se la colonna esiste.
     */
    private function countAssignmentsTodayGlobal(): int
    {
        try {
            return VehicleAssignment::query()
                ->whereDate('start_at', now()->toDateString())
                ->when(Schema::hasColumn('vehicle_assignments', 'status'), fn($q) =>
                    $q->whereNotIn('status', ['cancelled'])
                )
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Renter: assignment con start_at = oggi per renter specifico.
     */
    private function countAssignmentsTodayForRenter(int $orgId): int
    {
        try {
            return VehicleAssignment::query()
                ->where('renter_org_id', $orgId)
                ->whereDate('start_at', now()->toDateString())
                ->when(Schema::hasColumn('vehicle_assignments', 'status'), fn($q) =>
                    $q->whereNotIn('status', ['cancelled'])
                )
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /* =========================================================
     |  KPI: Blocchi attivi
     |=========================================================*/

    /**
     * Global: blocchi attivi.
     * Criteri:
     *  - start_at <= NOW
     *  - (end_at IS NULL OR end_at >= NOW)
     *  - (opz.) status = 'active' se esiste la colonna
     */
    private function countBlocksActiveGlobal(): int
    {
        try {
            return VehicleBlock::query()
                ->where('start_at', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('end_at')
                      ->orWhere('end_at', '>=', now());
                })
                ->when(Schema::hasColumn('vehicle_blocks', 'status'), fn($q) =>
                    $q->where('status', 'active')
                )
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Renter: blocchi attivi creati dalla propria organization.
     */
    private function countBlocksActiveForRenter(int $orgId): int
    {
        try {
            return VehicleBlock::query()
                ->where('organization_id', $orgId)
                ->where('start_at', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('end_at')
                      ->orWhere('end_at', '>=', now());
                })
                ->when(Schema::hasColumn('vehicle_blocks', 'status'), fn($q) =>
                    $q->where('status', 'active')
                )
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /* =========================================================
     |  KPI: Documenti veicolo in scadenza
     |=========================================================*/

    /**
     * Global: documenti con expiry_date entro N giorni (default 30).
     */
    private function countVehicleDocsDueGlobal(int $days = 30): int
    {
        try {
            $limit = now()->addDays($days)->toDateString();

            return VehicleDocument::query()
                ->whereDate('expiry_date', '<=', $limit)
                ->whereDate('expiry_date', '>=', now()->toDateString())
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Renter: documenti in scadenza di veicoli attualmente assegnati al renter.
     * Criteri:
     *  - vehicle_documents.vehicle_id IN (
     *      SELECT vehicle_id FROM vehicle_assignments
     *      WHERE renter_org_id = :orgId AND end_at IS NULL
     *    )
     *  - expiry_date entro N giorni
     */
    private function countVehicleDocsDueForRenter(int $orgId, int $days = 30): int
    {
        try {
            $limit = now()->addDays($days)->toDateString();

            return VehicleDocument::query()
                ->whereExists(function ($q) use ($orgId) {
                    $q->select(DB::raw(1))
                      ->from('vehicle_assignments as va')
                      ->whereColumn('va.vehicle_id', 'vehicle_documents.vehicle_id')
                      ->where('va.renter_org_id', $orgId)
                      ->whereNull('va.end_at');
                })
                ->whereDate('expiry_date', '<=', $limit)
                ->whereDate('expiry_date', '>=', now()->toDateString())
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /* =========================================================
     |  KPI: Clienti totali
     |=========================================================*/

    private function countCustomersTotalGlobal(): int
    {
        try {
            return Customer::query()->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countCustomersTotalForRenter(int $orgId): int
    {
        try {
            return Customer::query()->where('organization_id', $orgId)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
