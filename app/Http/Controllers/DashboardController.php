<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
