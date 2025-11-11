<?php

namespace App\Livewire\Vehicles;

use App\Models\Vehicle;
use App\Models\VehicleState;
use App\Models\VehicleMileageLog;
use App\Models\VehicleDamage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Validation\Rule;

/**
 * Livewire: Vehicles\Show
 *
 * Pagina dettaglio veicolo con tab: profilo, documenti, listino, stato tecnico, assegnazioni, note, danni.
 * - Permessi granulari: updateMileage, manageMaintenance, restore.
 * - Danni: usa VehicleDamagePolicy (viewAny/view/create/update/close/reopen/delete).
 */
class Show extends Component
{
    use AuthorizesRequests;

    /** ID veicolo passato dalla Blade di pagina */
    public int $vehicleId;

    /** Tab attiva (sincronizzata su query string per deep-link) */
    #[Url(as: 'tab', history: true, except: 'profile')]
    public string $tab = 'profile'; // profile|photos|documents|pricing|maintenance|assignments|notes|damages

    /** Filtri locali della tab Documenti */
    #[Url(as: 'doc_state')]
    public ?string $docState = null; // expired|soon|ok|null

    #[Url(as: 'doc_type')]
    public ?string $docType = null;  // insurance|road_tax|inspection|registration|green_card|ztl_permit|other|null

    /** Model corrente (per comodità nel template) */
    public ?Vehicle $vehicle = null;

    // Dati input per aprire/chiudere manutenzione
    public ?string $maintWorkshop = null;      // richiesto all'apertura
    public ?string $maintNotes = null;         // opzionale (apertura/chiusura)
    public ?float  $maintCloseCostEur = null;  // richiesto alla chiusura

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

    /** Whitelist dei tipi documento consentiti */
    protected array $allowedDocTypes = [
        'insurance', 'road_tax', 'inspection', 'registration', 'green_card', 'ztl_permit', 'other',
    ];

    /* ============================================================
     |  DANNI – stato/filtri/sort/kpi
     * ============================================================ */

    // Filtri (sincronizzabili se serve)
    #[Url(as: 'dg_stat')]
    public string $damageStatus = 'open'; // all|open|closed

    #[Url(as: 'dg_src')]
    public ?string $damageSource = null; // manual|inspection|service|rental|null

    #[Url(as: 'dg_sev')]
    public ?string $damageSeverity = null; // low|medium|high|null

    #[Url(as: 'dg_q')]
    public ?string $damageSearch = null; // testo libero area/description/notes

    #[Url(as: 'dg_from')]
    public ?string $damageFromDate = null; // Y-m-d

    #[Url(as: 'dg_to')]
    public ?string $damageToDate = null;   // Y-m-d

    #[Url(as: 'dg_sort')]
    public string $damageSort = 'default'; // vedi $allowedDamageSorts

    #[Url(as: 'dg_pp')]
    public int $damagePerPage = 20;

    public ?int $expandedDamageId = null;

    /** Enum aree danno consentite */
    private const DAMAGE_AREAS = [
        'front','rear','left','right','interior','roof','windshield','wheel','other'
    ];
    /** Enum severità consentite */
    private const DAMAGE_SEVERITIES = ['low','medium','high'];

    /** Whitelist campi danni */
    protected array $allowedDamageSources = ['manual','inspection','service','rental'];
    protected array $allowedDamageSeverities = self::DAMAGE_SEVERITIES;
    protected array $allowedDamageSorts = [
        'default',
        'opened_desc', 'opened_asc',
        'closed_desc', 'closed_asc',
        'cost_desc', 'cost_asc',
        'severity_desc', 'severity_asc',
        'origin_asc', 'origin_desc',
    ];

    // KPI calcolati in render()
    public int $damageOpenCount = 0;
    public int $damageTotalCount = 0;
    public float $damageCost12m = 0.0; // somma repair_cost chiusi negli ultimi 12 mesi (EUR)

    /** Etichette IT per le aree (usate per render e select) */
    public array $damageAreaLabels = [
        'front'      => 'Anteriore',
        'rear'       => 'Posteriore',
        'left'       => 'Sinistra',
        'right'      => 'Destra',
        'interior'   => 'Interno',
        'roof'       => 'Tetto',
        'windshield' => 'Parabrezza',
        'wheel'      => 'Ruota',
        'other'      => 'Altro',
    ];

