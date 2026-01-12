<?php

namespace App\Livewire\Rentals;

use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\Rental;
use App\Models\Customer;
use App\Services\Contracts\GenerateRentalContract;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\RentalChecklist;
use Barryvdh\DomPDF\Facade\Pdf; // se usi barryvdh/laravel-dompdf
use Illuminate\Support\Facades\Storage;

/**
 * Livewire: Scheda noleggio con tab e Action Drawer.
 *
 * NOTE:
 * - Questo componente gestisce sia il "Cliente principale" sia la "Seconda guida"
 *   usando un unico modale, con:
 *     - role: primary|second
 *     - mode: create|edit
 *
 * - I campi del form sono allineati ai nomi reali del DB customers:
 *     birthdate, address_line, postal_code
 */
class Show extends Component
{
    use AuthorizesRequests;

    /** @var Rental Istanza di noleggio */
    public Rental $rental;

    /** @var string Tab attivo: data|contract|pickup|return|damages|attachments|timeline */
    public string $tab = 'data';

    protected $queryString = [
        'tab' => ['as' => 'tab', 'except' => 'data']
    ];

    /* -------------------------------------------------------------------------
     |  MODALE CLIENTE / SECONDA GUIDA
     * ------------------------------------------------------------------------- */

    /** Modale aperto/chiuso */
    public bool $customerModalOpen = false;

    /**
     * Contesto modale (legacy/compat): 'primary'|'second'
     * - Se lo stai già usando in view per titoli/label, resta disponibile.
     * - Internamente usiamo customerRole.
     */
    public string $customerModalContext = 'primary';

    /** Ruolo del modale: primary (cliente) | second (seconda guida) */
    public string $customerRole = 'primary';

    /** Modalità: create (crea/associa) | edit (modifica anagrafica già collegata) */
    public string $customerModalMode = 'create';

    /**
     * Se true, abbiamo selezionato un customer esistente (o stiamo editando).
     * Nota: nel tuo flusso attuale, quando selezioni un customer esistente,
     *       il form è modificabile e al salvataggio verrà anche aggiornato.
     */
    public bool $customerPopulated = false;

    /** Id customer selezionato/in modifica */
    public ?int $customer_id = null;

    /**
     * Form customer (NOMI DB REALI)
     * customers: birthdate, address_line, postal_code
     */
    public array $customerForm = [
        'name'                     => null,
        'email'                    => null,
        'phone'                    => null,
        'doc_id_type'              => null,
        'doc_id_number'            => null,
        'birthdate'                => null,
        'address_line'             => null,
        'city'                     => null,
        'province'                 => null,
        'postal_code'              => null,
        'country_code'             => null,
        'driver_license_number'    => null,
        'driver_license_expires_at'=> null,
    ];

    /** Ricerca customer esistenti (usata SOLO in mode=create) */
    public string $customerQuery = '';

    /* -------------------------------------------------------------------------
     |  PREZZO OVERRIDE
     * ------------------------------------------------------------------------- */

    /** Override prezzo finale (rentals.final_amount_override) */
    public ?string $final_amount_override = null;

    public function mount(Rental $rental): void
    {
        $this->rental = $rental->load([
            'customer',
            'secondDriver',
            'vehicle',
            'checklists',
            'damages'
        ]);

        $this->final_amount_override = $this->rental->final_amount_override !== null
            ? (string) $this->rental->final_amount_override
            : null;
    }

    public function switch(string $tab): void
    {
        $this->tab = $tab;
    }

    /* -------------------------------------------------------------------------
     |  HELPERS: ruolo/mode + FK seconda guida
     * ------------------------------------------------------------------------- */

    protected function isSecondDriverContext(): bool
    {
        return $this->customerRole === 'second';
    }

    /**
     * Compat: in alcuni DB la colonna potrebbe chiamarsi:
     * - second_driver_customer_id (preferita)
     * - second_driver_id (legacy)
     *
     * Restituiamo quella presente negli attributi del model.
     */
    protected function secondDriverForeignKey(): string
    {
        $attrs = $this->rental->getAttributes();

        if (array_key_exists('second_driver_customer_id', $attrs)) {
            return 'second_driver_customer_id';
        }

        // fallback legacy
        return 'second_driver_id';
    }

    protected function primaryForeignKey(): string
    {
        return 'customer_id';
    }

