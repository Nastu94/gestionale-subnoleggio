<?php

namespace App\Livewire\Assignments;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\{
    Vehicle,
    VehicleAssignment,
    VehicleBlock,
    Organization
};

/**
 * Livewire: Admin ▸ Assegna veicoli ai renter.
 *
 * - Non rinomina alcun campo o relazione esistente.
 * - Rispetta Policy/Permessi: usa authorize('create', VehicleAssignment::class).
 * - Gestisce controlli di overlap su assegnazioni e blocchi (maintenance, ecc.).
 * - Esegue creazione in transazione con lock ottimistico (FOR UPDATE).
 */
class VehicleAssigner extends Component
{
    use WithPagination;

    /** @var int|null ID organizzazione (renter) selezionata */
    public ?int $renterOrgId = null;

    /** @var string Data/ora inizio assegnazione (ISO 8601 'Y-m-d\TH:i') */
    public string $dateFrom;

    /** @var string|null Data/ora fine assegnazione (nullable = aperta) */
    public ?string $dateTo = null;

    /** @var string Ricerca full-text semplificata (targa, marca, modello, ecc.) */
    public string $q = '';

    /** @var array Filtri opzionali lato UI */
    public array $filters = [
        'fuel_type'      => null,
        'transmission'   => null,
        'segment'        => null,
        'seats'          => null,
        'only_available' => true, // mostra di default solo veicoli liberi nel range
    ];

    /** @var array<int> Selezione multipla veicoli da assegnare */
    public array $selectedVehicleIds = [];

    /** @var string|null Messaggi/alert della conferma */
    public ?string $confirmMessage = null;

    /** @var string Tab corrente della tabella assegnazioni (active|scheduled|history) */
    public string $tab = 'active';

    /** Inizializza range date con “oggi → +30gg” per default */
    public function mount(): void
    {
        $now = Carbon::now();
        $this->dateFrom = $now->format('Y-m-d\TH:i');
        $this->dateTo   = $now->copy()->addDays(30)->format('Y-m-d\TH:i');
    }

    /** Regole di validazione (Laravel 12) */
    protected function rules(): array
    {
        return [
            'renterOrgId' => ['required', Rule::exists('organizations', 'id')],
            'dateFrom'    => ['required', 'date'],
            'dateTo'      => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'selectedVehicleIds' => ['array'],
            'selectedVehicleIds.*' => [Rule::exists('vehicles', 'id')],
        ];
    }

    /** Normalizza la ricerca come da tua logica * → %, spazi → % */
    protected function normalizeLike(string $term): string
    {
        $term = strtolower(trim($term));
        $term = str_replace('*', '%', $term);
        $term = preg_replace('/\s+/', '%', $term);
        return "%{$term}%";
    }

    /** Query paginata dei veicoli filtrati (paginazione isolata: 'vehiclesPage') */
    public function getVehiclesProperty()
    {
        $like = $this->normalizeLike($this->q);

        $q = Vehicle::query()
            ->with(['adminOrganization'])
            ->when($this->filters['fuel_type'],      fn($qq, $v) => $qq->where('fuel_type', $v))
            ->when($this->filters['transmission'],   fn($qq, $v) => $qq->where('transmission', $v))
            ->when($this->filters['segment'],        fn($qq, $v) => $qq->where('segment', $v))
            ->when($this->filters['seats'],          fn($qq, $v) => $qq->where('seats', $v))
            ->where('is_active', true)
            ->when(strlen(trim($this->q)) > 0, function ($qq) use ($like) {
                $qq->where(function ($w) use ($like) {
                    $w->whereRaw('LOWER(plate) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(make) LIKE ?',  [$like])
                      ->orWhereRaw('LOWER(model) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(color) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(segment) LIKE ?', [$like]);
                });
            });

        if ($this->filters['only_available']) {
            $availIds = $this->availableVehicleIdsInRange();
            $q->whereIn('id', $availIds);
        }

        // NB: terzo parametro = nome della pagina di paginazione
        return $q->orderBy('make')->orderBy('model')->paginate(12, ['*'], 'vehiclesPage');
    }


    /** Elenco organizzazioni di tipo renter (per select) */
    public function getRenterOptionsProperty()
    {
        return Organization::query()
            ->where('type', 'renter')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id','name']);
    }

