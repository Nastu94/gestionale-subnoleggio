<?php

namespace App\Livewire\Rentals;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Rental;
use App\Models\Organization;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class RentalsBoard extends Component
{
    use WithPagination;

    /** Vista corrente: 'table' | 'kanban' | 'planner' */
    #[Url(as: 'view', except: 'table')]
    public string $view = 'table';

    /** Stato selezionato: default 'draft' (Bozze) - usato per tabella/bacheca */
    #[Url(as: 'state', except: 'draft')]
    public ?string $state = 'draft';

    /** Ricerca libera (id o cliente) */
    public string $q = '';

    /**
     * Modalità del planner:
     * - 'week'  => vista settimanale (veicoli x giorni)
     * - 'day'   => vista giornaliera (veicoli x ore)
     *
     * Questa proprietà è usata SOLO quando $view === 'planner'.
     */
    public string $plannerMode = 'week';

    /**
     * Data base del planner in formato 'Y-m-d'.
     *
     * - In modalità 'week' rappresenta il LUNEDÌ della settimana mostrata.
     * - In modalità 'day' rappresenta il giorno attualmente visualizzato.
     *
     * Usiamo una stringa per mantenerla facilmente serializzabile da Livewire,
     * e la convertiremo in Carbon quando serviranno calcoli sulle date.
     */
    public ?string $plannerDate = null;

    /**
     * Filtro di stato per il planner.
     *
     * - 'all'  => mostra tutti gli stati
     * - altrimenti uno degli 8 stati: 'draft','reserved','checked_out',...
     *
     * Questo filtro è indipendente da $state (che resta per tabella/bacheca).
     */
    public string $plannerStatusFilter = 'all';

    /**
     * Filtro organizzazione per il planner (solo admin).
     *
     * - null             => per gli admin, significa "veicoli non assegnati"
     * - id numerico      => mostra veicoli dell'organizzazione selezionata
     *
     * Per renter/sub-renter questo filtro non verrà esposto a UI
     * e potrà rimanere semplicemente null.
     */
    public ?int $plannerOrganizationFilter = null;

    /** Etichette in italiano per stati */
    public array $stateLabels = [
        'draft'       => 'Bozze',
        'reserved'    => 'Prenotati',
        'in_use'      => 'In uso',
        'checked_in'  => 'Rientrati',
        'closed'      => 'Chiusi',
        'cancelled'   => 'Cancellati',
    ];

    /** Classi colore (card KPI / badge) per stato */
    public array $stateColors = [
        'draft'       => 'bg-gray-100 border-gray-300 text-gray-800',
        'reserved'    => 'bg-blue-100 border-blue-300 text-blue-900',
        'in_use'      => 'bg-slate-100 border-slate-300 text-slate-900',
        'checked_in'  => 'bg-indigo-100 border-indigo-300 text-indigo-900',
        'closed'      => 'bg-green-100 border-green-300 text-green-900',
        'cancelled'   => 'bg-rose-100 border-rose-300 text-rose-900',
    ];

    protected $queryString = [
        'view'  => ['as' => 'view', 'except' => 'table'],
        // manteniamo 'draft' come default: non salvare in querystring finché è 'draft'
        'state' => ['as' => 'state', 'except' => 'draft'],
        'q'     => ['as' => 'q', 'except' => ''],
    ];

    /**
     * Hook di inizializzazione del componente.
     *
     * - Inizializza la data base del planner alla settimana corrente,
     *   usando il lunedì come giorno di riferimento.
     * - Non modifica il comportamento di tabella/bacheca.
     */
    public function mount(): void
    {
        // Se la data del planner non è stata ancora impostata (es. da querystring/eventi),
        // usiamo il lunedì della settimana corrente come default.
        if ($this->plannerDate === null) {
            $this->plannerDate = Carbon::now()
                ->startOfWeek(Carbon::MONDAY)
                ->toDateString(); // 'Y-m-d'
        }

        // Modalità di default: vista settimanale del planner
        if ($this->plannerMode === '') {
            $this->plannerMode = 'week';
        }

        // Filtro di default: tutti gli stati nel planner
        if ($this->plannerStatusFilter === '') {
            $this->plannerStatusFilter = 'all';
        }
    }

    /** Ordine colonne/pulsanti KPI */
    public function getStatesProperty(): array
    {
        return ['draft','reserved','in_use','checked_in','closed','cancelled'];
    }

    /** Ricerca riutilizzabile su id e cliente */
    protected function applySearch(Builder $q): Builder
    {
        $term = trim((string) $this->q);
        if ($term === '') {
            return $q;
        }

        return $q->where(function (Builder $sub) use ($term) {
            // ✅ niente reference: ricerchiamo su id LIKE e nome cliente
            $sub->where('id', 'like', "%{$term}%")
                ->orWhereExists(function ($c) use ($term) {
                    $c->selectRaw(1)
                        ->from('customers')
                        ->whereColumn('rentals.customer_id', 'customers.id')
                        ->whereNull('customers.deleted_at')
                        ->where('customers.name', 'like', "%{$term}%");
                });
        });
    }

    /** Restringi visibilità in base al ruolo utente */
    protected function restrictToViewer(Builder $q): Builder
    {
        $u = Auth::user();
        if ($u->hasAnyRole(['admin','super-admin'])) return $q;
        if ($u->hasRole('renter') && $u->renter_id)   return $q->where('renter_id', $u->renter_id);
        if ($u->hasRole('sub-renter') && $u->sub_renter_id) return $q->where('sub_renter_id', $u->sub_renter_id);
        return $q->where('organization_id', $u->id);
    }

    /** KPI per stato: rispettare ricerca + stato, con parentesi corrette */
    public function getKpisProperty(): array
    {
        $base = $this->restrictToViewer(
            $this->applySearch(Rental::query()->whereNull('deleted_at'))
        );
        $states = ['draft','reserved','in_use','checked_in','closed','cancelled'];
        $out = [];
        foreach ($states as $s) $out[$s] = (clone $base)->where('status', $s)->count();
        return $out;
    }

    /** Query base per righe: stato selezionato + ricerca + restrizione ruolo */
    protected function baseRowsQuery(): Builder
    {
        $q = Rental::query()->whereNull('deleted_at');
        if ($this->state) $q->where('status', $this->state);
        $q = $this->applySearch($q);
        $q = $this->restrictToViewer($q);
        return $q->with(['customer','vehicle'])->orderByDesc('id');
    }

    /** Lista righe: stato selezionato + ricerca, con parentesi corrette */
    public function getRowsProperty()
    {
        $q = Rental::query()->whereNull('deleted_at');

        // Stato attivo (se presente)
        if ($this->state) {
            $q->where('status', $this->state);
        }

        // Ricerca
        $q = $this->applySearch($q);

        return $q->latest('id')->with(['customer', 'vehicle'])->paginate(15);
    }

    /**
     * Cambia la vista corrente (Elenco / Bacheca / Planner).
     *
     * - 'table'   => elenco tabellare
     * - 'kanban'  => bacheca per stato
     * - 'planner' => planner veicoli x calendario
     *
     * Il reset della pagina serve a non rimanere "incastrati"
     * su pagine di paginazione non più valide dopo il cambio vista.
     */
    public function setView(string $view): void
    {
        $allowedViews = ['table', 'kanban', 'planner'];

        $this->view = in_array($view, $allowedViews, true)
            ? $view
            : 'table';

        $this->resetPage();
    }

    /**
     * Cambia lo stato selezionato (usato in tabella/bacheca).
     *
     * Il reset della pagina serve a non rimanere "incastrati"
     * su pagine di paginazione non più valide dopo il cambio stato.
     */
    public function filterState(?string $state): void
    {
        $this->state = $state ?: 'draft';
        $this->resetPage();
    }

    /**
     * Elenco organizzazioni utilizzabile nel filtro del planner (solo per admin).
     *
     * - Admin / super-admin: ritorna la lista delle organizzazioni ordinata per nome.
     * - Altri ruoli: collection vuota (in view non mostreremo proprio il select).
     *
     * In Blade useremo $this->plannerOrganizations.
     */
    public function getPlannerOrganizationsProperty()
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['admin', 'super-admin'])) {
            // Nessuna organizzazione per ruoli non amministrativi
            return collect();
        }

        return Organization::query()
            ->whereNot('id', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Restituisce un'etichetta leggibile per il periodo del planner
     * in base alla modalità corrente (settimana / giorno).
     *
     * Esempi:
     * - modalità 'week': "10/11/2025 – 16/11/2025"
     * - modalità 'day':  "11/11/2025"
     */
    public function getPlannerPeriodLabelProperty(): string
    {
        // Se per qualche motivo plannerDate è null, ripieghiamo su oggi
        $date = Carbon::parse($this->plannerDate ?? now()->toDateString());

        if ($this->plannerMode === 'day') {
            // Vista giornaliera: mostriamo solo la data del giorno
            return $date->format('d/m/Y');
        }

        // Vista settimanale: calcoliamo lunedì e domenica della settimana
        $start = $date->copy()->startOfWeek(Carbon::MONDAY);
        $end   = $date->copy()->endOfWeek(Carbon::SUNDAY);

        return $start->format('d/m/Y') . ' – ' . $end->format('d/m/Y');
    }

    /**
     * Giorno corrente del planner come stringa 'Y-m-d'.
     *
     * È solo un alias “comodo” di plannerDate, già normalizzata
     * da setPlannerMode / openPlannerDay / goToPrevious/NextPeriod.
     */
    public function getPlannerCurrentDateProperty(): string
    {
        $base = $this->plannerDate
            ? Carbon::parse($this->plannerDate)
            : Carbon::today();

        return $base->toDateString(); // 'YYYY-MM-DD'
    }

    /**
     * Imposta la modalità del planner (settimana/giorno),
     * normalizzando la data di riferimento.
     *
     * - 'day'  => quando passiamo esplicitamente alla vista giornaliera
     *             tramite il pulsante, puntiamo sempre ad OGGI.
     * - 'week' => la data viene allineata al lunedì della settimana
     *             relativa alla plannerDate corrente (o ad oggi se nulla).
     */
    public function setPlannerMode(string $mode): void
    {
        // Normalizziamo il valore richiesto: solo 'week' o 'day'
        $mode = in_array($mode, ['week', 'day'], true) ? $mode : 'week';

        $this->plannerMode = $mode;

        if ($this->plannerMode === 'day') {
            // Vista giornaliera: quando l'utente clicca "Giorno"
            // vogliamo sempre puntare ad oggi.
            $date = Carbon::today()->startOfDay();
        } else {
            // Vista settimanale: usiamo la plannerDate corrente (se presente),
            // altrimenti oggi, e la portiamo al LUNEDÌ di quella settimana.
            $base = $this->plannerDate
                ? Carbon::parse($this->plannerDate)
                : Carbon::today();

            $date = $base->startOfWeek(Carbon::MONDAY);
        }

        // Salviamo la data normalizzata in formato 'Y-m-d'
        $this->plannerDate = $date->toDateString();
    }

    /**
     * Sposta il planner al periodo precedente
     * (una settimana o un giorno in base alla modalità corrente).
     */
    public function goToPreviousPeriod(): void
    {
        $date = Carbon::parse($this->plannerDate ?? now()->toDateString());

        if ($this->plannerMode === 'day') {
            // Vista giornaliera: ci muoviamo di 1 giorno
            $date->subDay();
        } else {
            // Vista settimanale: ci muoviamo di 1 settimana
            $date->subWeek();
        }

        // Normalizziamo la data come in setPlannerMode
        if ($this->plannerMode === 'week') {
            $date->startOfWeek(Carbon::MONDAY);
        } else {
            $date->startOfDay();
        }

        $this->plannerDate = $date->toDateString();
    }

    /**
     * Sposta il planner al periodo successivo
     * (una settimana o un giorno in base alla modalità corrente).
     */
    public function goToNextPeriod(): void
    {
        $date = Carbon::parse($this->plannerDate ?? now()->toDateString());

        if ($this->plannerMode === 'day') {
            // Vista giornaliera: +1 giorno
            $date->addDay();
        } else {
            // Vista settimanale: +1 settimana
            $date->addWeek();
        }

        if ($this->plannerMode === 'week') {
            $date->startOfWeek(Carbon::MONDAY);
        } else {
            $date->startOfDay();
        }

        $this->plannerDate = $date->toDateString();
    }

    /**
     * Dal planner settimanale, apre la vista giornaliera
     * per una data specifica cliccando sull'intestazione del giorno.
     *
     * - Imposta la modalità del planner su 'day'
     * - Imposta la plannerDate alla data cliccata (inizio del giorno)
     *
     * Non tocca la logica del pulsante "Giorno", che continua
     * a puntare ad oggi quando usato dalla toolbar.
     */
    public function openPlannerDay(string $date): void
    {
        // Normalizziamo la data ricevuta a inizio giorno
        $day = Carbon::parse($date)->startOfDay();

        $this->plannerMode = 'day';
        $this->plannerDate = $day->toDateString();
    }

    /**
     * Calcola il range temporale corrente del planner in base a:
     * - $plannerMode  ('week' | 'day')
     * - $plannerDate  (stringa 'Y-m-d')
     *
     * Ritorna un array associativo con:
     * - 'start' => Carbon (inizio range)
     * - 'end'   => Carbon (fine range)
     *
     * Questo metodo verrà riutilizzato sia per:
     * - la vista settimanale (range lunedì–domenica)
     * - la vista giornaliera (range giorno singolo)
     */
    protected function getPlannerRange(): array
    {
        // Se plannerDate è null per qualsiasi motivo, ripieghiamo su oggi
        $base = $this->plannerDate
            ? Carbon::parse($this->plannerDate)
            : Carbon::today();

        if ($this->plannerMode === 'day') {
            // Vista giornaliera: range = intero giorno (00:00–23:59:59)
            $start = $base->copy()->startOfDay();
            $end   = $base->copy()->endOfDay();
        } else {
            // Vista settimanale: range = lunedì–domenica della settimana corrente
            $start = $base->copy()->startOfWeek(Carbon::MONDAY);
            $end   = $base->copy()->endOfWeek(Carbon::SUNDAY);
        }

        return [
            'start' => $start,
            'end'   => $end,
        ];
    }

    /**
     * Elenco dei giorni da mostrare nella vista settimanale del planner.
     *
     * Ritorna un array di 7 elementi (lun–dom), ognuno con:
     * - 'date'  => string 'Y-m-d' (comoda per confronti / query)
     * - 'label' => string etichetta leggibile es. "Lun 10/11" in italiano
     *
     * In Blade sarà accessibile come $this->plannerWeekDays.
     */
    public function getPlannerWeekDaysProperty(): array
    {
        // Recuperiamo il range corrente del planner
        $range = $this->getPlannerRange();
        $start = $range['start']; // lunedì in modalità 'week'
        $end   = $range['end'];

        $days = [];

        // Partiamo dal giorno di inizio (normalizzato a lunedì in modalità 'week')
        $current = $start->copy();

        // Iteriamo finché non superiamo il giorno di fine (domenica)
        while ($current->lessThanOrEqualTo($end)) {
            $days[] = [
                'date'  => $current->toDateString(), // es. "2025-11-10"

                // Usa i nomi dei giorni tradotti in base alla locale (es. "lun 10/11").
                // Richiede che Carbon abbia la locale configurata su 'it' (in Laravel di solito lo è).
                'label' => $current->translatedFormat('D d/m'),
            ];

            $current->addDay();
        }

        return $days;
    }

    /**
     * Elenco veicoli da mostrare nel planner.
     *
     * Regole:
     * - Renter: vede tutti i veicoli A LUI affidati (assignment aperto),
     *           sia liberi che occupati da un noleggio.
     * - Admin:  di default vede i veicoli del proprio parco
     *           NON assegnati a nessun renter (nessun assignment aperto).
     *           Se è impostato plannerOrganizationFilter, vede i veicoli
     *           attualmente affidati a quella organizzazione.
     *
     * Ritorna una Collection di Vehicle.
     * In Blade la useremo come $this->plannerVehicles.
     */
    public function getPlannerVehiclesProperty()
    {
        $user = Auth::user();
        $org  = $user->organization ?? null;

        // Se l'utente non è legato ad alcuna organizzazione, non mostriamo nulla
        if (! $org) {
            return collect();
        }

        // Partiamo dai soli veicoli attivi
        $q = Vehicle::query()->active();

        // Caso: organizzazione RENTER (noleggiatore)
        if ($org->isRenter()) {
            // Tutti i veicoli che hanno un affidamento aperto verso questa org
            $q->whereHas('assignments', function ($assign) use ($org) {
                $assign
                    ->where('renter_org_id', $org->id)
                    ->whereNull('end_at'); // end_at NULL = affidamento aperto
            });
        }
        // Caso: organizzazione ADMIN (parco veicoli proprio)
        elseif ($org->isAdmin()) {
            // Veicoli del parco dell'admin corrente
            $q->where('admin_organization_id', $org->id);

            if ($this->plannerOrganizationFilter) {
                // Filtriamo per veicoli affidati all'organizzazione selezionata
                $targetOrgId = $this->plannerOrganizationFilter;

                $q->whereHas('assignments', function ($assign) use ($targetOrgId) {
                    $assign
                        ->where('renter_org_id', $targetOrgId)
                        ->whereNull('end_at');
                });
            } else {
                // Default admin: veicoli NON assegnati a nessun renter
                $q->whereDoesntHave('assignments', function ($assign) {
                    $assign->whereNull('end_at');
                });
            }
        }
        // Altri tipi di organizzazione: per ora non mostriamo nulla
        else {
            return collect();
        }

        // Ordiniamo in modo leggibile: targa, marca, modello
        return $q
            ->orderBy('plate')
            ->orderBy('make')
            ->orderBy('model')
            ->get();
    }

    /**
     * Noleggi da usare nel planner (settimana/giorno).
     *
     * Regole:
     * - Subiscono le stesse restrizioni di visibilità dell'utente
     *   (admin / renter / sub-renter) via restrictToViewer().
     * - Applicano la ricerca libera $q (id + nome cliente).
     * - Applicano il filtro di stato del PLANNER ($plannerStatusFilter),
     *   che è indipendente da $state (usato da tabella/bacheca).
     * - Devono "toccare" il range temporale del planner:
     *   [planned_pickup_at, planned_return_at] si sovrappone al range
     *   [start, end] calcolato da getPlannerRange().
     *
     * In Blade useremo $this->plannerRentals.
     */
    public function getPlannerRentalsProperty()
    {
        // Range corrente del planner (settimana intera o singolo giorno)
        $range = $this->getPlannerRange();
        $start = $range['start']->copy()->startOfDay();
        $end   = $range['end']->copy()->endOfDay();

        // Query base: noleggi non soft-deleted
        $q = Rental::query()->whereNull('deleted_at');

        // Restringiamo i noleggi a quelli visibili per l'utente corrente
        $q = $this->restrictToViewer($q);

        // Applichiamo la ricerca libera (id + nome cliente)
        $q = $this->applySearch($q);

        // Filtro di stato specifico del PLANNER:
        // - 'all' => nessun filtro aggiuntivo
        // - altro => where status = valore scelto
        if ($this->plannerStatusFilter !== 'all') {
            $q->where('status', $this->plannerStatusFilter);
        }

        // Sovrapposizione con il range del planner:
        // includiamo solo i noleggi il cui intervallo
        // [planned_pickup_at, planned_return_at] "tocca" [start, end].
        $q->where(function (Builder $overlap) use ($start, $end) {
            $overlap
                // Il ritiro deve avvenire PRIMA o entro la fine del range
                ->where('planned_pickup_at', '<=', $end)
                // E la riconsegna deve essere DOPO o entro l'inizio del range,
                // oppure NULL (noleggio ancora aperto).
                ->where(function (Builder $inner) use ($start) {
                    $inner
                        ->whereNull('planned_return_at')
                        ->orWhere('planned_return_at', '>=', $start);
                });
        });

        // Precarichiamo le relazioni che sicuramente serviranno nel planner
        return $q
            ->with(['vehicle', 'customer'])
            ->get();
    }

    /**
     * Matrice veicolo × giorno per la vista settimanale del planner.
     *
     * Struttura:
     * [
     *   vehicle_id => [
     *       'YYYY-MM-DD' => [ Rental, Rental, ... ],   // noleggi che toccano quel giorno
     *       ...
     *   ],
     *   ...
     * ]
     *
     * - Considera solo i rentals già filtrati da getPlannerRentalsProperty().
     * - Considera solo i veicoli presenti in plannerVehicles.
     * - Un rental viene associato a tutti i giorni della settimana in cui
     *   il suo intervallo [pickup, return] si sovrappone a quel giorno.
     *
     * In Blade useremo $this->plannerMatrix.
     */
    public function getPlannerMatrixProperty(): array
    {
        $vehicles = $this->plannerVehicles;
        $days     = $this->plannerWeekDays;
        $rentals  = $this->plannerRentals;

        $matrix = [];

        // Inizializziamo la matrice con chiavi veicolo/data vuote,
        // così l'accesso è sempre sicuro anche in assenza di noleggi.
        foreach ($vehicles as $vehicle) {
            $matrix[$vehicle->id] = [];

            foreach ($days as $day) {
                $matrix[$vehicle->id][$day['date']] = [];
            }
        }

        // Indichiamo velocemente i giorni della settimana come Carbon,
        // così evitiamo di fare parse ripetute dentro i loop dei rentals.
        $dayRanges = [];
        foreach ($days as $day) {
            $dayDate = Carbon::parse($day['date']);
            $dayRanges[$day['date']] = [
                'start' => $dayDate->copy()->startOfDay(),
                'end'   => $dayDate->copy()->endOfDay(),
            ];
        }

        // Distribuiamo i rentals nei relativi giorni/veicoli
        foreach ($rentals as $rental) {
            // Se il rental non ha veicolo associato o il veicolo non è nel planner, saltiamo
            if (! $rental->vehicle_id || ! isset($matrix[$rental->vehicle_id])) {
                continue;
            }

            // Otteniamo gli estremi dell'intervallo del rental
            $pickupAt = $rental->planned_pickup_at
                ? Carbon::parse($rental->planned_pickup_at)
                : null;

            $returnAt = $rental->planned_return_at
                ? Carbon::parse($rental->planned_return_at)
                : null;

            // Se manca la data di ritiro, il rental è "sospetto": per ora lo ignoriamo nel planner
            if (! $pickupAt) {
                continue;
            }

            // Se manca la return, consideriamo l'intervallo aperto verso il futuro
            if (! $returnAt) {
                $returnAt = $pickupAt->copy()->addMonth(); // placeholder ampio: tanto poi filtriamo per giorni effettivi
            }

            // Per ogni giorno della settimana corrente, controlliamo overlap
            foreach ($dayRanges as $dateKey => $range) {
                $dayStart = $range['start'];
                $dayEnd   = $range['end'];

                // Condizione di sovrapposizione a livello di GIORNO:
                // il rental "tocca" il giorno se il suo intervallo
                // [pickupAt, returnAt] si sovrappone a [dayStart, dayEnd].
                $overlaps =
                    $pickupAt->lte($dayEnd) &&
                    $returnAt->gte($dayStart);

                if ($overlaps) {
                    $matrix[$rental->vehicle_id][$dateKey][] = $rental;
                }
            }
        }

        return $matrix;
    }
    
    /**
     * Giorni occupati nella vista settimanale, per veicolo.
     *
     * Usa la matrice veicolo×giorno già calcolata in plannerMatrix:
     *
     * [
     *   vehicle_id => [
     *      'YYYY-MM-DD' => true, // se almeno un rental tocca quel giorno
     *      ...
     *   ],
     *   ...
     * ]
     */
    public function getPlannerWeekBusyDaysByVehicleProperty(): array
    {
        $busy = [];
        $matrix = $this->plannerMatrix; // vehicle_id => [date => [rentals...]]

        foreach ($matrix as $vehicleId => $days) {
            foreach ($days as $date => $rentals) {
                if (!empty($rentals)) {
                    $busy[$vehicleId][$date] = true;
                }
            }
        }

        return $busy;
    }

    /**
     * Barre continue per la vista settimanale del planner.
     *
     * Ritorna un array indicizzato per veicolo:
     *
     * [
     *   vehicle_id => [
     *      [
     *          'rental'      => Rental,
     *          'start_index' => 0..6,   // indice colonna di inizio (0 = lunedì)
     *          'end_index'   => 0..6,   // indice colonna di fine
     *          'span'        => 1..7,   // numero di giorni coperti
     *      ],
     *      ...
     *   ],
     *   ...
     * ]
     *
     * Verrà usato SOLO nella vista settimanale per disegnare barre orizzontali.
     */
    public function getPlannerBarsProperty(): array
    {
        $vehicles = $this->plannerVehicles;
        $days     = $this->plannerWeekDays;
        $rentals  = $this->plannerRentals;

        // Se per qualche motivo non abbiamo giorni, niente barre
        if (empty($days)) {
            return [];
        }

        $barsByVehicle = [];

        // Inizializziamo chiavi veicolo
        foreach ($vehicles as $vehicle) {
            $barsByVehicle[$vehicle->id] = [];
        }

        // Mappa data -> indice colonna (0..6)
        $indexByDate = [];
        foreach ($days as $idx => $day) {
            $indexByDate[$day['date']] = $idx;
        }

        // Estremi della settimana (in realtà del range settimanale)
        $weekStart = Carbon::parse($days[0]['date'])->startOfDay();
        $weekEnd   = Carbon::parse($days[count($days) - 1]['date'])->endOfDay();

        // Costruiamo le barre a partire dai rentals filtrati
        foreach ($rentals as $rental) {
            // Deve avere un veicolo e il veicolo deve essere nel planner
            if (! $rental->vehicle_id || ! isset($barsByVehicle[$rental->vehicle_id])) {
                continue;
            }

            // Se manca la data di ritiro, non sappiamo dove piazzare la barra
            if (! $rental->planned_pickup_at) {
                continue;
            }

            $pickupAt = Carbon::parse($rental->planned_pickup_at);

            // Se manca la data di fine, consideriamo il noleggio aperto
            $returnAt = $rental->planned_return_at
                ? Carbon::parse($rental->planned_return_at)
                : $pickupAt->copy()->addYear();

            // Se l'intervallo è completamente fuori dalla settimana, saltiamo
            if ($returnAt->lt($weekStart) || $pickupAt->gt($weekEnd)) {
                continue;
            }

            // "Clippiamo" l'intervallo alla settimana visibile
            $clampedStart = $pickupAt->lt($weekStart) ? $weekStart->copy() : $pickupAt->copy();
            $clampedEnd   = $returnAt->gt($weekEnd)   ? $weekEnd->copy()   : $returnAt->copy();

            $startDateKey = $clampedStart->toDateString();
            $endDateKey   = $clampedEnd->toDateString();

            // Indici colonna: se per qualche motivo non troviamo la data, ripieghiamo su estremi
            $startIndex = $indexByDate[$startDateKey] ?? 0;
            $endIndex   = $indexByDate[$endDateKey]   ?? (count($days) - 1);

            if ($endIndex < $startIndex) {
                [$startIndex, $endIndex] = [$endIndex, $startIndex];
            }

            $span = ($endIndex - $startIndex) + 1;

            $barsByVehicle[$rental->vehicle_id][] = [
                'rental'      => $rental,
                'start_index' => $startIndex,
                'end_index'   => $endIndex,
                'span'        => $span,
            ];
        }

        return $barsByVehicle;
    }
    
    /**
     * Identifica i noleggi in overbooking nel planner.
     *
     * Logica:
     * - Lavora sui noleggi già filtrati da getPlannerRentalsProperty():
     *   - visibilità (restrictToViewer),
     *   - ricerca libera,
     *   - filtro stato planner,
     *   - range temporale settimana/giorno.
     * - Raggruppa i noleggi per veicolo.
     * - Per ogni veicolo ordina i noleggi per planned_pickup_at.
     * - Due noleggi A e B sullo stesso veicolo sono in conflitto se:
     *      A.start < B.end  E  B.start < A.end
     *   (sovrapposizione a livello di data/ora, non solo di giorno).
     *
     * Ritorna un array “piatto” di id di Rental che partecipano
     * ad almeno un conflitto (ognuno una sola volta).
     *
     * In Blade useremo $this->plannerOverbookedRentalIds.
     */
    public function getPlannerOverbookedRentalIdsProperty(): array
    {
        // Noleggi già filtrati per il planner
        $rentals = $this->plannerRentals;

        // Consideriamo solo i noleggi che hanno un veicolo associato
        $grouped = $rentals
            ->filter(fn ($r) => $r->vehicle_id !== null)
            ->groupBy('vehicle_id');

        $overbookedIds = [];

        foreach ($grouped as $vehicleId => $list) {
            // Ordiniamo per orario di ritiro
            $sorted = $list
                ->sortBy(fn ($r) => $r->planned_pickup_at ?? '1970-01-01')
                ->values();

            $count = $sorted->count();

            for ($i = 0; $i < $count; $i++) {
                $a = $sorted[$i];

                // Se manca la data di ritiro, non possiamo valutare correttamente l'intervallo
                if (! $a->planned_pickup_at) {
                    continue;
                }

                $aStart = Carbon::parse($a->planned_pickup_at);

                // Se manca la fine, consideriamo il noleggio aperto a lungo nel futuro
                $aEnd = $a->planned_return_at
                    ? Carbon::parse($a->planned_return_at)
                    : $aStart->copy()->addYear();

                for ($j = $i + 1; $j < $count; $j++) {
                    $b = $sorted[$j];

                    if (! $b->planned_pickup_at) {
                        continue;
                    }

                    $bStart = Carbon::parse($b->planned_pickup_at);
                    $bEnd = $b->planned_return_at
                        ? Carbon::parse($b->planned_return_at)
                        : $bStart->copy()->addYear();

                    // Ottimizzazione: se B inizia dopo/uguale alla fine di A,
                    // i successivi inizieranno ancora più tardi → possiamo interrompere.
                    if ($bStart->gte($aEnd)) {
                        break;
                    }

                    // Condizione di sovrapposizione a livello di data/ora:
                    // intervalli [aStart, aEnd] e [bStart, bEnd] si sovrappongono?
                    $overlaps = $aStart->lt($bEnd) && $bStart->lt($aEnd);

                    if ($overlaps) {
                        $overbookedIds[] = $a->id;
                        $overbookedIds[] = $b->id;
                    }
                }
            }
        }

        // Rimuoviamo duplicati e ritorniamo un array di id “pulito”
        return array_values(array_unique($overbookedIds));
    }

    /**
     * Ore mostrate nella vista giornaliera del planner.
     *
     * Ritorna un array di "slot orari" da 00:00 a 23:00:
     *
     * [
     *   ['index' => 0,  'label' => '00:00'],
     *   ['index' => 1,  'label' => '01:00'],
     *   ...
     *   ['index' => 23, 'label' => '23:00'],
     * ]
     *
     * In Blade useremo $this->plannerDayHours.
     * In uno step successivo useremo questi index per disegnare le barre continue.
     */
    public function getPlannerDayHoursProperty(): array
    {
        $out = [];

        for ($h = 0; $h < 24; $h++) {
            $out[] = [
                'index' => $h,
                'label' => sprintf('%02d:00', $h),
            ];
        }

        return $out;
    }

    /**
     * Barre continue per la vista GIORNALIERA del planner.
     *
     * Struttura identica a plannerBars (settimanale), ma con 24 colonne (ore):
     *
     * [
     *   vehicle_id => [
     *      [
     *          'rental'      => Rental,
     *          'start_index' => 0..23,  // indice colonna ora di inizio
     *          'end_index'   => 0..23,  // indice colonna ora di fine
     *          'span'        => 1..24,  // numero di ore coperte
     *      ],
     *      ...
     *   ],
     *   ...
     * ]
     */
    public function getPlannerDayBarsByVehicleProperty(): array
    {
        $vehicles = $this->plannerVehicles;
        $hours    = $this->plannerDayHours;   // 24 slot
        $rentals  = $this->plannerRentals;

        if (empty($hours)) {
            return [];
        }

        $barsByVehicle = [];

        // Inizializziamo chiavi veicolo
        foreach ($vehicles as $vehicle) {
            $barsByVehicle[$vehicle->id] = [];
        }

        // Giorno corrente del planner
        $dayBase  = Carbon::parse($this->plannerCurrentDate ?? now());
        $dayStart = $dayBase->copy()->startOfDay();
        $dayEnd   = $dayBase->copy()->endOfDay(); // 23:59:59
        $slots    = count($hours); // 24

        foreach ($rentals as $rental) {
            // Deve avere veicolo e veicolo nel planner
            if (! $rental->vehicle_id || ! isset($barsByVehicle[$rental->vehicle_id])) {
                continue;
            }

            if (! $rental->planned_pickup_at) {
                continue;
            }

            $pickupAt = Carbon::parse($rental->planned_pickup_at);
            $returnAt = $rental->planned_return_at
                ? Carbon::parse($rental->planned_return_at)
                : $pickupAt->copy()->addYear();

            // Intervallo completamente fuori dal giorno selezionato → salta
            if ($returnAt->lt($dayStart) || $pickupAt->gt($dayEnd)) {
                continue;
            }

            // Clippiamo all'interno del giorno
            $clampedStart = $pickupAt->lt($dayStart) ? $dayStart->copy() : $pickupAt->copy();
            $clampedEnd   = $returnAt->gt($dayEnd)   ? $dayEnd->copy()   : $returnAt->copy();

            // Minuti dall'inizio del giorno
            $startMinutes = $dayStart->diffInMinutes($clampedStart, false);
            $endMinutes   = $dayStart->diffInMinutes($clampedEnd, false);

            if ($endMinutes <= 0) {
                continue;
            }

            if ($startMinutes < 0) {
                $startMinutes = 0;
            }

            // Convertiamo in indici di "slot orari"
            // slot = floor(minuti/60), manteniamo fine inclusivo
            $startIndex = intdiv($startMinutes, 60);
            $endIndex   = intdiv(max($endMinutes - 1, 0), 60);

            // Clamp agli estremi [0, slots-1]
            if ($startIndex >= $slots) {
                continue;
            }

            $startIndex = max(0, min($slots - 1, $startIndex));
            $endIndex   = max($startIndex, min($slots - 1, $endIndex));

            $span = ($endIndex - $startIndex) + 1;

            $barsByVehicle[$rental->vehicle_id][] = [
                'rental'      => $rental,
                'start_index' => $startIndex,
                'end_index'   => $endIndex,
                'span'        => $span,
            ];
        }

        return $barsByVehicle;
    }

    /**
     * Slot orari occupati nella vista giornaliera, per veicolo.
     *
     * Ritorna:
     * [
     *   vehicle_id => [
     *      0 => bool, // ora 00:00–01:00 occupata?
     *      1 => bool, // 01:00–02:00
     *      ...
     *      23 => bool,
     *   ],
     *   ...
     * ]
     */
    public function getPlannerDayBusySlotsByVehicleProperty(): array
    {
        $busy = [];

        if (!$this->plannerRentals || $this->plannerRentals->isEmpty()) {
            return [];
        }

        // Giorno corrente del planner
        $dayBase  = Carbon::parse($this->plannerCurrentDate ?? now());
        $dayStart = $dayBase->copy()->startOfDay();
        $dayEnd   = $dayStart->copy()->addDay(); // [dayStart, dayEnd)

        foreach ($this->plannerRentals as $rental) {
            if (!$rental->vehicle_id) {
                continue;
            }

            if (!$rental->planned_pickup_at || !$rental->planned_return_at) {
                continue;
            }

            $start = $rental->planned_pickup_at instanceof Carbon
                ? $rental->planned_pickup_at->copy()
                : Carbon::parse($rental->planned_pickup_at);

            $end = $rental->planned_return_at instanceof Carbon
                ? $rental->planned_return_at->copy()
                : Carbon::parse($rental->planned_return_at);

            // intervallo non valido
            if ($end <= $start) {
                continue;
            }

            // nessuna sovrapposizione col giorno
            if ($end <= $dayStart || $start >= $dayEnd) {
                continue;
            }

            // tronchiamo agli estremi del giorno
            $segmentStart = $start->lessThan($dayStart) ? $dayStart : $start;
            $segmentEnd   = $end->greaterThan($dayEnd)  ? $dayEnd   : $end;

            if ($segmentEnd <= $segmentStart) {
                continue;
            }

            // minuti dall'inizio giornata
            $startMin = $dayStart->diffInMinutes($segmentStart, false);
            $endMin   = $dayStart->diffInMinutes($segmentEnd, false); // esclusivo

            // clamp
            $startMin = max(0, min(24 * 60, $startMin));
            $endMin   = max(0, min(24 * 60, $endMin));

            if ($endMin <= $startMin) {
                continue;
            }

            // quali ore tocca? [startMin, endMin) vs blocchi [h*60, (h+1)*60)
            $fromHour = intdiv($startMin, 60);
            $lastMinute = $endMin - 1;
            $toHour   = intdiv(max(0, $lastMinute), 60);

            $fromHour = max(0, min(23, $fromHour));
            $toHour   = max(0, min(23, $toHour));

            $vehicleId = $rental->vehicle_id;

            if (!isset($busy[$vehicleId])) {
                $busy[$vehicleId] = array_fill(0, 24, false);
            }

            for ($h = $fromHour; $h <= $toHour; $h++) {
                $busy[$vehicleId][$h] = true;
            }
        }

        return $busy;
    }

    /**
     * Apre il wizard di creazione noleggio partendo da uno slot vuoto
     * del planner (settimanale o giornaliero).
     *
     * $dateTime può essere:
     *  - 'YYYY-MM-DD'              (vista settimana)
     *  - 'YYYY-MM-DD HH:MM'       (vista giorno)
     */
    public function createRentalFromSlot(int $vehicleId, string $date, ?string $timeLabel = null)
    {
        $startDate = Carbon::parse($date)->toDateString();

        $params = [
            'vehicle_id'          => $vehicleId,
            'planned_pickup_date' => $startDate,
        ];

        // Se siamo in vista GIORNO e ci è arrivato un orario, aggiungiamolo
        if ($this->plannerMode === 'day' && $timeLabel) {
            $params['planned_pickup_time'] = $timeLabel; // es. "19:00"
        }

        return redirect()->route('rentals.create', $params);
    }

    public function render(): View
    {
        $query = $this->baseRowsQuery();

        // ⚠️ Per ora, finché non implementiamo il planner,
        // 'planner' viene trattato come 'kanban' sul lato dati:
        // - 'table' => paginazione
        // - altro   => max 200 record
        $rows = $this->view === 'table'
            ? $query->paginate(15)
            : $query->limit(200)->get();

        return view('livewire.rentals.board', [
            'rows' => $rows,
            'kpis' => $this->kpis,
        ]);
    }
}