    /** Stato form “Nuovo danno” (solo non-rental) */
    public array $newDamage = [
        'source'      => 'manual',  // manual|inspection|service
        'area'        => null,      // enum self::DAMAGE_AREAS
        'severity'    => null,      // low|medium|high
        'description' => null,      // testo libero
    ];

    // --- MODALE: CHIUDI DANNO ---
    public bool $isCloseDamageModalOpen = false;
    public ?int   $damageIdClosing      = null;
    public ?float $damageCloseCostEur   = null;
    public ?string $damageCloseNotes    = null;

    // --- MODALE: MODIFICA DANNO (solo source != rental) ---
    public bool $isEditDamageModalOpen = false;
    public ?int $damageIdEditing       = null;
    public ?string $editSeverity       = null;   // low|medium|high
    public ?string $editDescription    = null;

    /** Sidebar foto danno (solo visualizzazione per ora) */
    public bool $isDamagePhotosSidebarOpen = false;
    public ?int $damageIdViewing = null;
    public ?string $viewingDamageSource = null; // 'rental' | 'manual' | 'inspection' | 'service'
    public array $damagePhotos = [];            // array normalizzato per la view

    /**
     * Mount: accetta ID esplicito o un model dal controller.
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

        // Sanitize filtro Documenti
        if ($this->docType && !in_array($this->docType, $this->allowedDocTypes, true)) {
            $this->docType = null;
        }

        // Garantisce la shape del form Nuovo Danno (evita Undefined array key 'source')
        $this->ensureNewDamageShape();

        // Sanitize iniziale filtri danni
        $this->sanitizeDamageFilters();
    }

    /* ============================ HELPER ============================ */

    /** Garantisce che $newDamage abbia sempre tutte le chiavi richieste. */
    private function ensureNewDamageShape(): void
    {
        $defaults = [
            'source'      => 'manual',
            'area'        => null,
            'severity'    => null,
            'description' => null,
        ];
        $this->newDamage = array_merge($defaults, is_array($this->newDamage) ? $this->newDamage : []);
        // harden: normalizza i valori fuori whitelist
        if (!in_array($this->newDamage['source'] ?? null, ['manual','inspection','service'], true)) {
            $this->newDamage['source'] = 'manual';
        }
        if ($this->newDamage['area'] !== null && !in_array($this->newDamage['area'], self::DAMAGE_AREAS, true)) {
            $this->newDamage['area'] = null;
        }
        if ($this->newDamage['severity'] !== null && !in_array($this->newDamage['severity'], self::DAMAGE_SEVERITIES, true)) {
            $this->newDamage['severity'] = null;
        }
        if (!is_null($this->newDamage['description'])) {
            $this->newDamage['description'] = (string) $this->newDamage['description'];
        }
    }

    /** Normalizza importo in formato IT/EN → float. */
    private function normalizeEuro(null|int|float|string $value): float
    {
        if (is_string($value)) {
            $s = trim($value);
            $s = str_replace(['.', ' '], '', $s); // rimuove separatori migliaia
            $s = str_replace(',', '.', $s);       // converte la virgola in punto
            return (float) $s;
        }
        return (float) $value;
    }

    /* ----------------- Hooks di sanitizzazione filtri danni ----------------- */

    public function updatedDamageStatus(): void
    {
        if (!in_array($this->damageStatus, ['all','open','closed'], true)) {
            $this->damageStatus = 'open';
        }
    }

    public function updatedDamageSource($v): void
    {
        if ($v === '' || $v === null) { $this->damageSource = null; return; }
        if (!in_array($v, $this->allowedDamageSources, true)) {
            $this->damageSource = null;
        }
    }

    public function updatedDamageSeverity($v): void
    {
        if ($v === '' || $v === null) { $this->damageSeverity = null; return; }
        if (!in_array($v, $this->allowedDamageSeverities, true)) {
            $this->damageSeverity = null;
        }
    }

    public function updatedDamageSort(): void
    {
        if (!in_array($this->damageSort, $this->allowedDamageSorts, true)) {
            $this->damageSort = 'default';
        }
    }

