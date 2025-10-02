<?php

namespace App\Livewire\Vehicles;

use App\Models\Vehicle;
use App\Models\VehicleState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Livewire: Vehicles\Show
 *
 * Pagina dettaglio veicolo con tab: profilo, documenti, stato tecnico, assegnazioni, note.
 * - Permessi granulari: updateMileage, manageMaintenance, restore.
 * - Adattato allo schema/route esistenti, senza rinominare variabili/relazioni.
 */
class Show extends Component
{
    use AuthorizesRequests;

    /** ID veicolo passato dalla Blade di pagina */
    public int $vehicleId;

    /** Tab attiva (sincronizzata su query string per deep-link) */
    #[Url(as: 'tab', except: 'profile')]
    public string $tab = 'profile'; // profile|documents|maintenance|assignments|notes

    /** Filtri locali della tab Documenti */
    #[Url(as: 'doc_state')]
    public ?string $docState = null; // expired|soon|ok|null

    #[Url(as: 'doc_type')]
    public ?string $docType = null;  // insurance|road_tax|inspection|registration|green_card|ztl_permit|other|null

    /** Model corrente (per comodità nel template) */
    public ?Vehicle $vehicle = null;

    /** Etichette localizzate per i tipi documento (enum) */
    protected array $docLabels = [
        'insurance'    => 'RCA',
        'road_tax'     => 'Bollo',
        'inspection'   => 'Revisione',
        'registration' => 'Libretto',
        'green_card'   => 'Carta verde',
        'ztl_permit'   => 'Permesso ZTL',
        'other'        => 'Altro',
    ];

    /** Whitelist dei tipi consentiti (enum) */
    protected array $allowedDocTypes = [
        'insurance', 'road_tax', 'inspection', 'registration', 'green_card', 'ztl_permit', 'other',
    ];

    /**
     * Mount: accetta ID esplicito o un model dal controller (senza cambiare route/controller).
     */
    public function mount(?int $vehicleId = null, ?Vehicle $vehicle = null): void
    {
        if ($vehicleId) {
            $this->vehicleId = $vehicleId;
        } elseif ($vehicle) {
            $this->vehicleId = $vehicle->getKey();
        } else {
            abort(404);
        }

        // Sanitize iniziale del filtro docType, se presente in query
        if ($this->docType && !in_array($this->docType, $this->allowedDocTypes, true)) {
            $this->docType = null;
        }
    }

    /**
     * Garantisce che docType resti allineato ai valori dell'enum.
     * Trigger automatico su cambio di $docType (Livewire).
     */
    public function updatedDocType($value): void
    {
        if ($value === '' || $value === null) {
            $this->docType = null;
            return;
        }
        if (!in_array($value, $this->allowedDocTypes, true)) {
            $this->docType = null;
        }
    }

    /**
     * Carica il veicolo con relazioni e flag calcolati (assegnazione/stato tecnico/scadenza minima).
     * Include soft-deleted per mostrare stato "archiviato" ed eventuale ripristino.
     */
    protected function loadVehicle(): Vehicle
    {
        $now = Carbon::now();

        /** @var Vehicle $v */
        $v = Vehicle::withTrashed()
            ->with([
                'adminOrganization:id,name',
                'defaultPickupLocation:id,name',
                'documents'    => fn ($q) => $q->orderBy('expiry_date'),
                'states'       => fn ($q) => $q->orderByDesc('started_at'),
                'assignments'  => fn ($q) => $q->orderByDesc('start_at'),
            ])
            ->withExists(['assignments as is_assigned' => function ($sub) use ($now) {
                $sub->where('status', 'active')
                    ->where('start_at', '<=', $now)
                    ->where(fn ($w) => $w->whereNull('end_at')->orWhere('end_at', '>', $now));
            }])
            ->withExists(['states as is_maintenance' => function ($sub) {
                $sub->whereIn('state', ['maintenance', 'out_of_service'])
                    ->whereNull('ended_at');
            }])
            ->withMin(['documents as next_expiry_date' => function ($d) use ($now) {
                $d->whereNotNull('expiry_date')->where('expiry_date', '>=', $now->toDateString());
            }], 'expiry_date')
            ->findOrFail($this->vehicleId);

        // Gate di visibilità coerente con policy 'view'
        $this->authorize('view', $v);

        return $v;
    }

    /**
     * Aggiorna chilometraggio assoluto (no regressione).
     * Permesso granulare: policy 'updateMileage'.
     */
    public function updateMileage(int $mileage): void
    {
        $v = Vehicle::withTrashed()->findOrFail($this->vehicleId);
        $this->authorize('updateMileage', $v);

        if ($v->trashed()) {
            $this->addError('mileage', 'Veicolo archiviato: ripristina prima di aggiornare i km.');
            return;
        }

        if ($mileage < (int) $v->mileage_current) {
            $this->addError('mileage', 'Il chilometraggio non può diminuire.');
            return;
        }

        $v->mileage_current = $mileage;
        $v->save();

        $this->dispatch('toast', type: 'success', message: 'Chilometraggio aggiornato.');
    }