    /**
     * Verifica se un veicolo è disponibile nel range selezionato.
     * - Conflitto se esiste assegnazione active|scheduled con overlap
     * - Conflitto se esiste VehicleBlock active|scheduled con overlap
     */
    public function isVehicleAvailable(int $vehicleId): bool
    {
        [$from, $to] = $this->rangeAsCarbon();
        return !$this->hasAssignmentOverlap($vehicleId, $from, $to)
            && !$this->hasBlockOverlap($vehicleId, $from, $to);
    }

    /** Calcola gli ID veicoli disponibili (ottimizzazione per filtro) */
    protected function availableVehicleIdsInRange(): array
    {
        [$from, $to] = $this->rangeAsCarbon();

        // Prendi tutti gli ID attivi
        $ids = Vehicle::query()->where('is_active', true)->pluck('id');

        // Sottrai quelli con overlap in assignments o in blocks
        $busyFromAssignments = VehicleAssignment::query()
            ->whereIn('status', ['scheduled','active'])
            ->whereIn('vehicle_id', $ids)
            ->where(fn($q) => $this->overlapWhere($q, $from, $to))
            ->pluck('vehicle_id');

        $busyFromBlocks = VehicleBlock::query()
            ->whereIn('status', ['scheduled','active'])
            ->whereIn('vehicle_id', $ids)
            ->where(fn($q) => $this->overlapWhere($q, $from, $to))
            ->pluck('vehicle_id');

        return $ids->diff($busyFromAssignments->merge($busyFromBlocks))->values()->all();
    }

    /** Helper: converte le proprietà date in Carbon */
    protected function rangeAsCarbon(): array
    {
        $from = Carbon::parse($this->dateFrom);
        $to   = $this->dateTo ? Carbon::parse($this->dateTo) : null; // null = aperto
        return [$from, $to];
    }

    /**
     * Applica condizione di overlap a una query:
     * due intervalli si sovrappongono se:
     *   start1 <= end2  AND  start2 <= end1
     * dove end null = +∞
     */
    protected function overlapWhere($q, Carbon $from, ?Carbon $to): void
    {
        // end di filtro; se null, usa una “fine” molto in avanti per semplificare il confronto
        $endFilter = $to?->copy() ?? Carbon::create(9999,12,31,23,59,59);

        $q->where('start_at', '<=', $endFilter)
          ->where(function ($qq) use ($from) {
              // (end_at IS NULL) OR (end_at >= from)
              $qq->whereNull('end_at')
                 ->orWhere('end_at', '>=', $from);
          });
    }

    /** TRUE se esiste overlap con assegnazioni active/scheduled del veicolo */
    protected function hasAssignmentOverlap(int $vehicleId, Carbon $from, ?Carbon $to): bool
    {
        return VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', ['scheduled','active'])
            ->where(fn($q) => $this->overlapWhere($q, $from, $to))
            ->exists();
    }

    /** TRUE se esiste overlap con blocchi (maintenance, ecc.) active/scheduled */
    protected function hasBlockOverlap(int $vehicleId, Carbon $from, ?Carbon $to): bool
    {
        return VehicleBlock::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', ['scheduled','active'])
            ->where(fn($q) => $this->overlapWhere($q, $from, $to))
            ->exists();
    }

        /**
     * Restituisce gli ID dei veicoli della pagina corrente.
     * Usiamo il Paginator → collection corrente per evitare N+1.
     */
    protected function currentPageVehicleIds(): array
    {
        $paginator = $this->vehicles; // computed property
        return $paginator->getCollection()->pluck('id')->all();
    }

    /**
     * Seleziona o deseleziona TUTTI i veicoli della pagina corrente.
     * - Se tutti sono già selezionati → li rimuove dalla selezione
     * - Altrimenti → li aggiunge alla selezione (senza duplicati)
     */
    public function toggleSelectAll(): void
    {
        $pageIds = $this->currentPageVehicleIds();

        $allSelected = collect($pageIds)->every(
            fn($id) => in_array($id, $this->selectedVehicleIds, true)
        );

        if ($allSelected) {
            // Rimuove quelli della pagina dalla selezione corrente
            $this->selectedVehicleIds = array_values(array_diff($this->selectedVehicleIds, $pageIds));
        } else {
            // Unisce senza duplicati
            $this->selectedVehicleIds = array_values(array_unique(array_merge($this->selectedVehicleIds, $pageIds)));
        }
    }