    public function resetDamageFilters(): void
    {
        $this->damageStatus   = 'open';
        $this->damageSource   = null;
        $this->damageSeverity = null;
        $this->damageSearch   = null;
        $this->damageFromDate = null;
        $this->damageToDate   = null;
        $this->damageSort     = 'default';
        $this->damagePerPage  = 20;
    }

    protected function sanitizeDamageFilters(): void
    {
        $this->updatedDamageStatus();
        $this->updatedDamageSource($this->damageSource);
        $this->updatedDamageSeverity($this->damageSeverity);
        $this->updatedDamageSort();

        if ($this->damagePerPage < 5 || $this->damagePerPage > 200) {
            $this->damagePerPage = 20;
        }

        foreach (['damageFromDate','damageToDate'] as $prop) {
            $v = $this->{$prop};
            if ($v === '' || $v === null) { $this->{$prop} = null; continue; }
            try {
                Carbon::createFromFormat('Y-m-d', $v);
            } catch (\Throwable $e) {
                $this->{$prop} = null;
            }
        }
    }

    /* ===================== Hooks Livewire utili ===================== */

    /** Re-harden ad ogni ciclo per evitare shape rotte dopo update parziali. */
    public function hydrate(): void
    {
        $this->ensureNewDamageShape();
    }

    /* ===================== Blocchi esistenti (tuo codice) ===================== */

    public function updatedDocType($value): void
    {
        if ($value === '' || $value === null) { $this->docType = null; return; }
        if (!in_array($value, $this->allowedDocTypes, true)) {
            $this->docType = null;
        }
    }