    /**
     * Apre manutenzione se non già aperta.
     * Permesso granulare: policy 'manageMaintenance'.
     */
    public function setMaintenance(): void
    {
        $v = Vehicle::withTrashed()->findOrFail($this->vehicleId);
        $this->authorize('manageMaintenance', $v);

        if ($v->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Veicolo archiviato: ripristina prima di modificare lo stato.');
            return;
        }

        $exists = VehicleState::where('vehicle_id', $v->id)
            ->whereIn('state', ['maintenance', 'out_of_service'])
            ->whereNull('ended_at')
            ->exists();

        if (!$exists) {
            VehicleState::create([
                'vehicle_id' => $v->id,
                'state'      => 'maintenance',
                'started_at' => Carbon::now(),
                'ended_at'   => null,
                'reason'     => 'Impostato dalla pagina veicolo',
                'created_by' => Auth::id(),
            ]);
        }

        $this->dispatch('toast', type: 'success', message: 'Manutenzione aperta.');
    }

    /**
     * Chiude qualunque stato tecnico aperto.
     * Permesso granulare: policy 'manageMaintenance'.
     */
    public function clearMaintenance(): void
    {
        $v = Vehicle::withTrashed()->findOrFail($this->vehicleId);
        $this->authorize('manageMaintenance', $v);

        if ($v->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Veicolo archiviato: ripristina prima di modificare lo stato.');
            return;
        }

        VehicleState::where('vehicle_id', $v->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => Carbon::now()]);

        $this->dispatch('toast', type: 'success', message: 'Manutenzione chiusa.');
    }

    /** Soft delete (archivia) */
    public function archive(): void
    {
        $v = Vehicle::findOrFail($this->vehicleId);
        $this->authorize('delete', $v);

        $v->delete();
        $this->dispatch('toast', type: 'success', message: 'Veicolo archiviato.');
    }

    /** Ripristina soft delete */
    public function restore(): void
    {
        $v = Vehicle::withTrashed()->findOrFail($this->vehicleId);
        $this->authorize('restore', $v);

        if ($v->trashed()) {
            $v->restore();
            $this->dispatch('toast', type: 'success', message: 'Veicolo ripristinato.');
        }
    }

    /** Cambio tab da UI (whitelist) */
    public function switchTab(string $tab): void
    {
        $allowed = ['profile','documents','maintenance','assignments','notes'];
        if (in_array($tab, $allowed, true)) {
            $this->tab = $tab;
        }
    }

    /** Render della pagina con dati calcolati per header e tab */
    public function render()
    {
        // Carica veicolo + autorizzazione 'view'
        $v = $this->loadVehicle();
        $this->vehicle = $v;

        // Flag per badge header
        $isArchived    = method_exists($v, 'trashed') && $v->trashed();
        $isAssigned    = (bool) $v->is_assigned;
        $isMaintenance = (bool) $v->is_maintenance;

        // Prossima scadenza in giorni interi
        $nextDays = null;
        if ($v->next_expiry_date) {
            $next = Carbon::parse($v->next_expiry_date)->startOfDay();
            $nextDays = now()->startOfDay()->diffInDays($next, false);
        }

        // Documenti: contatori per badge tab
        $today     = now()->startOfDay();
        $soonLimit = now()->startOfDay()->addDays(60);

        $docs = $v->documents;
        $docExpired = $docs->whereNotNull('expiry_date')
            ->filter(fn ($d) => Carbon::parse($d->expiry_date)->lt($today))
            ->count();

        $docSoon = $docs->whereNotNull('expiry_date')
            ->filter(fn ($d) => ($dt = Carbon::parse($d->expiry_date)->startOfDay()) >= $today && $dt <= $soonLimit)
            ->count();

        // Filtri locali documenti (docType/docState)
        $docsFiltered = $docs
            ->when($this->docType, fn ($c) => $c->where('type', $this->docType))
            ->when($this->docState === 'expired', function ($c) use ($today) {
                return $c->whereNotNull('expiry_date')
                         ->filter(fn ($d) => Carbon::parse($d->expiry_date)->lt($today));
            })
            ->when($this->docState === 'soon', function ($c) use ($today, $soonLimit) {
                return $c->whereNotNull('expiry_date')->filter(function ($d) use ($today, $soonLimit) {
                    $dt = Carbon::parse($d->expiry_date)->startOfDay();
                    return $dt >= $today && $dt <= $soonLimit;
                });
            })
            ->when($this->docState === 'ok', function ($c) use ($today, $soonLimit) {
                return $c->whereNotNull('expiry_date')->reject(function ($d) use ($today, $soonLimit) {
                    $dt = Carbon::parse($d->expiry_date)->startOfDay();
                    return $dt < $today || ($dt >= $today && $dt <= $soonLimit);
                });
            });

        // Assegnazione attiva (readonly)
        $assignedNow = DB::table('vehicle_assignments as va')
            ->leftJoin('organizations as o', 'o.id', '=', 'va.renter_org_id')
            ->select('va.id', 'o.name as renter_name', 'va.start_at', 'va.end_at', 'va.status')
            ->where('va.vehicle_id', $v->id)
            ->where('va.status', 'active')
            ->where('va.start_at', '<=', now())
            ->where(fn ($w) => $w->whereNull('va.end_at')->orWhere('va.end_at', '>', now()))
            ->first();

        return view('livewire.vehicles.show', [
            'v'              => $v,
            'isArchived'     => $isArchived,
            'isAssigned'     => $isAssigned,
            'isMaintenance'  => $isMaintenance,
            'nextDays'       => $nextDays,
            'docExpired'     => $docExpired,
            'docSoon'        => $docSoon,
            'docsFiltered'   => $docsFiltered,
            'assignedNow'    => $assignedNow,
            'docLabels'      => $this->docLabels, // label italiane per la view
        ]);
    }
}