    /**
     * Elenco assegnazioni dell'organizzazione selezionata, filtrate per tab.
     * Paginazione separata: 'assignmentsPage' per non interferire con i veicoli.
     */
    public function getAssignmentsProperty()
    {
        if (!$this->renterOrgId) {
            // Ritorna un paginator vuoto quando non è selezionato alcun renter
            return VehicleAssignment::query()
                ->whereRaw('1=0')
                ->paginate(10, ['*'], 'assignmentsPage');
        }

        $q = VehicleAssignment::query()
            ->with(['vehicle']) // mostriamo make, model, plate
            ->where('renter_org_id', $this->renterOrgId);

        // Filtra per tab: active | scheduled | history (ended/revoked)
        if ($this->tab === 'active') {
            $q->where('status', 'active');
        } elseif ($this->tab === 'scheduled') {
            $q->where('status', 'scheduled');
        } else {
            // Storico: ended + revoked (adatti agli enum che già usi)
            $q->whereIn('status', ['ended', 'revoked']);
        }

        return $q->orderByDesc('start_at')->paginate(10, ['*'], 'assignmentsPage');
    }

    /** Cambio tab (valore ammesso: active|scheduled|history) */
    public function changeTab(string $tab): void
    {
        $allowed = ['active', 'scheduled', 'history'];
        if (in_array($tab, $allowed, true)) {
            $this->tab = $tab;
            // Resetta solo la pagina della lista assegnazioni
            $this->resetPage('assignmentsPage');
        }
    }

    /** Crea le assegnazioni per i veicoli selezionati (una per veicolo) */
    public function assignSelected(): void
    {
        $this->validate();
        $this->authorize('create', VehicleAssignment::class);

        if (empty($this->selectedVehicleIds)) {
            $this->addError('selectedVehicleIds', 'Seleziona almeno un veicolo.');
            return;
        }

        [$from, $to] = $this->rangeAsCarbon();
        $created = 0;
        $failed  = [];

        DB::transaction(function () use (&$created, &$failed, $from, $to) {
            foreach ($this->selectedVehicleIds as $vehicleId) {
                // Lock “pessimistico” sul set di righe potenzialmente in conflitto
                $hasConflict = VehicleAssignment::query()
                    ->where('vehicle_id', $vehicleId)
                    ->whereIn('status', ['scheduled','active'])
                    ->where(fn($q) => $this->overlapWhere($q, $from, $to))
                    ->lockForUpdate()
                    ->exists();

                $hasBlock = VehicleBlock::query()
                    ->where('vehicle_id', $vehicleId)
                    ->whereIn('status', ['scheduled','active'])
                    ->where(fn($q) => $this->overlapWhere($q, $from, $to))
                    ->lockForUpdate()
                    ->exists();

                if ($hasConflict || $hasBlock) {
                    $failed[] = $vehicleId;
                    continue;
                }

                // Determina stato iniziale in base alle date
                $now = now();
                $status = $from->isFuture() ? 'scheduled' : 'active';

                VehicleAssignment::create([
                    'vehicle_id'   => $vehicleId,
                    'renter_org_id'=> $this->renterOrgId,
                    'start_at'     => $from,
                    'end_at'       => $to,         // può essere null
                    'status'       => $status,     // ['scheduled','active','ended','revoked']
                    'mileage_start'=> null,        // opzionale: valorizzabile da UI avanzata
                    'mileage_end'  => null,
                    'notes'        => null,
                    'created_by'   => Auth::id(),
                ]);

                $created++;
            }
        });

        // Reset selezione e mostra esito
        $this->selectedVehicleIds = [];
        $this->confirmMessage = $created > 0
            ? "Create {$created} assegnazioni" . (count($failed) ? " (saltati ".count($failed)." veicoli per conflitto)" : ".")
            : "Nessuna assegnazione creata: tutti i veicoli selezionati risultano in conflitto.";

        // Aggiorna la lista
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.assignments.vehicle-assigner', [
            'vehicles'       => $this->vehicles,           // paginata
            'renterOptions'  => $this->renterOptions,      // select renter
        ]);
    }
}