    protected function rentalForeignKeyForRole(): string
    {
        return $this->isSecondDriverContext()
            ? $this->secondDriverForeignKey()
            : $this->primaryForeignKey();
    }

    protected function resetCustomerForm(): void
    {
        $this->customerPopulated = false;
        $this->customer_id = null;

        foreach ($this->customerForm as $key => $value) {
            $this->customerForm[$key] = null;
        }
    }

    protected function fillCustomerFormFromModel(Customer $c): void
    {
        $this->customerForm = [
            'name'                     => $c->name,
            'email'                    => $c->email,
            'phone'                    => $c->phone,
            'doc_id_type'              => $c->doc_id_type,
            'doc_id_number'            => $c->doc_id_number,

            // ✅ NOMI DB REALI
            'birthdate'                => optional($c->birthdate)->format('Y-m-d'),
            'address_line'             => $c->address_line,
            'city'                     => $c->city,
            'province'                 => $c->province,
            'postal_code'              => $c->postal_code,
            'country_code'             => $c->country_code,

            'driver_license_number'    => $c->driver_license_number,
            'driver_license_expires_at'=> optional($c->driver_license_expires_at)->format('Y-m-d'),
        ];
    }

    /* -------------------------------------------------------------------------
     |  VALIDAZIONE CUSTOMER
     * ------------------------------------------------------------------------- */

    protected function customerRules(): array
    {
        return [
            'customerForm.name'          => ['required', 'string', 'max:255'],
            'customerForm.email'         => ['nullable', 'email', 'max:255'],
            'customerForm.phone'         => ['nullable', 'string', 'max:32'],

            'customerForm.doc_id_type'   => ['required', 'in:id,passport'],
            'customerForm.doc_id_number' => ['required', 'string', 'max:64'],

            'customerForm.driver_license_number'     => ['nullable', 'string', 'max:64'],
            'customerForm.driver_license_expires_at' => ['nullable', 'date'],

            // ✅ DB fields reali
            'customerForm.birthdate'     => ['nullable', 'date'],
            'customerForm.address_line'  => ['nullable', 'string', 'max:191'],
            'customerForm.city'          => ['nullable', 'string', 'max:128'],
            'customerForm.province'      => ['nullable', 'string', 'max:64'],
            'customerForm.postal_code'   => ['nullable', 'string', 'max:16'],
            'customerForm.country_code'  => ['nullable', 'string', 'size:2'],
        ];
    }

    /* -------------------------------------------------------------------------
     |  MODALE: OPEN/CLOSE
     * ------------------------------------------------------------------------- */

    /**
     * Apre il modale per:
     * - primary: cliente principale
     * - second : seconda guida (solo se il cliente principale esiste)
     */
    public function openCustomerModal(string $role = 'primary'): void
    {
        $this->authorize('update', $this->rental);

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile modificare i dati conducente dopo l’avvio del noleggio.');
            return;
        }

        $this->resetErrorBag();
        $this->resetValidation();

        $this->customerRole = ($role === 'second') ? 'second' : 'primary';
        $this->customerModalContext = $this->customerRole; // compat con eventuali view

        // Regola: la seconda guida si gestisce solo se esiste il cliente principale
        if ($this->isSecondDriverContext() && empty($this->rental->customer_id)) {
            $this->dispatch('toast', type: 'warning', message: 'Inserisci prima il cliente principale.');
            return;
        }

        // Pulisco ricerca per evitare residui UI
        $this->customerQuery = '';

        // Determino mode + prefill in base a cosa è già collegato al rental
        if ($this->isSecondDriverContext()) {
            $fk = $this->secondDriverForeignKey();
            $hasSecond = !empty($this->rental->{$fk}) && $this->rental->secondDriver;

            if ($hasSecond) {
                $this->customerModalMode = 'edit';
                $this->customerPopulated = true;
                $this->customer_id = (int) $this->rental->secondDriver->id;
                $this->fillCustomerFormFromModel($this->rental->secondDriver);
            } else {
                $this->customerModalMode = 'create';
                $this->resetCustomerForm();
            }
        } else {
            $hasPrimary = !empty($this->rental->customer_id) && $this->rental->customer;

            if ($hasPrimary) {
                $this->customerModalMode = 'edit';
                $this->customerPopulated = true;
                $this->customer_id = (int) $this->rental->customer->id;
                $this->fillCustomerFormFromModel($this->rental->customer);
            } else {
                $this->customerModalMode = 'create';
                $this->resetCustomerForm();
            }
        }