    protected function loadVehicle(): Vehicle
    {
        $now = Carbon::now();

        /** @var Vehicle $v */
        $v = Vehicle::withTrashed()
            ->with([
                'adminOrganization:id,name',
                'defaultPickupLocation:id,name',
                'documents'    => fn ($q) => $q->orderBy('expiry_date'),
                'states'       => fn ($q) => $q->orderByDesc('started_at')->with('maintenanceDetail'),
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

        $this->authorize('view', $v);

        return $v;
    }

    public function updateMileage(int $mileage): void
    {
        $v = Vehicle::withTrashed()->findOrFail($this->vehicleId);
        $this->authorize('updateMileage', $v);

        if ($v->trashed()) {
            $this->addError('mileage', 'Veicolo archiviato: ripristina prima di aggiornare i km.');
            return;
        }

        $old = (int) $v->mileage_current;

        if ($mileage < $old) {
            $this->addError('mileage', 'Il chilometraggio non può diminuire.');
            return;
        }

        $v->mileage_current = $mileage;
        $v->save();

        VehicleMileageLog::create([
            'vehicle_id'  => $v->id,
            'mileage_old' => $old,
            'mileage_new' => (int) $mileage,
            'changed_by'  => auth()->id(),
            'source'      => 'manual',
            'notes'       => 'Aggiornamento dalla pagina veicolo',
            'changed_at'  => now(),
        ]);

        $this->dispatch('toast', [
            'type'    => 'success',
            'message' => 'Chilometraggio aggiornato.',
        ]);
    }

    public function setMaintenance(): void
    {
        $v = Vehicle::withTrashed()->findOrFail($this->vehicleId);
        $this->authorize('manageMaintenance', $v);

        if ($v->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Veicolo archiviato: ripristina prima di modificare lo stato.');
            return;
        }

        $this->validate([
            'maintWorkshop' => ['required','string','max:128'],
            'maintNotes'    => ['nullable','string'],
        ], [], [
            'maintWorkshop' => 'Luogo/officina',
            'maintNotes'    => 'Note',
        ]);

        $exists = VehicleState::where('vehicle_id', $v->id)
            ->whereIn('state', ['maintenance','out_of_service'])
            ->whereNull('ended_at')
            ->exists();

        if ($exists) {
            $this->dispatch('toast', type: 'info', message: 'Esiste già uno stato tecnico aperto.');
            return;
        }

        $state = VehicleState::create([
            'vehicle_id' => $v->id,
            'state'      => 'maintenance',
            'started_at' => now(),
            'ended_at'   => null,
            'reason'     => 'Impostato dalla pagina veicolo',
            'created_by' => auth()->id(),
        ]);

        $state->maintenanceDetail()->create([
            'workshop'   => trim($this->maintWorkshop),
            'cost_cents' => null,
            'currency'   => 'EUR',
            'notes'      => $this->maintNotes ? trim($this->maintNotes) : null,
        ]);

        $v->is_active = false;
        $v->save();

        $this->maintWorkshop = null;
        $this->maintNotes = null;

        $this->dispatch('toast', type: 'success', message: 'Manutenzione aperta.');
    }

    public function clearMaintenance(): void
    {
        $v = Vehicle::withTrashed()->findOrFail($this->vehicleId);
        $this->authorize('manageMaintenance', $v);

        if ($v->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Veicolo archiviato: ripristina prima di modificare lo stato.');
            return;
        }

        $state = VehicleState::where('vehicle_id', $v->id)
            ->where('state', 'maintenance')
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if (!$state) {
            $this->dispatch('toast', type: 'info', message: 'Nessuna manutenzione aperta da chiudere.');
            return;
        }

        $this->validate([
            'maintCloseCostEur' => ['required','numeric','min:0'],
            'maintNotes'        => ['nullable','string'],
        ], [], [
            'maintCloseCostEur' => 'Costo manutenzione (€)',
            'maintNotes'        => 'Note',
        ]);

        $state->update(['ended_at' => now()]);

        $prevNotes   = optional($state->maintenanceDetail)->notes;
        $newNotes    = trim((string) $this->maintNotes);
        $mergedNotes = $prevNotes;

        if ($newNotes !== '') {
            $mergedNotes = $prevNotes ? ($prevNotes . "\n— " . $newNotes) : $newNotes;
        }

        $state->maintenanceDetail()->updateOrCreate(
            ['vehicle_state_id' => $state->id],
            [
                'cost_cents' => (int) round(((float) $this->maintCloseCostEur) * 100),
                'notes'      => $mergedNotes,
                'currency'   => 'EUR',
            ]
        );

        $v->is_active = true;
        $v->save();

        $this->maintCloseCostEur = null;
        $this->maintNotes = null;

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
        $allowed = ['profile','photos','documents','pricing','maintenance','damages','assignments','notes'];
        if (in_array($tab, $allowed, true)) {
            $this->tab = $tab;
        }
    }

    /* ======================= AZIONI DANNI ======================= */

    /** Watchers del form “Nuovo danno” per robustezza. */
    public function updatedNewDamage($value, $key): void
    {
        // $key è tipo "source" | "area" | "severity" | "description"
        if ($key === 'source' && !in_array($value, ['manual','inspection','service'], true)) {
            $this->newDamage['source'] = 'manual';
        }
        if ($key === 'area' && $value !== null && !in_array($value, self::DAMAGE_AREAS, true)) {
            $this->newDamage['area'] = null;
        }
        if ($key === 'severity' && $value !== null && !in_array($value, self::DAMAGE_SEVERITIES, true)) {
            $this->newDamage['severity'] = null;
        }
        if ($key === 'description' && !is_null($value)) {
            $this->newDamage['description'] = (string) $value;
        }
        // ricompatta sempre la shape
        $this->ensureNewDamageShape();
    }

    /**
     * Crea un nuovo danno (manual/inspection/service). Nessun danno rental da qui.
     */
    public function createDamage(): void
    {
        $v = Vehicle::withTrashed()->findOrFail($this->vehicleId);
        $this->authorize('vehicle_damages.create', $v);

        if ($v->trashed()) {
            $this->dispatch('toast', type:'warning', message:'Veicolo archiviato: ripristina prima di aggiungere danni.');
            return;
        }

        // Validazione
        $this->validate([
            'newDamage.source'      => ['required','in:manual,inspection,service'],
            'newDamage.area'        => ['nullable','in:'.implode(',', self::DAMAGE_AREAS)],
            'newDamage.severity'    => ['nullable','in:'.implode(',', self::DAMAGE_SEVERITIES)],
            'newDamage.description' => ['nullable','string','max:1000'],
        ], [], [
            'newDamage.source'      => 'Origine',
            'newDamage.area'        => 'Area',
            'newDamage.severity'    => 'Severità',
            'newDamage.description' => 'Descrizione',
        ]);

        // Creazione (aperto di default)
        VehicleDamage::create([
            'vehicle_id' => $v->id,
            'source'     => $this->newDamage['source'],
            'area'       => $this->newDamage['area'] ?: null,
            'severity'   => $this->newDamage['severity'] ?: null,
            'description'=> $this->newDamage['description'] ? trim($this->newDamage['description']) : null,
            'is_open'    => true,
            'created_by' => auth()->id(),
        ]);

        // reset form (sicuro)
        $this->newDamage = [
            'source'      => 'manual',
            'area'        => null,
            'severity'    => null,
            'description' => null,
        ];

        $this->dispatch('toast', type:'success', message:'Danno aggiunto.');
    }

    /**
     * Aggiorna un danno non-rental (solo se aperto). Campo note viene APPESO se passato.
     */
    public function updateDamage(int $damageId, string $area, string $severity, string $description, ?string $appendNote = null): void
    {
        $damage = VehicleDamage::forVehicle($this->vehicleId)->findOrFail($damageId);
        $this->authorize('update', $damage);

        if ($damage->source === 'rental') {
            $this->dispatch('toast', type: 'warning', message: 'Impossibile modificare un danno da rental.');
            return;
        }
        if (!$damage->is_open) {
            $this->dispatch('toast', type: 'warning', message: 'Modifica non consentita su un danno chiuso.');
            return;
        }

        $validated = validator([
            'area'        => $area,
            'severity'    => $severity,
            'description' => $description,
            'append_note' => $appendNote,
        ], [
            'area'        => ['required','string','max:255'],
            'severity'    => ['required', Rule::in($this->allowedDamageSeverities)],
            'description' => ['required','string','max:1000'],
            'append_note' => ['nullable','string','max:2000'],
        ])->validate();

        $damage->area        = trim($validated['area']);
        $damage->severity    = $validated['severity'];
        $damage->description = trim($validated['description']);

        if (!empty($validated['append_note'])) {
            $damage->notes = $damage->notes
                ? ($damage->notes . "\n— " . trim($validated['append_note']))
                : trim($validated['append_note']);
        }

        $damage->save();

        $this->dispatch('toast', type: 'success', message: 'Danno aggiornato.');
    }

    /**
     * Riapre un danno (confirm lato UI). Mantiene fixed_at/repair_cost per storico.
     */
    public function reopenDamage(int $damageId, ?string $appendNote = null): void
    {
        $damage = VehicleDamage::forVehicle($this->vehicleId)->findOrFail($damageId);
        $this->authorize('reopen', $damage);

        if ($damage->is_open) {
            $this->dispatch('toast', type: 'info', message: 'Il danno è già aperto.');
            return;
        }

        $damage->reopen();

        if ($appendNote !== null && trim($appendNote) !== '') {
            $damage->notes = $damage->notes
                ? ($damage->notes . "\n— " . trim($appendNote))
                : trim($appendNote);
            $damage->save();
        }

        $this->dispatch('toast', type: 'success', message: 'Danno riaperto.');
    }

    /**
     * Elimina un danno (confirm lato UI).
     */
    public function deleteDamage(int $damageId): void
    {
        $damage = VehicleDamage::forVehicle($this->vehicleId)->findOrFail($damageId);
        $this->authorize('delete', $damage);

        $damage->delete();

        $this->dispatch('toast', type: 'success', message: 'Danno eliminato.');
    }

    /**
     * Appende una nota a un danno (consentito se policy update lo permette).
     */
    public function appendDamageNote(int $damageId, string $note): void
    {
        $damage = VehicleDamage::forVehicle($this->vehicleId)->findOrFail($damageId);
        $this->authorize('update', $damage);

        $note = trim($note);
        if ($note === '') {
            $this->dispatch('toast', type: 'warning', message: 'Nota vuota.');
            return;
        }

        $damage->notes = $damage->notes ? ($damage->notes . "\n— " . $note) : $note;
        $damage->save();

        $this->dispatch('toast', type: 'success', message: 'Nota aggiunta.');
    }

    /** Espande/contrae una riga danno per dettagli */
    public function toggleDamageRow(int $id): void
    {
        $this->expandedDamageId = $this->expandedDamageId === $id ? null : $id;
    }

    /** Apre la modale di chiusura danno, precompilando i campi */
    public function openCloseDamageModal(int $damageId): void
    {
        $damage = VehicleDamage::forVehicle($this->vehicleId)->findOrFail($damageId);
        $this->authorize('close', $damage);

        if (!$damage->is_open) {
            $this->dispatch('toast', type: 'info', message: 'Questo danno è già chiuso.');
            return;
        }

        $this->damageIdClosing        = $damage->id;
        $this->damageCloseCostEur     = $damage->repair_cost !== null ? (float)$damage->repair_cost : null;
        $this->damageCloseNotes       = null;
        $this->isCloseDamageModalOpen = true;
    }

    /** Esegue la chiusura danno dalla modale */
    public function performCloseDamage(): void
    {
        $this->validate([
            'damageIdClosing'    => ['required','integer'],
            'damageCloseCostEur' => ['required','numeric','min:0','max:999999.99'],
            'damageCloseNotes'   => ['nullable','string','max:2000'],
        ], [], [
            'damageCloseCostEur' => 'Costo riparazione (€)',
            'damageCloseNotes'   => 'Note',
        ]);

        $damage = VehicleDamage::forVehicle($this->vehicleId)->findOrFail((int)$this->damageIdClosing);
        $this->authorize('close', $damage);

        if (!$damage->is_open) {
            $this->dispatch('toast', type:'info', message:'Il danno risulta già chiuso.');
            $this->isCloseDamageModalOpen = false;
            return;
        }

        $cost   = round($this->normalizeEuro($this->damageCloseCostEur), 2);
        $prev   = (string)($damage->notes ?? '');
        $new    = trim((string)$this->damageCloseNotes);
        $merged = $prev !== '' && $new !== '' ? ($prev . "\n— " . $new) : ($new !== '' ? $new : $prev);

        $damage->fill([
            'is_open'          => false,
            'fixed_at'         => now(),
            'fixed_by_user_id' => auth()->id(),
            'repair_cost'      => $cost,
            'notes'            => $merged,
        ])->save();

        $this->reset(['isCloseDamageModalOpen','damageIdClosing','damageCloseCostEur','damageCloseNotes']);

        $this->dispatch('toast', type:'success', message:'Danno chiuso con successo.');
    }

    /** Apre la modale di modifica danno (solo non-rental), precompilando i campi */
    public function openEditDamageModal(int $damageId): void
    {
        $damage = VehicleDamage::forVehicle($this->vehicleId)->findOrFail($damageId);

        if ($damage->source === 'rental') {
            $this->dispatch('toast', type:'warning', message:'I danni da noleggio si modificano dalla checklist del noleggio.');
            return;
        }

        $this->authorize('update', $damage);

        $this->damageIdEditing       = $damage->id;
        $this->editSeverity          = $damage->severity ?: 'medium';
        $this->editDescription       = $damage->description ?? '';
        $this->isEditDamageModalOpen = true;
    }

    /** Esegue la modifica danno dalla modale */
    public function performEditDamage(): void
    {
        $this->validate([
            'damageIdEditing' => ['required','integer'],
            'editSeverity'    => ['required','in:low,medium,high'],
            'editDescription' => ['required','string','max:1000'],
        ], [], [
            'editSeverity'    => 'Severità',
            'editDescription' => 'Descrizione',
        ]);

        $damage = VehicleDamage::forVehicle($this->vehicleId)->find($this->damageIdEditing);
        if (!$damage) {
            $this->addError('damageIdEditing', 'Danno non trovato.');
            return;
        }

        if ($damage->source === 'rental') {
            $this->dispatch('toast', type:'warning', message:'I danni da noleggio non sono modificabili qui.');
            $this->isEditDamageModalOpen = false;
            return;
        }

        $this->authorize('update', $damage);

        $damage->update([
            'severity'    => $this->editSeverity,
            'description' => trim((string)$this->editDescription),
        ]);

        $this->reset(['isEditDamageModalOpen','damageIdEditing','editSeverity','editDescription']);

        $this->dispatch('toast', type:'success', message:'Danno aggiornato.');
    }

    /**
     * Apre la sidebar foto e raccoglie immagini da:
     *  - RentalDamage (collection 'photos') se il danno proviene da noleggio
     *  - Vehicle (collection 'vehicle_damage_photos') filtrando per custom_property damage_id
     */
    public function openDamagePhotosSidebar(int $damageId): void
    {
        $damage = VehicleDamage::with([
            'vehicle',
            'firstRentalDamage.media',
            'lastRentalDamage.media',
        ])->forVehicle($this->vehicleId)->findOrFail($damageId);

        $this->authorize('view', $damage);

        $this->damageIdViewing           = $damage->id;
        $this->viewingDamageSource       = $damage->source;
        $this->isDamagePhotosSidebarOpen = true;

        $photos = [];

        // 1) Foto da rental (prima + ultima occorrenza collegate)
        if ($damage->source === 'rental') {
            if ($damage->firstRentalDamage) {
                foreach ($damage->firstRentalDamage->getMedia('photos') as $m) {
                    $photos[] = $this->mapMediaForView($m, 'rental_damage');
                }
            }
            if ($damage->lastRentalDamage && $damage->lastRentalDamage->id !== optional($damage->firstRentalDamage)->id) {
                foreach ($damage->lastRentalDamage->getMedia('photos') as $m) {
                    $photos[] = $this->mapMediaForView($m, 'rental_damage');
                }
            }
        }

        // 2) Foto “manuali” collegate al veicolo e riferite a questo damage_id
        if ($damage->vehicle) {
            foreach ($damage->vehicle->getMedia('vehicle_damage_photos') as $m) {
                if ((int) $m->getCustomProperty('damage_id') === (int) $damage->id) {
                    $photos[] = $this->mapMediaForView($m, 'vehicle_damage');
                }
            }
        }

        // Ordina per data desc
        usort($photos, fn ($a, $b) => strcmp($b['created_at_iso'] ?? '', $a['created_at_iso'] ?? ''));

        $this->damagePhotos = $photos;
    }

    /** Chiude la sidebar e pulisce lo stato */
    public function closeDamagePhotosSidebar(): void
    {
        $this->isDamagePhotosSidebarOpen = false;
        $this->damageIdViewing = null;
        $this->viewingDamageSource = null;
        $this->damagePhotos = [];
    }

    /** Normalizza un Media Spatie per la view */
    private function mapMediaForView(Media $m, string $origin): array
    {
        $thumb   = $m->hasGeneratedConversion('thumb')   ? $m->getUrl('thumb')   : $m->getUrl();
        $preview = $m->hasGeneratedConversion('preview') ? $m->getUrl('preview') : $m->getUrl();

        return [
            'id'             => $m->id,
            'thumb'          => $thumb,
            'url'            => $preview,
            'file_name'      => $m->file_name,
            'origin'         => $origin, // 'rental_damage' | 'vehicle_damage'
            'created_at'     => optional($m->created_at)->format('d/m/Y H:i'),
            'created_at_iso' => optional($m->created_at)?->toISOString(),
        ];
    }

    /* =============================== Render =============================== */

    public function render()
    {
        // Carica veicolo + autorizzazione 'view'
        $v = $this->loadVehicle();
        $this->vehicle = $v;

        // Harden ogni ciclo per evitare chiavi mancanti in Blade
        $this->ensureNewDamageShape();

        // Flag per badge header
        $isArchived    = method_exists($v, 'trashed') && $v->trashed();
        $isAssigned    = (bool) $v->is_assigned;
        $isMaintenance = (bool) $v->is_maintenance;

        // Prossima scadenza
        $nextDays = null;
        if ($v->next_expiry_date) {
            $next = Carbon::parse($v->next_expiry_date)->startOfDay();
            $nextDays = now()->startOfDay()->diffInDays($next, false);
        }

        // Documenti: contatori
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

        /* ----------------------- DANNI: KPI + elenco ----------------------- */

        $canViewDamages = auth()->user()?->can('vehicle_damages.viewAny') ?? false;

        $damages = collect();
        if ($canViewDamages) {
            // KPI
            $this->damageOpenCount  = VehicleDamage::forVehicle($v->id)->where('is_open', true)->count();
            $this->damageTotalCount = VehicleDamage::forVehicle($v->id)->count();
            $this->damageCost12m    = (float) VehicleDamage::forVehicle($v->id)
                ->where('is_open', false)
                ->whereBetween('fixed_at', [now()->subYear(), now()])
                ->sum('repair_cost');

            // Elenco con filtri
            $q = VehicleDamage::query()
                ->where('vehicle_id', $v->id)
                ->with([
                    'firstRentalDamage:id,rental_id,area,severity,description',
                    'lastRentalDamage:id,rental_id',
                    'creator:id,name',
                ])
                ->orderByDesc('id');

            // stato
            if ($this->damageStatus === 'open')   { $q->where('is_open', true); }
            if ($this->damageStatus === 'closed') { $q->where('is_open', false); }

            // source
            if ($this->damageSource) { $q->where('source', $this->damageSource); }

            // severity: sia su VehicleDamage.severity che su firstRentalDamage.severity
            if ($this->damageSeverity) {
                $sev = $this->damageSeverity;
                $q->where(function ($w) use ($sev) {
                    $w->where('severity', $sev)
                      ->orWhereHas('firstRentalDamage', fn($r) => $r->where('severity', $sev));
                });
            }

            // date range
            $from = $this->damageFromDate ? Carbon::createFromFormat('Y-m-d', $this->damageFromDate)->startOfDay() : null;
            $to   = $this->damageToDate   ? Carbon::createFromFormat('Y-m-d', $this->damageToDate)->endOfDay()   : null;

            if ($from || $to) {
                if ($this->damageStatus === 'closed') {
                    if ($from) { $q->where('fixed_at', '>=', $from); }
                    if ($to)   { $q->where('fixed_at', '<=', $to); }
                } else {
                    if ($from) { $q->where('created_at', '>=', $from); }
                    if ($to)   { $q->where('created_at', '<=', $to); }
                }
            }

            // search (area/description/notes)
            if ($this->damageSearch) {
                $s = '%' . str_replace(['%','_'], ['\%','\_'], trim($this->damageSearch)) . '%';
                $q->where(function ($w) use ($s) {
                    $w->where('area', 'like', $s)
                      ->orWhere('description', 'like', $s)
                      ->orWhere('notes', 'like', $s);
                });
            }

            // ordinamenti
            switch ($this->damageSort) {
                case 'opened_asc':  $q->orderBy('created_at', 'asc'); break;
                case 'opened_desc': $q->orderBy('created_at', 'desc'); break;
                case 'closed_asc':  $q->orderBy('fixed_at', 'asc'); break;
                case 'closed_desc': $q->orderBy('fixed_at', 'desc'); break;
                case 'cost_asc':    $q->orderBy('repair_cost', 'asc'); break;
                case 'cost_desc':   $q->orderBy('repair_cost', 'desc'); break;
                case 'severity_asc':
                    $q->orderByRaw("FIELD(severity,'low','medium','high') ASC NULLS LAST");
                    break;
                case 'severity_desc':
                    $q->orderByRaw("FIELD(severity,'low','medium','high') DESC NULLS LAST");
                    break;
                case 'origin_asc':  $q->orderBy('source', 'asc'); break;
                case 'origin_desc': $q->orderBy('source', 'desc'); break;
                default:
                    // default: aperti prima, poi per created_at desc
                    $q->orderBy('is_open', 'desc')->orderBy('created_at', 'desc');
            }

            // restituisce tutti; per paginare: ->paginate($this->damagePerPage)
            $damages = $q->get();
        }

        return view('livewire.vehicles.show', [
            'v'                => $v,
            'isArchived'       => $isArchived,
            'isAssigned'       => $isAssigned,
            'isMaintenance'    => $isMaintenance,
            'nextDays'         => $nextDays,
            'docExpired'       => $docExpired,
            'docSoon'          => $docSoon,
            'docsFiltered'     => $docsFiltered,
            'assignedNow'      => $assignedNow,
            'docLabels'        => $this->docLabels,

            // Danni (per la view)
            'canViewDamages'   => $canViewDamages,
            'damages'          => $damages,
            'damageOpenCount'  => $this->damageOpenCount,
            'damageTotalCount' => $this->damageTotalCount,
            'damageCost12m'    => $this->damageCost12m,
            'areaLabels'       => $this->damageAreaLabels,
            'areaOptions'      => array_map(
                fn($k)=>['value'=>$k,'label'=>$this->damageAreaLabels[$k]],
                array_keys($this->damageAreaLabels)
            ),
        ]);
    }
}