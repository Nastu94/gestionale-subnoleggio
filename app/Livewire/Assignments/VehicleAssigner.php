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
    VehicleState,
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

    /** @var array<string,string|null> Nuove date fine per estensioni (chiave = assignmentId) */
    public array $extend = [];

    /** Inizializza range date con “oggi → +30gg” per default */
    public function mount(): void
    {
        $now = Carbon::now();
        $this->dateFrom = $now->format('Y-m-d\TH:i');
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

    /**
     * Restituisce gli ID selezionati **normalizzati a int** e unici.
     */
    protected function selectedIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->selectedVehicleIds)));
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

            // Filtri testuali: passiamo da '=' a LIKE, normalizzando l'input
            ->when($this->filters['fuel_type'], function ($qq, $v) {
                /** Esempi che matchano:
                 *  'die*' → diesel; 'die' → %die%; 'die el' → %die%el%
                 */
                $qq->whereRaw('LOWER(fuel_type) LIKE ?', [$this->normalizeLike($v)]);
            })
            ->when($this->filters['transmission'], function ($qq, $v) {
                /** Esempi che matchano:
                 *  'man%' → manuale; 'man' → %man%; 'man auto' → %man%auto%
                 */
                $qq->whereRaw('LOWER(transmission) LIKE ?', [$this->normalizeLike($v)]);
            })
            ->when($this->filters['segment'], function ($qq, $v) {
                $qq->whereRaw('LOWER(segment) LIKE ?', [$this->normalizeLike($v)]);
            })

            // Filtri numerici: qui resta '=' (se servisse range, lo estendiamo dopo)
            ->when($this->filters['seats'], fn ($qq, $v) => $qq->where('seats', $v))

            ->where('is_active', true)

            // Ricerca libera: estendiamo anche a fuel_type & transmission
            ->when(strlen(trim($this->q)) > 0, function ($qq) {
                $like = $this->normalizeLike($this->q);
                $qq->where(function ($w) use ($like) {
                    $w->whereRaw('LOWER(plate) LIKE ?',       [$like])
                    ->orWhereRaw('LOWER(make) LIKE ?',      [$like])
                    ->orWhereRaw('LOWER(model) LIKE ?',     [$like])
                    ->orWhereRaw('LOWER(color) LIKE ?',     [$like])
                    ->orWhereRaw('LOWER(segment) LIKE ?',   [$like])
                    ->orWhereRaw('LOWER(fuel_type) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(transmission) LIKE ?', [$like]);
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
        // ID della pagina corrente (int)
        $pageIds = array_map('intval', $this->currentPageVehicleIds());

        // Opzionale: limita ai veicoli "selezionabili" (coerente con i checkbox disabilitati)
        if (!$this->renterOrgId) {
            // senza renter non facciamo nulla
            return;
        }
        $pageIds = array_values(array_filter($pageIds, fn($id) => $this->isVehicleAvailable($id)));

        // Stato corrente normalizzato (int)
        $current = $this->selectedIds();

        // Se almeno UNO della pagina è già selezionato → deseleziona TUTTI quelli della pagina
        $hasAnyOnPage = count(array_intersect($pageIds, $current)) > 0;

        if ($hasAnyOnPage) {
            // Rimuove dalla selezione tutti gli ID della pagina (int vs int → ok)
            $this->selectedVehicleIds = array_values(array_diff($current, $pageIds));
        } else {
            // Aggiunge tutti gli ID della pagina, evitando duplicati
            $this->selectedVehicleIds = array_values(array_unique(array_merge($current, $pageIds)));
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

    /**
     * Registra un log in vehicle_states in base all'assegnazione creata.
     * - Se l'assegnazione è futura (scheduled): crea un record 'assigned'
     *   con started_at = start_at e ended_at = end_at (non è lo stato corrente).
     * - Se è attiva (active): chiude l'eventuale stato aperto del veicolo
     *   e crea il nuovo stato 'assigned' con started_at = start_at (o now se preferisci).
     */
    protected function logStateForAssignment(VehicleAssignment $va): void
    {
        // Per sicurezza: lock sull'insieme degli stati del veicolo
        // (siamo già in transazione quando chiamata da assignSelected)
        $now = now();

        if ($va->start_at->isFuture()) {
            // Caso SCHEDULED: non tocchiamo lo stato corrente, logghiamo l'evento futuro
            VehicleState::create([
                'vehicle_id' => $va->vehicle_id,
                'state'      => 'assigned',
                'started_at' => $va->start_at,
                'ended_at'   => $va->end_at, // può essere null (assegnazione aperta futura)
                'reason'     => 'Assegnazione #'.$va->id.' programmata per l\'org '.$va->renter_org_id,
                'created_by' => Auth::id(),
            ]);
            return;
        }

        // Caso ACTIVE: chiudiamo lo stato corrente (se esiste) alla data di inizio assegnazione
        VehicleState::query()
            ->where('vehicle_id', $va->vehicle_id)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->update(['ended_at' => $va->start_at]);

        // Apriamo lo stato "assigned" valido da start_at → end_at (null = corrente)
        VehicleState::create([
            'vehicle_id' => $va->vehicle_id,
            'state'      => 'assigned',
            'started_at' => $va->start_at,
            'ended_at'   => $va->end_at, // se null, diventa lo stato corrente
            'reason'     => 'Assegnazione #'.$va->id.' per l\'org '.$va->renter_org_id,
            'created_by' => Auth::id(),
        ]);
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

                $va = VehicleAssignment::create([
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

                // 🔎 Log stato veicolo
                $this->logStateForAssignment($va);
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

    /**
     * Rimuove un'affidamento.
     * - scheduled: elimina l'assegnazione e rimuove il log "assigned" programmato
     * - active   : imposta status=revoked + end_at=now, chiude stato 'assigned' corrente e apre 'available'
     * - ended/revoked: elimina (lasciando il log storico intatto)
     */
    public function deleteAssignment(int $assignmentId): void
    {
        $va = VehicleAssignment::query()->findOrFail($assignmentId);
        $this->authorize('delete', $va);

        DB::transaction(function () use ($va) {
            $now = now();

            if ($va->status === 'scheduled') {
                // Elimina eventuale log programmato che corrisponde esattamente a questa assegnazione
                VehicleState::query()
                    ->where('vehicle_id', $va->vehicle_id)
                    ->where('state', 'assigned')
                    ->where('started_at', $va->start_at)
                    ->when($va->end_at, fn($q) => $q->where('ended_at', $va->end_at), fn($q) => $q->whereNull('ended_at'))
                    ->delete();

                $va->delete();
                return;
            }

            if ($va->status === 'active') {
                // Chiudi lo stato 'assigned' corrente
                VehicleState::query()
                    ->where('vehicle_id', $va->vehicle_id)
                    ->where('state', 'assigned')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->update(['ended_at' => $now, 'reason' => DB::raw("CONCAT(COALESCE(reason,''),' | revocata #{$va->id}')")]);

                // Apre lo stato 'available' da ora
                VehicleState::create([
                    'vehicle_id' => $va->vehicle_id,
                    'state'      => 'available',
                    'started_at' => $now,
                    'ended_at'   => null,
                    'reason'     => 'Assegnazione #'.$va->id.' revocata',
                    'created_by' => Auth::id(),
                ]);

                // Aggiorna l'assegnazione a revoked
                $va->update([
                    'status' => 'revoked',
                    'end_at' => $now,
                ]);

                return;
            }

            // ended o già revoked: elimino record amministrativo (il log storico resta)
            $va->delete();
        });

        // refresh tabella
        $this->resetPage('assignmentsPage');
        $this->confirmMessage = 'Assegnazione rimossa.';
    }

    /**
     * Chiude l'assegnazione attiva "adesso".
     * - Aggiorna assignment: status=ended, end_at=now
     * - Chiude state 'assigned' corrente e apre 'available'
     */
    public function closeAssignmentNow(int $assignmentId): void
    {
        $va = VehicleAssignment::query()->findOrFail($assignmentId);
        $this->authorize('update', $va);

        if ($va->status !== 'active') {
            $this->addError('action', 'Solo le assegnazioni attive possono essere chiuse.');
            return;
        }

        DB::transaction(function () use ($va) {
            $now = now();

            // Chiude eventuale stato "assigned" aperto
            VehicleState::query()
                ->where('vehicle_id', $va->vehicle_id)
                ->where('state', 'assigned')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->update(['ended_at' => $now, 'reason' => DB::raw("CONCAT(COALESCE(reason,''),' | terminata #{$va->id}')")]);

            // Apre "available" da ora
            VehicleState::create([
                'vehicle_id' => $va->vehicle_id,
                'state'      => 'available',
                'started_at' => $now,
                'ended_at'   => null,
                'reason'     => 'Assegnazione #'.$va->id.' terminata',
                'created_by' => Auth::id(),
            ]);

            // Aggiorna assignment
            $va->update([
                'status' => 'ended',
                'end_at' => $now,
            ]);
        });

        $this->confirmMessage = 'Assegnazione chiusa.';
        $this->resetPage('assignmentsPage');
    }

    /**
     * Estende la data di fine di un'assegnazione (active o scheduled).
     * - Valida che la nuova fine sia >= start_at e > end_at attuale (se presente)
     * - Impedisce overlap con altre assegnazioni/blocchi dello stesso veicolo
     * - Aggiorna il relativo VehicleState (aperto o programmato)
     */
    public function extendAssignment(int $assignmentId): void
    {
        $va = VehicleAssignment::query()->findOrFail($assignmentId);
        $this->authorize('update', $va);

        $newEnd = $this->extend[$assignmentId] ?? null;
        if (!$newEnd) {
            $this->addError('extend.'.$assignmentId, 'Inserisci una nuova data fine.');
            return;
        }

        $newEndAt = \Illuminate\Support\Carbon::parse($newEnd);

        // Validazioni temporali di base
        if ($newEndAt->lt($va->start_at)) {
            $this->addError('extend.'.$assignmentId, 'La data fine deve essere ≥ della data inizio.');
            return;
        }
        if ($va->end_at && $newEndAt->lte($va->end_at)) {
            $this->addError('extend.'.$assignmentId, 'La nuova fine deve essere successiva a quella attuale.');
            return;
        }

        // Conflitti con altre assegnazioni o blocchi
        $from = $va->start_at;
        $to   = $newEndAt;

        $overlapAssignments = VehicleAssignment::query()
            ->where('vehicle_id', $va->vehicle_id)
            ->where('id', '!=', $va->id)
            ->whereIn('status', ['scheduled','active'])
            ->where(fn($q) => $this->overlapWhere($q, $from, $to))
            ->exists();

        $overlapBlocks = VehicleBlock::query()
            ->where('vehicle_id', $va->vehicle_id)
            ->whereIn('status', ['scheduled','active'])
            ->where(fn($q) => $this->overlapWhere($q, $from, $to))
            ->exists();

        if ($overlapAssignments || $overlapBlocks) {
            $this->addError('extend.'.$assignmentId, 'Estensione non disponibile: conflitto con altre assegnazioni o blocchi.');
            return;
        }

        DB::transaction(function () use ($va, $newEndAt) {
            // Aggiorna assignment
            $va->update(['end_at' => $newEndAt]);

            // Aggiorna il relativo VehicleState:
            // - se active: record assigned aperto → ended_at = newEndAt
            // - se scheduled: record assigned programmato con stessi estremi → ended_at = newEndAt
            VehicleState::query()
                ->where('vehicle_id', $va->vehicle_id)
                ->where('state', 'assigned')
                ->where(function ($q) use ($va) {
                    $q->whereNull('ended_at')
                    ->orWhere('started_at', $va->start_at); // programmato con start uguale
                })
                ->lockForUpdate()
                ->update(['ended_at' => $newEndAt]);
        });

        $this->confirmMessage = 'Assegnazione estesa correttamente.';
        $this->resetPage('assignmentsPage');
    }
    
    /**
     * Renderizza il componente con i dati necessari.
     * - Veicoli filtrati e paginati
     * - Opzioni renter per la select
     * - Assegnazioni dell'organizzazione selezionata, filtrate per tab
     * - Messaggi di conferma
     * - Mantiene la paginazione separata per veicoli e assegnazioni
     * - Usa Blade per la view (resources/views/livewire/assignments/vehicle-assigner.blade.php)
     * - Usa Alpine.js per interazioni UI leggere (es. mostra/nascondi messaggi)
     * - Non include logica di autorizzazione: si assume che il middleware o il controller
     *   che carica questo componente abbiano già verificato i permessi necessari.
     */
    public function render()
    {
        return view('livewire.assignments.vehicle-assigner', [
            'vehicles'       => $this->vehicles,           // paginata
            'renterOptions'  => $this->renterOptions,      // select renter
        ]);
    }
}