        $this->customerModalOpen = true;
    }

    /** Chiude il modale customer/second driver */
    public function closeCustomerModal(): void
    {
        $this->customerModalOpen = false;
    }

    /* -------------------------------------------------------------------------
     |  SALVATAGGIO CUSTOMER (primary/second)
     * ------------------------------------------------------------------------- */

    public function createOrUpdateCustomer(): void
    {
        $this->authorize('update', $this->rental);

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile modificare i dati conducente dopo l’avvio del noleggio.');
            return;
        }

        $this->validate($this->customerRules());

        // Normalizzazione country ISO2
        if (!empty($this->customerForm['country_code'])) {
            $this->customerForm['country_code'] = strtoupper(trim((string) $this->customerForm['country_code']));
        }

        $fk = $this->rentalForeignKeyForRole();

        try {
            DB::transaction(function () use ($fk) {

                // 1) CREATE/EDIT customer
                if ($this->customerPopulated === true && $this->customer_id) {
                    // Update customer esistente (se selezionato o già collegato)
                    $customer = Customer::query()->findOrFail($this->customer_id);
                    $customer->fill($this->customerForm);
                    $customer->save();
                } else {
                    // Create customer nuovo
                    $customer = new Customer();
                    $customer->fill($this->customerForm);

                    // organization_id è NOT NULL nel tuo DB
                    $customer->organization_id = $this->rental->organization_id;

                    $customer->save();
                }

                // 2) Business rule: seconda guida != cliente principale
                if ($this->isSecondDriverContext() && !empty($this->rental->customer_id)) {
                    if ((int) $this->rental->customer_id === (int) $customer->id) {
                        throw new \RuntimeException('La seconda guida non può coincidere con il cliente principale.');
                    }
                }

                // 3) Associazione sul rental
                $this->rental->{$fk} = (int) $customer->id;
                $this->rental->save();
            });

            $this->rental->refresh();
            $this->rental->load(['customer', 'secondDriver']);

            $this->customerModalOpen = false;

            $msg = $this->isSecondDriverContext()
                ? ($this->customerModalMode === 'edit' ? 'Seconda guida aggiornata.' : 'Seconda guida associata al noleggio.')
                : ($this->customerModalMode === 'edit' ? 'Cliente aggiornato.' : 'Cliente associato al noleggio.');

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('$refresh');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante il salvataggio del cliente.');
        }
    }

    /* -------------------------------------------------------------------------
     |  RICERCA CUSTOMER (SOLO mode=create)
     * ------------------------------------------------------------------------- */

    /**
     * Risultati ricerca clienti (computed).
     * - Min 2 caratteri, max 10 risultati.
     * - Mostrata SOLO quando il modale è in mode=create.
     */
    public function getCustomerSearchResultsProperty(): array
    {
        $this->authorize('update', $this->rental);

        if (!$this->customerModalOpen || $this->customerModalMode !== 'create') {
            return [];
        }

        if (mb_strlen($this->customerQuery) < 2) {
            return [];
        }

        $q = trim($this->customerQuery);

        return Customer::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                      ->orWhere('doc_id_number', 'like', '%' . $q . '%');
            })
            ->limit(10)
            ->get(['id', 'name', 'doc_id_number'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'doc_id_number' => $c->doc_id_number,
            ])
            ->toArray();
    }

    /**
     * Seleziona un customer esistente (solo mode=create):
     * - Compila il form
     * - Imposta customer_id e customerPopulated
     */
    public function selectCustomer(int $id): void
    {
        $this->authorize('update', $this->rental);

        if ($this->customerModalMode !== 'create') {
            return;
        }

        $customer = Customer::query()->findOrFail($id);

        $this->customer_id = (int) $customer->id;
        $this->customerPopulated = true;

        $this->fillCustomerFormFromModel($customer);

        // Pulisco query per chiudere la lista risultati
        $this->customerQuery = '';
    }

    /* -------------------------------------------------------------------------
     |  CONTRATTO / PREZZO OVERRIDE
     * ------------------------------------------------------------------------- */
    /** Regole di validazione per l'override del prezzo finale. */
    protected function finalAmountRules(): array
    {
        return [
            'final_amount_override' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** Salva l'override del prezzo finale. */
    public function saveFinalAmountOverride(): void
    {
        $this->authorize('update', $this->rental);

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile modificare il prezzo in questa fase del noleggio.');
            return;
        }

        $this->validate($this->finalAmountRules());

        $raw = is_string($this->final_amount_override) ? trim($this->final_amount_override) : $this->final_amount_override;
        $value = ($raw === '' || $raw === null) ? null : round((float) $raw, 2);

        try {
            DB::transaction(function () use ($value) {
                $this->rental->final_amount_override = $value;
                $this->rental->save();
            });

            $this->rental->refresh();

            $this->final_amount_override = $this->rental->final_amount_override !== null
                ? (string) $this->rental->final_amount_override
                : null;

            $this->dispatch('toast', type: 'success', message: 'Prezzo aggiornato.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante il salvataggio del prezzo.');
        }
    }

    /** Rimuove l'override del prezzo finale. */
    public function clearFinalAmountOverride(): void
    {
        $this->final_amount_override = null;
        $this->saveFinalAmountOverride();
    }

    /** Genera il contratto di noleggio. */
    public function generateContract(GenerateRentalContract $generator): void
    {
        if (!auth()->user()?->can('rentals.contract.generate') || !auth()->user()?->can('media.attach.contract')) {
            $this->dispatch('toast', type: 'error', message: 'Permesso negato.');
            return;
        }

        if (empty($this->rental->customer_id)) {
            $this->dispatch('toast', type: 'error', message: 'Associa prima un cliente al noleggio.');
            return;
        }

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non puoi generare il contratto in questa fase del noleggio.');
            return;
        }

        try {
            $generator->handle($this->rental);

            if ($this->rental->status === 'draft') {
                $this->rental->status = 'reserved';
                $this->rental->save();
            }

            $this->rental->refresh();
            $this->dispatch('toast', type: 'success', message: 'Contratto generato.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante la generazione del contratto.');
        }
    }

    /** Rigenera il contratto includendo le firme caricate. */
    public function regenerateContractWithSignatures(GenerateRentalContract $generator): void
    {
        $this->authorize('contractGenerate', $this->rental);

        if (empty($this->rental->customer_id)) {
            $this->dispatch('toast', type: 'warning', message: 'Associa prima un cliente al noleggio.');
            return;
        }

        if (!$this->hasCustomerSignature()) {
            $this->dispatch('toast', type: 'warning', message: 'Inserisci prima la firma del cliente per rigenerare il contratto con firme.');
            return;
        }

        $generator->handle($this->rental);

        $this->rental->refresh();

        $this->dispatch('toast', type: 'success', message: 'Contratto rigenerato con le firme.');
    }

    /** Verifica se il noleggio ha la firma del cliente caricata. */  
    private function hasCustomerSignature(): bool
    {
        return method_exists($this->rental, 'getFirstMedia')
            && (bool) $this->rental->getFirstMedia('signature_customer');
    }

    /* -------------------------------------------------------------------------
     |  CHECKLIST EVENTS
     * ------------------------------------------------------------------------- */
    /**
     * Genera il PDF firmato della checklist
     */
    public function generateSignedChecklistPdf(int $checklistId): void
    {
        $tmpRelPath = null;

        try {
            $checklist = RentalChecklist::with([
                'rental.organization',
                'rental.customer',
                'rental.vehicle',
            ])->findOrFail($checklistId);

            // Se vuoi impedire rigenerazione quando lockata:
            if ($checklist->isLocked()) {
                $this->dispatch('toast', type: 'warning', message: 'Checklist già bloccata: rimuovi il firmato per rigenerare.');
                return;
            }

            $rental = $checklist->rental;

            // ===== Recupero firme (stessa logica contratto) =====
            $customerSig = method_exists($rental, 'getFirstMedia')
                ? $rental->getFirstMedia('signature_customer')
                : null;

            $lessorOverrideSig = method_exists($rental, 'getFirstMedia')
                ? $rental->getFirstMedia('signature_lessor')
                : null;

            $lessorDefaultSig  = ($rental->organization && method_exists($rental->organization, 'getFirstMedia'))
                ? $rental->organization->getFirstMedia('signature_company')
                : null;

            $lessorSig = $lessorOverrideSig ?: $lessorDefaultSig;

            if (!$customerSig || !$lessorSig) {
                $this->dispatch('toast', type: 'error', message: 'Per generare il firmato servono firma cliente e firma noleggiante.');
                return;
            }

            // ===== Payload: riusa il TUO builder attuale =====
            $payload = $this->buildChecklistPayload($checklist);

            $signatures = [
                'customer' => $this->mediaToDataUri($customerSig),
                'lessor'   => $this->mediaToDataUri($lessorSig),
            ];

            $generated_at = now();

            $pdf = Pdf::loadView('pdfs.checklist', [
                'checklist'    => $checklist,
                'payload'      => $payload,
                'generated_at' => $generated_at,
                'signatures'   => $signatures,
            ])->setPaper('a4');

            // tmp file
            Storage::makeDirectory('tmp');
            $tmpRelPath = "tmp/checklist-{$checklist->id}-signed.pdf";
            $tmpAbsPath = Storage::path($tmpRelPath);

            file_put_contents($tmpAbsPath, $pdf->output());

            // Salva come "firmato" nella stessa collection usata dal caricamento manuale
            $signedCollection = $checklist->signedCollectionName();

            DB::transaction(function () use ($checklist, $signedCollection, $tmpAbsPath) {
                // Tieni 1 solo firmato (opzionale: già singleFile, ma così sei esplicito)
                $checklist->clearMediaCollection($signedCollection);

                /** @var Media $media */
                $media = $checklist
                    ->addMedia($tmpAbsPath)
                    ->usingName("checklist-{$checklist->type}-signed")
                    ->usingFileName("checklist-{$checklist->type}-{$checklist->id}-signed.pdf")
                    ->toMediaCollection($signedCollection);

                // ✅ IMPORTANTISSIMO: lock + riferimento media (allinea la logica al caricamento manuale)
                $checklist->forceFill([
                    'signed_media_id'   => $media->id,
                    'locked_at'         => now(),
                    'locked_by_user_id' => auth()->id(),
                    'locked_reason'     => 'generated_signed_pdf',
                ])->save();
            });

            // cleanup tmp
            Storage::delete($tmpRelPath);

            $this->dispatch('toast', type: 'success', message: 'Checklist firmata generata con successo.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);

            // prova a pulire il tmp se qualcosa è andato storto
            if ($tmpRelPath) {
                try { Storage::delete($tmpRelPath); } catch (\Throwable $ignored) {}
            }

            $this->dispatch('toast', type: 'error', message: 'Errore durante la generazione del PDF firmato.');
        }
    }

    /** Costruisce il payload per il PDF della checklist (riusa la tua logica esistente) */
    private function buildChecklistPayload(RentalChecklist $checklist): array
    {
        // Evita N+1 quando compili danni (VehicleDamage -> firstRentalDamage)
        $checklist->loadMissing([
            'rental.damages',
            'rental.vehicle.damages.firstRentalDamage',
        ]);

        $base = [
            'mileage'      => $checklist->mileage !== null ? (int) $checklist->mileage : 0,
            'fuel_percent' => $checklist->fuel_percent !== null ? (int) $checklist->fuel_percent : 0,
            'cleanliness'  => $checklist->cleanliness ?: null,
        ];

        // Nel tuo model è castato ad array, ma normalizziamo uguale per robustezza
        $json = $this->normalizeChecklistJson($checklist->checklist_json);

        $damages = $this->buildChecklistDamages($checklist);

        return [
            'base'    => $base,
            'json'    => $json,
            'damages' => $damages,
        ];
    }

    /**
     * checklist_json: garantisce struttura attesa dal PDF:
     * vehicle, documents, equipment, notes
     */
    private function normalizeChecklistJson(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        $raw['vehicle']   = isset($raw['vehicle']) && is_array($raw['vehicle']) ? $raw['vehicle'] : [];
        $raw['documents'] = isset($raw['documents']) && is_array($raw['documents']) ? $raw['documents'] : [];
        $raw['equipment'] = isset($raw['equipment']) && is_array($raw['equipment']) ? $raw['equipment'] : [];
        $raw['notes']     = isset($raw['notes']) ? (string) $raw['notes'] : '';

        return $raw;
    }

    /**
     * Unisce:
     * - VehicleDamage OPEN (storici/persistenti del veicolo)
     * - RentalDamage del noleggio (in base al tipo checklist)
     *
     * Output: array di righe compatibili col tuo PDF:
     * [
     *   ['area'=>'front','severity'=>'low','description'=>'...'],
     * ]
     */
    private function buildChecklistDamages(RentalChecklist $checklist): array
    {
        $rental  = $checklist->rental;
        $vehicle = $rental?->vehicle;

        // 1) Vehicle open damages (escludo quelli originati dallo STESSO rental, per evitare duplicati)
        $vehicleRows = collect();

        if ($vehicle) {
            $vehicleOpen = $vehicle->damages
                ? $vehicle->damages->where('is_open', true)
                : collect();

            $vehicleRows = $vehicleOpen
                ->filter(function ($vd) use ($rental) {
                    // se il danno del veicolo nasce da un RentalDamage di QUESTO rental,
                    // lo mostriamo già nella sezione rental -> quindi lo escludiamo qui
                    $first = $vd->firstRentalDamage ?? null;
                    return !($first && (int) $first->rental_id === (int) $rental->id);
                })
                ->map(function ($vd) {
                    $area = (string) ($vd->resolved_area ?? $vd->area ?? 'other');
                    $severity = (string) ($vd->resolved_severity ?? $vd->severity ?? 'low');
                    $desc = trim((string) ($vd->resolved_description ?? $vd->description ?? '')) ?: '—';

                    return [
                        'area'        => $area ?: 'other',
                        'severity'    => $severity ?: 'low',
                        'description' => $desc,
                    ];
                });
        }

        // 2) Rental damages (filtrati per tipo checklist)
        $allowedPhases = $checklist->type === 'pickup'
            ? ['pickup']                       // in pickup: danni rilevati in pickup
            : ['pickup', 'during', 'return'];  // in return: tutto ciò che è successo nel noleggio

        $rentalRows = ($rental?->damages ?? collect())
            ->filter(function ($rd) use ($allowedPhases) {
                $phase = (string) ($rd->phase ?? '');
                // se phase è null/'' lo includo comunque (scelta “robusta”)
                return $phase === '' || in_array($phase, $allowedPhases, true);
            })
            ->map(function ($rd) {
                $area = (string) ($rd->area ?? 'other');
                $severity = (string) ($rd->severity ?? 'low');
                $desc = trim((string) ($rd->description ?? '')) ?: '—';

                return [
                    'area'        => $area ?: 'other',
                    'severity'    => $severity ?: 'low',
                    'description' => $desc,
                ];
            });

        // Merge + dedupe “soft”
        return $vehicleRows
            ->merge($rentalRows)
            ->filter(fn($r) => !empty($r['area']) || !empty($r['description']))
            ->unique(fn($r) => ($r['area'] ?? '').'|'.($r['severity'] ?? '').'|'.($r['description'] ?? ''))
            ->values()
            ->all();
    }

    /** Converte un media in Data URI per l’embed nel PDF */
    private function mediaToDataUri(?Media $media): ?string
    {
        if (!$media) return null;

        $path = $media->getPath();
        if (!is_file($path)) return null;

        $mime = $media->mime_type ?: (function_exists('mime_content_type') ? mime_content_type($path) : null) ?: 'image/png';
        $data = base64_encode(file_get_contents($path));

        return "data:{$mime};base64,{$data}";
    }

    /**
     * Aggiorna la rental quando viene caricato un file firmato per una checklist.
     */
    #[On('checklist-signed-uploaded')]
    public function onChecklistSignedUploaded(array $payload = []): void
    {
        if (isset($payload['rentalId']) && (int) $payload['rentalId'] !== (int) $this->rental->id) {
            return;
        }

        $this->rental->refresh();
        $this->rental->load(['checklists.media']);

        $this->dispatch('$refresh');
    }

    /**
     * Aggiorna la rental quando i media delle checklist vengono modificati
     * (aggiunti/rimossi).
     */
    #[On('checklist-media-updated')]
    public function onChecklistMediaUpdated(): void
    {
        $this->rental->refresh();
        $this->rental->load(['checklists.media']);
        $this->dispatch('$refresh');
    }

    /**
     * Aggiorna la rental quando viene aggiornata la firma del cliente
     * nel contratto di noleggio.
     */
    #[On('signature-updated')]
    public function onSignatureUpdated(): void
    {
        $this->rental->refresh();
        $this->rental->load(['customer', 'secondDriver']); // e media se la usi qui
        $this->dispatch('$refresh');
    }

    public function render(): View
    {
        return view('livewire.rentals.show');
    }
}
