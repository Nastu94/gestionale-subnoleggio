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
 * Livewire: Admin ▸ Assegna veicoli ai renter (organizzazioni).
 *
 * - Nessun rename di campi o relazioni.
 * - Policy/permessi rispettati (authorize su create/delete/update).
 * - Controllo overlap con assegnazioni e blocchi.
 * - Logging stato veicolo su vehicle_states.
 * - Toast UI per esito azioni via browser event 'toast'.
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

    /** @var string Ricerca full-text semplificata (targa, marca, modello, …) */
    public string $q = '';

    /** @var array Filtri opzionali lato UI */
    public array $filters = [
        'fuel_type'      => null,
        'transmission'   => null,
        'segment'        => null,
        'seats'          => null,
        'only_available' => true, // mostra di default solo veicoli liberi nel range
    ];

    /** @var array<int|string> Selezione multipla veicoli da assegnare (checkbox → possono arrivare stringhe) */
    public array $selectedVehicleIds = [];

    /** @var string|null Messaggi/alert di conferma nella UI */
    public ?string $confirmMessage = null;

    /** @var string Tab corrente della tabella assegnazioni (active|scheduled|history) */
    public string $tab = 'active';

    /** @var array<string,string|null> Nuove date fine per estensioni (chiave = assignmentId) */
    public array $extend = [];

    /** Inizializza range date con “oggi” */
    public function mount(): void
    {
        $now = Carbon::now();
        $this->dateFrom = $now->format('Y-m-d\TH:i');
        // $this->dateTo resta opzionale (null = aperto)
    }

    /** Regole di validazione (Laravel 12) */
    protected function rules(): array
    {
        return [
            'renterOrgId' => ['required', Rule::exists('organizations', 'id')],
            'dateFrom'    => ['required', 'date'],
            'dateTo'      => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'selectedVehicleIds'   => ['array'],
            'selectedVehicleIds.*' => [Rule::exists('vehicles', 'id')],
        ];
    }

    /** Toast helper: emette evento browser per il sistema toast Alpine */
    protected function toast(string $type, string $message, int $duration = 3000): void
    {
        // Livewire 3: browser event → intercettato dal tuo componente toast
        $this->dispatch('toast', type: $type, message: $message, duration: $duration);
    }

    /**
     * Restituisce gli ID selezionati normalizzati a int e unici.
     * (checkbox → stringhe; normalizziamo per confronti corretti)
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

            // Filtri testuali con LIKE case-insensitive
            ->when($this->filters['fuel_type'], function ($qq, $v) {
                $qq->whereRaw('LOWER(fuel_type) LIKE ?', [$this->normalizeLike($v)]);
            })
            ->when($this->filters['transmission'], function ($qq, $v) {
                $qq->whereRaw('LOWER(transmission) LIKE ?', [$this->normalizeLike($v)]);
            })
            ->when($this->filters['segment'], function ($qq, $v) {
                $qq->whereRaw('LOWER(segment) LIKE ?', [$this->normalizeLike($v)]);
            })

            // Filtri numerici invariati
            ->when($this->filters['seats'], fn ($qq, $v) => $qq->where('seats', $v))

            ->where('is_active', true)

            // Ricerca libera estesa a fuel_type & transmission
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

    /** Verifica se un veicolo è disponibile nel range selezionato. */
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

        $ids = Vehicle::query()->where('is_active', true)->pluck('id');

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
     * Condizione di overlap:
     * due intervalli si sovrappongono se start1 <= end2  AND  start2 <= end1
     * dove end null = +∞.
     */
    protected function overlapWhere($q, Carbon $from, ?Carbon $to): void
    {
        $endFilter = $to?->copy() ?? Carbon::create(9999,12,31,23,59,59);

        $q->where('start_at', '<=', $endFilter)
          ->where(function ($qq) use ($from) {
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

    /** Restituisce gli ID dei veicoli della pagina corrente. */
    protected function currentPageVehicleIds(): array
    {
        $paginator = $this->vehicles; // computed property
        return $paginator->getCollection()->pluck('id')->all();
    }

    /**
     * Seleziona o deseleziona TUTTI i veicoli della pagina corrente.
     * - Se almeno uno è già selezionato → deseleziona tutti quelli della pagina
     * - Altrimenti → seleziona tutti quelli della pagina (evitando duplicati)
     */
    public function toggleSelectAll(): void
    {
        $pageIds = array_map('intval', $this->currentPageVehicleIds());

        if (!$this->renterOrgId) {
            return; // coerente con checkbox disabilitati
        }
        // Considera solo veicoli effettivamente selezionabili
        $pageIds = array_values(array_filter($pageIds, fn($id) => $this->isVehicleAvailable($id)));

        $current = $this->selectedIds();

        $hasAnyOnPage = count(array_intersect($pageIds, $current)) > 0;

        if ($hasAnyOnPage) {
            $this->selectedVehicleIds = array_values(array_diff($current, $pageIds));
        } else {
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
            return VehicleAssignment::query()
                ->whereRaw('1=0')
                ->paginate(10, ['*'], 'assignmentsPage');
        }

        /** @var \Illuminate\Support\Carbon $now */
        $now = Carbon::now();

        $q = VehicleAssignment::query()
            ->with(['vehicle'])
            ->where('renter_org_id', $this->renterOrgId);

        if ($this->tab === 'active') {
            /**
             * Attive = iniziate e non ancora terminate (end_at null oppure > now).
             * Escludo inoltre gli status conclusivi per coerenza.
             */
            $q->where('start_at', '<=', $now)
            ->where(function ($qq) use ($now) {
                $qq->whereNull('end_at')
                    ->orWhere('end_at', '>', $now);
            })
            ->whereNotIn('status', ['ended', 'revoked']);
        } elseif ($this->tab === 'scheduled') {
            /**
             * Programmate = iniziano nel futuro.
             * Escludo status conclusivi.
             */
            $q->where('start_at', '>', $now)
            ->whereNotIn('status', ['ended', 'revoked']);
        } else {
            /**
             * Storico = status conclusi (ended/revoked) OPPURE assegnazioni con end_at passato.
             * Serve a “recuperare” eventuali righe rimaste status=active ma con end_at già superato.
             */
            $q->where(function ($qq) use ($now) {
                $qq->whereIn('status', ['ended', 'revoked'])
                ->orWhere(function ($q2) use ($now) {
                    $q2->whereNotNull('end_at')
                        ->where('end_at', '<=', $now);
                });
            });
        }

        return $q->orderByDesc('start_at')->paginate(10, ['*'], 'assignmentsPage');
    }

    /** Cambio tab (valore ammesso: active|scheduled|history) */
    public function changeTab(string $tab): void
    {
        $allowed = ['active', 'scheduled', 'history'];
        if (in_array($tab, $allowed, true)) {
            $this->tab = $tab;
            $this->resetPage('assignmentsPage');
        }
    }

    /**
     * Logga lo stato 'assigned' su vehicle_states in base all'assegnazione.
     * - scheduled: crea record futuro (non corrente)
     * - active   : chiude eventuale stato corrente e apre 'assigned'
     */
    protected function logStateForAssignment(VehicleAssignment $va): void
    {
        if ($va->start_at->isFuture()) {
            VehicleState::create([
                'vehicle_id' => $va->vehicle_id,
                'state'      => 'assigned',
                'started_at' => $va->start_at,
                'ended_at'   => $va->end_at,
                'reason'     => 'Assegnazione #'.$va->id.' programmata per org '.$va->renter_org_id,
                'created_by' => Auth::id(),
            ]);
            return;
        }

        VehicleState::query()
            ->where('vehicle_id', $va->vehicle_id)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->update(['ended_at' => $va->start_at]);

        VehicleState::create([
            'vehicle_id' => $va->vehicle_id,
            'state'      => 'assigned',
            'started_at' => $va->start_at,
            'ended_at'   => $va->end_at,
            'reason'     => 'Assegnazione #'.$va->id.' per org '.$va->renter_org_id,
            'created_by' => Auth::id(),
        ]);
    }

    /** Crea le assegnazioni per i veicoli selezionati (una per veicolo) */
    public function assignSelected(): void
    {
        $this->validate();
        $this->authorize('create', VehicleAssignment::class);

        $selected = $this->selectedIds();
        if (empty($selected)) {
            $this->addError('selectedVehicleIds', 'Seleziona almeno un veicolo.');
            $this->toast('warning', 'Seleziona almeno un veicolo.');
            return;
        }

        [$from, $to] = $this->rangeAsCarbon();
        $created = 0;
        $failed  = [];

        DB::transaction(function () use (&$created, &$failed, $from, $to, $selected) {
            foreach ($selected as $vehicleId) {
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

                $status = $from->isFuture() ? 'scheduled' : 'active';

                /** @var VehicleAssignment $va */
                $va = VehicleAssignment::create([
                    'vehicle_id'    => $vehicleId,
                    'renter_org_id' => $this->renterOrgId,
                    'start_at'      => $from,
                    'end_at'        => $to,         // può essere null
                    'status'        => $status,     // ['scheduled','active','ended','revoked']
                    'mileage_start' => null,
                    'mileage_end'   => null,
                    'notes'         => null,
                    'created_by'    => Auth::id(),
                ]);

                $this->logStateForAssignment($va);
                $created++;
            }
        });

        // Reset selezione e messaggi riepilogo
        $this->selectedVehicleIds = [];
        $this->confirmMessage = $created > 0
            ? "Create {$created} assegnazioni" . (count($failed) ? " (saltati ".count($failed)." veicoli per conflitto)" : ".")
            : "Nessuna assegnazione creata: tutti i veicoli selezionati risultano in conflitto.";

        // Toast coerenti con l'esito
        if ($created === 0) {
            $this->toast('warning', $this->confirmMessage, 5000);
        } else {
            $this->toast('success', "Create {$created} assegnazioni.", 4000);
            if (count($failed)) {
                $this->toast('warning', 'Alcuni veicoli sono stati saltati per conflitto.', 5000);
            }
        }

        // Aggiorna le liste/pagine
        $this->resetPage();                 // vehiclesPage
        $this->resetPage('assignmentsPage'); // per sicurezza aggiorna anche la tabella destra
    }

    /**
     * Rimuove un'affidamento.
     * - scheduled: elimina l'assegnazione e rimuove il log "assigned" programmato
     * - active   : imposta status=revoked + end_at=now, chiude stato 'assigned' e apre 'available'
     * - ended/revoked: elimina (lo storico stati resta)
     */
    public function deleteAssignment(int $assignmentId): void
    {
        $va = VehicleAssignment::query()->findOrFail($assignmentId);
        $this->authorize('delete', $va);

        DB::transaction(function () use ($va) {
            $now = now();

            if ($va->status === 'scheduled') {
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
                VehicleState::query()
                    ->where('vehicle_id', $va->vehicle_id)
                    ->where('state', 'assigned')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->update(['ended_at' => $now, 'reason' => DB::raw("CONCAT(COALESCE(reason,''),' | revocata #{$va->id}')")]);

                VehicleState::create([
                    'vehicle_id' => $va->vehicle_id,
                    'state'      => 'available',
                    'started_at' => $now,
                    'ended_at'   => null,
                    'reason'     => 'Assegnazione #'.$va->id.' revocata',
                    'created_by' => Auth::id(),
                ]);

                $va->update([
                    'status' => 'revoked',
                    'end_at' => $now,
                ]);

                return;
            }

            // ended o già revoked
            $va->delete();
        });

        $this->resetPage('assignmentsPage');
        $this->confirmMessage = 'Assegnazione rimossa.';
        $this->toast('success', 'Assegnazione rimossa.');
    }

    /**
     * Chiude l'assegnazione attiva "adesso".
     * - Aggiorna assignment: status=ended, end_at=now
     * - Chiude state 'assigned' e apre 'available'
     */
    public function closeAssignmentNow(int $assignmentId): void
    {
        $va = VehicleAssignment::query()->findOrFail($assignmentId);
        $this->authorize('update', $va);

        if ($va->status !== 'active') {
            $this->addError('action', 'Solo le assegnazioni attive possono essere chiuse.');
            $this->toast('error', 'Solo le assegnazioni attive possono essere chiuse.');
            return;
        }

        DB::transaction(function () use ($va) {
            $now = now();

            VehicleState::query()
                ->where('vehicle_id', $va->vehicle_id)
                ->where('state', 'assigned')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->update(['ended_at' => $now, 'reason' => DB::raw("CONCAT(COALESCE(reason,''),' | terminata #{$va->id}')")]);

            VehicleState::create([
                'vehicle_id' => $va->vehicle_id,
                'state'      => 'available',
                'started_at' => $now,
                'ended_at'   => null,
                'reason'     => 'Assegnazione #'.$va->id.' terminata',
                'created_by' => Auth::id(),
            ]);

            $va->update([
                'status' => 'ended',
                'end_at' => $now,
            ]);
        });

        $this->confirmMessage = 'Assegnazione chiusa.';
        $this->toast('success', 'Assegnazione chiusa.');
        $this->resetPage('assignmentsPage');
    }

    /**
     * Estende la data di fine di un'assegnazione (active o scheduled).
     * - Valida che la nuova fine sia ≥ start_at e > end_at attuale (se presente)
     * - Impedisce overlap con altre assegnazioni/blocchi
     * - Aggiorna il relativo VehicleState
     */
    public function extendAssignment(int $assignmentId): void
    {
        $va = VehicleAssignment::query()->findOrFail($assignmentId);
        $this->authorize('update', $va);

        $newEnd = $this->extend[$assignmentId] ?? null;
        if (!$newEnd) {
            $this->addError('extend.'.$assignmentId, 'Inserisci una nuova data fine.');
            $this->toast('error', 'Inserisci una nuova data fine.');
            return;
        }

        $newEndAt = Carbon::parse($newEnd);

        if ($newEndAt->lt($va->start_at)) {
            $this->addError('extend.'.$assignmentId, 'La data fine deve essere ≥ della data inizio.');
            $this->toast('error', 'La data fine deve essere ≥ della data inizio.');
            return;
        }
        if ($va->end_at && $newEndAt->lte($va->end_at)) {
            $this->addError('extend.'.$assignmentId, 'La nuova fine deve essere successiva a quella attuale.');
            $this->toast('error', 'La nuova fine deve essere successiva a quella attuale.');
            return;
        }

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
            $this->toast('error', 'Estensione non disponibile: conflitto con altre assegnazioni o blocchi.', 5000);
            return;
        }

        DB::transaction(function () use ($va, $newEndAt) {
            $va->update(['end_at' => $newEndAt]);

            VehicleState::query()
                ->where('vehicle_id', $va->vehicle_id)
                ->where('state', 'assigned')
                ->where(function ($q) use ($va) {
                    $q->whereNull('ended_at')
                      ->orWhere('started_at', $va->start_at);
                })
                ->lockForUpdate()
                ->update(['ended_at' => $newEndAt]);
        });

        $this->confirmMessage = 'Assegnazione estesa correttamente.';
        $this->toast('success', 'Assegnazione estesa correttamente.');
        $this->resetPage('assignmentsPage');
    }

    /**
     * Render del componente: passa liste/paginazioni e opzioni alla view Blade.
     */
    public function render()
    {
        return view('livewire.assignments.vehicle-assigner', [
            'vehicles'      => $this->vehicles,      // paginata
            'renterOptions' => $this->renterOptions, // select renter
        ]);
    }
}
