<?php

namespace App\Livewire\Rentals;

use App\Models\Rental;
use App\Models\RentalChecklist;
use App\Models\RentalDamage;
use App\Models\VehicleDamage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Arr;
use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Livewire Component: rentals.checklist-form
 *
 * Gestisce la creazione/edit della checklist di noleggio in 3 tab:
 *  - TAB 1 (Base): chilometraggio, carburante, pulizia, firme (flag)
 *  - TAB 2 (Checklist JSON v1): documenti, dotazioni, condizioni, note
 *  - TAB 3 (Danni):
 *      * pickup  → preload “vehicle_damages” aperti (readonly, nessun add/remove/salvataggio)
 *      * return  → CRUD idempotente su “rental_damages” (fase corrente), lock-aware
 *
 * Nota: upload immagini/documenti e PDF restano nel controller dedicato.
 */
class ChecklistForm extends Component
{
    use AuthorizesRequests;

    /** @var \App\Models\Rental  Noleggio corrente (iniettato dal route-model binding) */
    public Rental $rental;

    /** Tipo checklist: 'pickup' | 'return' (da query string) */
    public string $type = 'pickup';

    // -------------------------------
    //   TAB 1 — BASE
    // -------------------------------

    /** Km dichiarati in checklist */
    public ?int $mileage = null;

    /** Carburante % (0–100) */
    public ?int $fuel_percent = 0;

    /** Pulizia ('poor'|'fair'|'good'|'excellent'|null) */
    public ?string $cleanliness = null;

    /** Flag firme (solo boolean “spunta”, la firma è media gestito da controller) */
    public bool $signed_by_customer = false;
    public bool $signed_by_operator = false;

    /** UUID media firma (compatibilità, non usato nello step corrente) */
    public ?string $signature_media_uuid = null;

    /** Km attuali del veicolo (solo riferimento UI/validazione) */
    public ?int $current_vehicle_mileage = null;

    // -------------------------------
    //   TAB 2 — CHECKLIST JSON v1
    // -------------------------------

    /** Struttura JSON normalizzata (array) salvata in rental_checklists.checklist_json */
    public array $checklist = [];

    // -------------------------------
    //   TAB 3 — DANNI
    // -------------------------------

    /**
     * Danni per la tab (array di righe).
     *  - pickup: preload da VehicleDamage (aperti) con accessor “risolti” (readonly).
     *  - return: array dinamico (id, area, severity, description) su RentalDamage fase corrente.
     */
    public array $damages = [];

    /** true se i danni sono solo consultivi (pickup) → disabilita add/remove/edit/salva */
    public bool $damagesReadonly = false;

    // -------------------------------
    //   TAB 4 — MEDIA & DOCUMENTI
    // -------------------------------

    /** Foto checklist raggruppate per kind (odometer|fuel|exterior) */
    public array $mediaChecklist = [
        'odometer' => [],
        'fuel'     => [],
        'exterior' => [],
    ];

    /** Stato PDF lato UI */
    public ?string $last_pdf_url = null;  // URL ultimo PDF non firmato
    public bool $pdf_dirty = true;        // se true, "Genera PDF" abilitato

    // -------------------------------
    //   Stato Checklist
    // -------------------------------

    /** ID riga checklist (dopo saveBase) → abilita le tab 2-3 in UI */
    public ?int $checklistId = null;

    /** Stato lock persistente (Opzione B: blocco dopo firma PDF) */
    public bool $isLocked = false;

    /**
     * Mount iniziale:
     * - Determina type ('pickup'|'return')
     * - Preleva chilometraggio attuale veicolo
     * - Precarica eventuale riga esistente checklist (e stato lock)
     * - Precarica danni: vehicle_damages (pickup, open) | rental_damages (return, fase corrente)
     */
    public function mount(Rental $rental, ?string $type = null): void
    {
        $this->rental = $rental;
        $this->type   = \in_array($type, ['pickup','return'], true) ? $type : 'pickup';

        // Km attuali del veicolo per validazione “min”
        $vehicle = $rental->vehicle ?? null;
        $this->current_vehicle_mileage =
            $vehicle->mileage_current        // nome colonna usato nel tuo schema
            ?? $vehicle->odometer_km // fallback comuni
            ?? $vehicle->odometer
            ?? 0;

        // Se esiste già una checklist per (rental_id, type), precarica base + JSON + lock
        $existing = $this->rental->checklists()->where('type', $this->type)->first();
        if ($existing) {
            $this->checklistId          = (int) $existing->id;
            $this->isLocked             = $existing->isLocked();
            $this->mileage              = $existing->mileage;
            $this->fuel_percent         = $existing->fuel_percent;
            $this->cleanliness          = $existing->cleanliness;
            $this->signed_by_customer   = (bool) $existing->signed_by_customer;
            $this->signed_by_operator   = (bool) $existing->signed_by_operator;
            $this->signature_media_uuid = $existing->signature_media_uuid;
            $this->checklist            = $existing->checklist_json ?? [];

            // ===== Precarica foto 'photos' raggruppate per kind =====
            $groups = ['odometer'=>[], 'fuel'=>[], 'exterior'=>[]];
            foreach ($existing->getMedia('photos') as $m) {
                $kind = $m->getCustomProperty('kind') ?: 'exterior';
                if (!isset($groups[$kind])) $kind = 'exterior';

                $groups[$kind][] = [
                    'id'         => (int) $m->id,
                    'name'       => $m->file_name,
                    'url'        => $m->getUrl(),
                    'size'       => (int) $m->size,
                    'delete_url' => route('media.destroy', $m), // 👈 qui
                ];
            }
            $this->mediaChecklist = $groups;
        }

        // -------------------------
        // Precarico DANNI per la tab
        // -------------------------
        if ($this->type === 'pickup') {
            // In pickup i danni NON si inseriscono: mostriamo i danni veicolo aperti (readonly)
            $this->damagesReadonly = true;

            $vehicleId = $this->rental->vehicle?->id;
            $this->damages = $vehicleId
                ? VehicleDamage::query()
                    ->forVehicle($vehicleId)
                    ->open()
                    ->orderBy('id')
                    ->get()
                    ->map(function (VehicleDamage $vd) {
                        return [
                            'id'          => (int) $vd->id,              // ID del vehicle_damage (consultazione)
                            'area'        => $vd->resolved_area,        // accessor (preferisce manuale, fallback first_rental_damage)
                            'severity'    => $vd->resolved_severity,    // accessor
                            'description' => $vd->resolved_description, // accessor
                            'source'      => $vd->source,               // solo display
                        ];
                    })
                    ->values()
                    ->all()
                : [];
        } else {
            // In return (o altre fasi) lavoriamo sui rental_damages della fase corrente
            $this->damagesReadonly = false;

            $this->damages = RentalDamage::query()
                ->where('rental_id', $this->rental->id)
                ->where('phase', $this->type) // 'return' o altro
                ->orderBy('id')
                ->get()
                ->map(function (RentalDamage $d) {
                    return [
                        'id'          => (int) $d->id,
                        'area'        => $d->area,
                        'severity'    => $d->severity,    // 'low'|'medium'|'high'
                        'description' => $d->description,
                    ];
                })
                ->values()
                ->all();
        }

        $this->refreshPdfState(); // calcola url + dirty
    }

    // =========================================================================
    // TAB 1 — BASE: validazione + salvataggio (create/update) + eventi Alpine
    // =========================================================================

    /**
     * Regole di validazione per TAB 1 (Base).
     * - 'mileage' min vincolato ai km attuali del veicolo
     * - 'fuel_percent' 0..100
     */
    protected function rulesBase(): array
    {
        return [
            'type'               => ['required','in:pickup,return'],
            'mileage'            => ['required','integer','min:'.((int)($this->current_vehicle_mileage ?? 0)),'max:2000000'],
            'fuel_percent'       => ['required','integer','min:0','max:100'],
            'cleanliness'        => ['required','in:poor,fair,good,excellent'],
            'signed_by_customer' => ['boolean'],
            'signed_by_operator' => ['boolean'],
        ];
    }

    /**
     * Salva/Crea la checklist (solo TAB 1).
     * - Se riga esiste ed è locked → nessuna modifica, errore UI.
     * - Se non esiste → crea riga con base + JSON corrente (se presente).
     * - Emette eventi per Alpine: sblocca tab dopo successo; segnala lock se presente.
     */
    public function saveBase(): void
    {
        $traceId = (string) Str::uuid();

        // 0) Snapshot stato iniziale
        Log::info('[CHK][saveBase][START]', [
            'trace_id'   => $traceId,
            'rental_id'  => $this->rental->id ?? null,
            'type'       => $this->type,
            'props'      => [
                'mileage'              => $this->mileage,
                'fuel_percent'         => $this->fuel_percent,
                'cleanliness'          => $this->cleanliness,
                'signed_by_customer'   => $this->signed_by_customer,
                'signed_by_operator'   => $this->signed_by_operator,
                'signature_media_uuid' => $this->signature_media_uuid,
                'current_vehicle_km'   => $this->current_vehicle_mileage,
                'checklistId'          => $this->checklistId,
                'isLocked'             => $this->isLocked,
            ],
            'referer'    => request()->headers->get('referer'),
            'url'        => request()->fullUrl(),
        ]);

        // 1) Normalizzo UUID firma PRIMA della validazione
        $rawUuid = $this->signature_media_uuid;
        $this->signature_media_uuid = $this->normalizeUuid($this->signature_media_uuid);
        Log::debug('[CHK][saveBase] UUID normalized', [
            'trace_id'   => $traceId,
            'raw'        => $rawUuid,
            'normalized' => $this->signature_media_uuid,
        ]);

        // 2) Valido
        $data = $this->validate($this->rulesBase());
        Log::debug('[CHK][saveBase] Validated data', [
            'trace_id' => $traceId,
            'data'     => $data,
        ]);

        // 3) Traccia query SQL del blocco critico
        DB::enableQueryLog();
        try {
            DB::transaction(function () use ($data, $traceId) {
                $existing = $this->rental->checklists()
                    ->where('type', $this->type)
                    ->lockForUpdate()
                    ->first();

                Log::debug('[CHK][saveBase] Existing checklist lookup', [
                    'trace_id' => $traceId,
                    'found'    => (bool) $existing,
                    'id'       => $existing?->id,
                    'locked'   => $existing?->isLocked(),
                ]);

                if ($existing) {
                    $this->authorize('update', $existing);

                    if ($existing->isLocked()) {
                        $this->isLocked    = true;
                        $this->checklistId = (int) $existing->id;
                        Log::warning('[CHK][saveBase] Checklist is LOCKED (no update)', [
                            'trace_id'     => $traceId,
                            'checklist_id' => $this->checklistId,
                        ]);
                        $this->addError('mileage', __('La checklist è bloccata e non può essere modificata.'));
                        return;
                    }

                    // --- UPDATE ---
                    $existing->fill([
                        'mileage'            => $data['mileage'] ?? null,
                        'fuel_percent'       => $data['fuel_percent'] ?? 0,
                        'cleanliness'        => $data['cleanliness'] ?? null,
                        'signed_by_customer' => (bool) $data['signed_by_customer'],
                        'signed_by_operator' => (bool) $data['signed_by_operator'],
                    ])->save();

                    $this->checklistId = (int) $existing->id;
                    $this->isLocked    = false;

                    Log::info('[CHK][saveBase] Checklist UPDATED', [
                        'trace_id'     => $traceId,
                        'checklist_id' => $this->checklistId,
                    ]);

                    // Veicolo/rental updates
                    $veh   = $this->rental->vehicle()->lockForUpdate()->first();
                    $mOut  = $data['mileage'] ?? null;
                    $fOut  = $data['fuel_percent'] ?? null;

                    if ($veh && $mOut !== null) {
                        $old = (int) ($veh->mileage_current ?? 0);
                        $new = (int) $mOut;
                        if ($new !== $old) {
                            $veh->forceFill(['mileage_current' => $new])->save();
                            DB::table('vehicle_mileage_logs')->insert([
                                'vehicle_id'  => (int) $veh->id,
                                'mileage_old' => $old,
                                'mileage_new' => $new,
                                'changed_by'  => (int) (auth()->id() ?? 0),
                                'source'      => 'manual',
                                'notes'       => 'Aggiornamento nella checklist del noleggio #'.$this->rental->id,
                                'changed_at'  => now(),
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ]);
                            Log::debug('[CHK][saveBase] Vehicle km updated', [
                                'trace_id'    => $traceId,
                                'vehicle_id'  => $veh->id,
                                'km_old'      => $old,
                                'km_new'      => $new,
                            ]);
                        }
                        $this->current_vehicle_mileage = $new;
                    }

                    if ($this->type === 'pickup') {
                        $updates = [];
                        if ($mOut !== null) $updates['mileage_out']      = (int) $mOut;
                        if ($fOut !== null) $updates['fuel_out_percent'] = (int) $fOut;
                        if ($updates) {
                            $this->rental->fill($updates)->save();
                            Log::debug('[CHK][saveBase] Rental OUT fields updated', [
                                'trace_id'  => $traceId,
                                'rental_id' => $this->rental->id,
                                'updates'   => $updates,
                            ]);
                        }
                    } else {
                        $updates = [];
                        if ($mOut !== null) $updates['mileage_in']      = (int) $mOut;
                        if ($fOut !== null) $updates['fuel_in_percent'] = (int) $fOut;
                        if ($updates) {
                            $this->rental->fill($updates)->save();
                            Log::debug('[CHK][saveBase] Rental IN fields updated', [
                                'trace_id'  => $traceId,
                                'rental_id' => $this->rental->id,
                                'updates'   => $updates,
                            ]);
                        }
                    }
                } else {
                    // --- CREATE ---
                    $this->authorize('create', \App\Models\RentalChecklist::class);

                    $new = new \App\Models\RentalChecklist([
                        'rental_id'            => $this->rental->id,
                        'type'                 => $this->type,
                        'mileage'              => $data['mileage'] ?? null,
                        'fuel_percent'         => $data['fuel_percent'] ?? 0,
                        'cleanliness'          => $data['cleanliness'] ?? null,
                        'signed_by_customer'   => (bool) $data['signed_by_customer'],
                        'signed_by_operator'   => (bool) $data['signed_by_operator'],
                        'signature_media_uuid' => $this->normalizeUuid($data['signature_media_uuid'] ?? null),
                        'checklist_json'       => $this->checklist ?? [],
                        'created_by'           => auth()->id(),
                    ]);
                    $new->save();

                    $this->checklistId = (int) $new->id;
                    $this->isLocked    = false;

                    Log::info('[CHK][saveBase] Checklist CREATED', [
                        'trace_id'     => $traceId,
                        'checklist_id' => $this->checklistId,
                    ]);

                    // Veicolo/rental updates (come sopra)
                    $veh   = $this->rental->vehicle()->lockForUpdate()->first();
                    $mOut  = $data['mileage'] ?? null;
                    $fOut  = $data['fuel_percent'] ?? null;

                    if ($veh && $mOut !== null) {
                        $old = (int) ($veh->mileage_current ?? 0);
                        $newKm = (int) $mOut;
                        if ($newKm !== $old) {
                            $veh->forceFill(['mileage_current' => $newKm])->save();
                            DB::table('vehicle_mileage_logs')->insert([
                                'vehicle_id'  => (int) $veh->id,
                                'mileage_old' => $old,
                                'mileage_new' => $newKm,
                                'changed_by'  => (int) (auth()->id() ?? 0),
                                'source'      => 'manual',
                                'notes'       => 'Aggiornamento nella checklist del noleggio #'.$this->rental->id,
                                'changed_at'  => now(),
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ]);
                            Log::debug('[CHK][saveBase] Vehicle km updated (create)', [
                                'trace_id'    => $traceId,
                                'vehicle_id'  => $veh->id,
                                'km_old'      => $old,
                                'km_new'      => $newKm,
                            ]);
                        }
                        $this->current_vehicle_mileage = $newKm;
                    }

                    if ($this->type === 'pickup') {
                        $updates = [];
                        if ($mOut !== null) $updates['mileage_out']      = (int) $mOut;
                        if ($fOut !== null) $updates['fuel_out_percent'] = (int) $fOut;
                        if ($updates) {
                            $this->rental->fill($updates)->save();
                            Log::debug('[CHK][saveBase] Rental OUT fields updated (create)', [
                                'trace_id'  => $traceId,
                                'rental_id' => $this->rental->id,
                                'updates'   => $updates,
                            ]);
                        }
                    } else {
                        $updates = [];
                        if ($mOut !== null) $updates['mileage_in']      = (int) $mOut;
                        if ($fOut !== null) $updates['fuel_in_percent'] = (int) $fOut;
                        if ($updates) {
                            $this->rental->fill($updates)->save();
                            Log::debug('[CHK][saveBase] Rental IN fields updated', [
                                'trace_id'  => $traceId,
                                'rental_id' => $this->rental->id,
                                'updates'   => $updates,
                            ]);
                        }
                    }
                }
            });

            // 4) Stato PDF ricalcolato
            $this->refreshPdfState();
            Log::debug('[CHK][saveBase] PDF state after save', [
                'trace_id'     => $traceId,
                'last_pdf_url' => $this->last_pdf_url,
                'pdf_dirty'    => $this->pdf_dirty,
            ]);

            // 5) Eventi browser/UI
            if ($this->checklistId && !$this->isLocked) {
                $this->dispatch('checklist-base-saved', checklistId: $this->checklistId, locked: false, type: $this->type);
                Log::debug('[CHK][dispatch] checklist-base-saved', ['trace_id' => $traceId]);
            }
            if ($this->checklistId && $this->isLocked) {
                $this->dispatch('checklist-locked', checklistId: $this->checklistId, locked: true);
                Log::debug('[CHK][dispatch] checklist-locked', ['trace_id' => $traceId]);
            }

            $this->dispatch('toast',
                type: $this->isLocked ? 'warning' : 'success',
                message: $this->isLocked ? __('Checklist bloccata: nessuna modifica applicata.') : __('Dati base salvati.'),
                duration: 3000
            );

            Log::info('[CHK][saveBase][END_OK]', [
                'trace_id'     => $traceId,
                'checklist_id' => $this->checklistId,
                'isLocked'     => $this->isLocked,
            ]);
        } catch (Throwable $e) {
            Log::error('[CHK][saveBase][EXCEPTION]', [
                'trace_id' => $traceId,
                'error'    => $e->getMessage(),
                'class'    => get_class($e),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'trace'    => $e->getTraceAsString(),
            ]);
            throw $e; // lascia che Laravel gestisca come prima
        } finally {
            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            // NB: limitiamo i dettagli del binding per non esplodere i log
            Log::debug('[CHK][saveBase][SQL]', [
                'trace_id' => $traceId,
                'count'    => count($queries),
                'queries'  => array_map(function ($q) {
                    return [
                        'sql'      => $q['query'] ?? null,
                        'time_ms'  => $q['time']  ?? null,
                    ];
                }, $queries),
            ]);
        }
    }

    /** Normalizza "undefined"/vuoti a null prima di validare/salvare */
    protected function normalizeUuid(?string $v): ?string
    {
        if ($v === null) return null;
        $v = trim((string)$v);
        if ($v === '' || strtolower($v) === 'undefined' || strtolower($v) === 'null') return null;
        return $v;
    }

    // =========================================================================
    // TAB 2 — CHECKLIST (JSON v1): validazione/normalizzazione/salvataggio
    // =========================================================================

    /** Regole per la struttura JSON v1 della tab 2 */
    protected function rulesChecklist(): array
    {
        return [
            // Documenti
            'checklist.documents.id_card'         => ['nullable','boolean'],
            'checklist.documents.driver_license'  => ['nullable','boolean'],
            'checklist.documents.contract_copy'   => ['nullable','boolean'],

            // Dotazioni / sicurezza
            'checklist.equipment.spare_wheel'     => ['nullable','boolean'],
            'checklist.equipment.jack'            => ['nullable','boolean'],
            'checklist.equipment.triangle'        => ['nullable','boolean'],
            'checklist.equipment.vest'            => ['nullable','boolean'],

            // Condizioni veicolo
            'checklist.vehicle.lights_ok'         => ['nullable','boolean'],
            'checklist.vehicle.horn_ok'           => ['nullable','boolean'],
            'checklist.vehicle.brakes_ok'         => ['nullable','boolean'],
            'checklist.vehicle.tires_ok'          => ['nullable','boolean'],
            'checklist.vehicle.windshield_ok'     => ['nullable','boolean'],

            // Note libere
            'checklist.notes'                     => ['nullable','string','max:2000'],
        ];
    }

    /**
     * Normalizza l'array checklist in forma JSON v1 stabile (true/false/stringhe).
     */
    protected function normalizeChecklistV1(array $input): array
    {
        $bool = static fn($v) => filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;

        return [
            'schema_version' => 1,
            'documents' => [
                'id_card'        => $bool($input['documents']['id_card']        ?? false),
                'driver_license' => $bool($input['documents']['driver_license'] ?? false),
                'contract_copy'  => $bool($input['documents']['contract_copy']  ?? false),
            ],
            'equipment' => [
                'spare_wheel' => $bool($input['equipment']['spare_wheel'] ?? false),
                'jack'        => $bool($input['equipment']['jack']        ?? false),
                'triangle'    => $bool($input['equipment']['triangle']    ?? false),
                'vest'        => $bool($input['equipment']['vest']        ?? false),
            ],
            'vehicle' => [
                'lights_ok'     => $bool($input['vehicle']['lights_ok']     ?? false),
                'horn_ok'       => $bool($input['vehicle']['horn_ok']       ?? false),
                'brakes_ok'     => $bool($input['vehicle']['brakes_ok']     ?? false),
                'tires_ok'      => $bool($input['vehicle']['tires_ok']      ?? false),
                'windshield_ok' => $bool($input['vehicle']['windshield_ok'] ?? false),
            ],
            'notes' => isset($input['notes']) ? (string) $input['notes'] : null,
        ];
    }

    /**
     * Persistenza della tab 2 (JSON v1) su checklist_json.
     * Richiede che la checklist esista e non sia locked.
     */
    public function saveChecklist(): void
    {
        if (!$this->checklistId) {
            $this->dispatch('toast', type:'warning', message:__('Salva prima i dati base.'), duration:3000);
            return;
        }

        DB::transaction(function () {
            /** @var \App\Models\RentalChecklist|null $row */
            $row = RentalChecklist::lockForUpdate()->find($this->checklistId);
            if (!$row) {
                $this->dispatch('toast', type:'error', message:__('Checklist non trovata.'), duration:3000);
                return;
            }

            $this->authorize('update', $row);
            if ($row->isLocked()) {
                $this->isLocked = true;
                $this->dispatch('toast', type:'warning', message:__('Checklist bloccata: impossibile salvare modifiche.'), duration:3000);
                return;
            }

            $this->validate($this->rulesChecklist());
            $normalized = $this->normalizeChecklistV1($this->checklist ?? []);

            $row->checklist_json = $normalized;
            $row->save();

            // Aggiorna stato locale (mutator/array casting)
            $this->checklist = $row->checklist_json ?? [];

            // Recalcola subito lo stato PDF
            $this->refreshPdfState();

            $this->dispatch('toast', type:'success', message:__('Checklist salvata.'), duration:3000);
        });
    }

    // =========================================================================
    // TAB 3 — DANNI: add/remove (solo return), salvataggio idempotente su rental_damages
    // =========================================================================

    /** Aggiunge una riga danno (solo return; in pickup è readonly) */
    public function addDamageRow(): void
    {
        if ($this->damagesReadonly || !$this->checklistId || $this->isLocked) {
            $this->dispatch('toast', type:'warning', message:__('Operazione non consentita in questa fase.'), duration:3000);
            return;
        }

        $this->damages[] = [
            'id'          => null,
            'area'        => null,
            'severity'    => null,   // 'low'|'medium'|'high'
            'description' => null,
        ];
    }

    /** Rimuove una riga danno dall’array UI (solo return) */
    public function removeDamageRow(int $index): void
    {
        if ($this->damagesReadonly || !isset($this->damages[$index])) return;
        unset($this->damages[$index]);
        $this->damages = array_values($this->damages); // reindex
    }

    /** Regole per i danni (array può essere vuoto; severity allineata all’enum) */
    protected function rulesDamages(): array
    {
        return [
            'damages'               => ['array'],
            'damages.*.id'          => ['nullable','integer'],
            'damages.*.area'        => ['nullable','string','max:64'],
            'damages.*.severity'    => ['nullable','in:low,medium,high'],
            'damages.*.description' => ['nullable','string','max:2000'],
        ];
    }

    /** True se la riga “danno” non ha dati significativi (non va salvata) */
    protected function isDamageEmpty(array $data): bool
    {
        $a = trim((string)($data['area'] ?? ''));
        $s = trim((string)($data['severity'] ?? ''));
        $d = trim((string)($data['description'] ?? ''));
        return $a === '' && $s === '' && $d === '';
    }

    /**
     * Salva i danni (solo per fasi ≠ pickup).
     * - Idempotente su rental_id + phase
     * - Update i record con id; crea i nuovi; cancella quelli rimossi
     * - Bloccato se checklist è locked
     */
    public function saveDamages(): void
    {
        // In pickup non salviamo: sono precaricati da vehicle_damages (readonly)
        if ($this->type === 'pickup') {
            $this->dispatch('toast', type:'info', message:__('In pickup i danni sono precaricati automaticamente.'), duration:3000);
            return;
        }

        if (!$this->checklistId) {
            $this->dispatch('toast', type:'warning', message:__('Salva prima i dati base.'), duration:3000);
            return;
        }

        DB::transaction(function () {
            $checklist = RentalChecklist::lockForUpdate()->find($this->checklistId);
            if (!$checklist) {
                $this->dispatch('toast', type:'error', message:__('Checklist non trovata.'), duration:3000);
                return;
            }

            $this->authorize('update', $checklist);
            if ($checklist->isLocked()) {
                $this->isLocked = true;
                $this->dispatch('toast', type:'warning', message:__('Checklist bloccata: impossibile salvare modifiche.'), duration:3000);
                return;
            }

            $this->validate($this->rulesDamages());

            // Danni esistenti per questo rental e questa fase
            $existing = RentalDamage::query()
                ->where('rental_id', $this->rental->id)
                ->where('phase', $this->type)
                ->get()
                ->keyBy('id');

            $keepIds   = [];
            $inputRows = $this->damages ?? [];

            foreach ($inputRows as $i => $row) {
                $id   = Arr::get($row, 'id');
                $data = [
                    'area'        => Arr::get($row, 'area'),
                    'severity'    => Arr::get($row, 'severity'),    // 'low'|'medium'|'high'
                    'description' => Arr::get($row, 'description'),
                ];

                // Non salvare righe vuote
                if ($this->isDamageEmpty($data)) {
                    continue;
                }

                if ($id && $existing->has($id)) {
                    /** @var RentalDamage $d */
                    $d = $existing->get($id);
                    $d->fill($data)->save();
                    $keepIds[] = (int) $d->id;
                } else {
                    $d = new RentalDamage(array_merge($data, [
                        'rental_id'  => $this->rental->id,
                        'phase'      => $this->type,   // return (o altro)
                        'created_by' => auth()->id(),
                    ]));
                    $d->save();
                    $keepIds[] = (int) $d->id;

                    // Aggiorna l’ID nella riga UI
                    $this->damages[$i]['id'] = (int) $d->id;
                }
            }

            // Cancella i danni rimossi in UI
            foreach ($existing as $id => $d) {
                if (!in_array((int) $id, $keepIds, true)) {
                    $d->delete();
                }
            }
        });
        // Recalcola subito lo stato PDF
        $this->refreshPdfState();

        $this->dispatch('toast', type:'success', message:__('Danni salvati.'), duration:3000);
        $this->broadcastDamagesUpdated();
    }

    /**
     * Invia al browser la lista danni persistita (solo id/area/severity),
     * così la select "Foto danni" si aggiorna senza ricaricare la pagina.
     */
    protected function broadcastDamagesUpdated(): void
    {
        $items = RentalDamage::query()
            ->where('rental_id', $this->rental->id)
            ->where('phase', $this->type)
            ->orderBy('id')
            ->get(['id','area','severity'])
            ->map(fn ($d) => [
                'id'       => (int) $d->id,
                'area'     => (string) ($d->area ?? ''),
                'severity' => (string) ($d->severity ?? ''),
            ])
            ->values()
            ->all();

        // Evento browser → intercettato da Alpine nella sezione Media
        $this->dispatch('damages-updated', items: $items);
    }

    // ================== PDF: helper/compute ==================

    /**
     * Costruisce il payload canonico da "hashare".
     * NOTA: legge dal DB (coerente con i dati salvati).
     */
    protected function buildPdfPayload(RentalChecklist $checklist): array
    {
        // 1) Dati base della checklist
        $base = $checklist->only([
            'type','mileage','fuel_percent','cleanliness',
            'signed_by_customer','signed_by_operator',
        ]);

        // 2) JSON v1
        $json = $checklist->checklist_json ?? [];

        // 3)  Danni correnti collegati al rental (sempre)
        $damages = RentalDamage::query()
            ->where('rental_id', $checklist->rental_id)
            ->orderBy('id')
            ->get(['id','area','severity','description'])
            ->map(fn($d) => [
                'id'          => (int) $d->id,          // id di rental_damages
                'rd_id'       => (int) $d->id,
                'area'        => (string) ($d->area ?? ''),
                'severity'    => (string) ($d->severity ?? ''),
                'description' => (string) ($d->description ?? ''),
                'preexisting' => false,
                'source'      => 'rental',
            ])->values()->all();

        // 4) Danni "pickup" da vehicle_damages (aperti). Servono i campi, altrimenti fallback da first_rental_damage
        if ($checklist->type === 'pickup') {
            $vehicleId = $checklist->rental->vehicle?->id;

            if ($vehicleId) {
                $vehicleDamages = VehicleDamage::query()
                    ->forVehicle($vehicleId)
                    ->open()
                    ->with(['firstRentalDamage' => function ($q) {
                        $q->select('id','area','severity','description'); // rental_damages originari
                    }])
                    ->orderBy('id')
                    ->get(['id','first_rental_damage_id','area','severity','description']);

                $pickupDamages = $vehicleDamages->map(function ($vd) {
                    // fallback dai campi del VD oppure dal first_rental_damage
                    $area  = (string) ($vd->area ?? $vd->firstRentalDamage?->area ?? '');
                    $sev   = (string) ($vd->severity ?? $vd->firstRentalDamage?->severity ?? '');
                    $desc  = (string) ($vd->description ?? $vd->firstRentalDamage?->description ?? '');

                    return [
                        'id'          => (int) $vd->id,     // id di vehicle_damages (diverso dagli RD)
                        'vd_id'       => (int) $vd->id,
                        'area'        => $area,
                        'severity'    => $sev,
                        'description' => $desc,
                        'preexisting' => true,
                        'source'      => 'vehicle',
                    ];
                })->values()->all();

                // Unisci: prima i vehicle (pregressi), poi i rental_damages
                // Deduplica “soft” per (area|severity|description) per evitare doppioni visivi
                $merged = collect($pickupDamages)
                    ->concat($damages)
                    ->unique(function ($r) {
                        return strtolower(trim(($r['area'] ?? '').'|'.($r['severity'] ?? '').'|'.($r['description'] ?? '')));
                    })
                    ->values()
                    ->all();

                $damages = $merged;
            }
        }

        return [
            'base'     => $base,
            'json'     => $json,
            'damages'  => $damages,
            'rentalId' => (int) $checklist->rental_id,
            'chkId'    => (int) $checklist->id,
        ];
    }

    /**
     * Hash stabile del payload (JSON canonicalizzato).
     */
    protected function payloadHash(array $payload): string
    {
        // json_encode con opzioni stabili → poi sha256
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
        return hash('sha256', $json);
    }

    /**
     * Ricalcola url ultimo PDF e flag dirty (vs last_pdf_payload_hash).
     */
    protected function refreshPdfState(): void
    {
        $checklist = $this->resolveChecklistModel();
        if (!$checklist) {
            $this->last_pdf_url = null;
            $this->pdf_dirty = true;
            return;
        }

        // URL ultimo PDF (se presente)
        $this->last_pdf_url = optional($checklist->lastPdf)->getUrl();

        // Dirty: confronto hash(payload attuale) vs ultimo salvato
        $payload = $this->buildPdfPayload($checklist);
        $current = $this->payloadHash($payload);
        $this->pdf_dirty = ($checklist->last_pdf_payload_hash !== $current);
    }

    /**
     * Utility: recupera il modello aggiornato da DB.
     */
    protected function resolveChecklistModel(): ?RentalChecklist
    {
        if (!$this->checklistId) return null;

        /** @var RentalChecklist|null $chk */
        $chk = RentalChecklist::query()->with('lastPdf')->find($this->checklistId);
        return $chk;
    }

    // ================== ACTION: Genera PDF ==================

    /**
     * Genera il PDF checklist, salva su Media Library e aggiorna hash/id.
     * Disabilitato se la checklist è "locked".
     */
    public function generatePdf(): void
    {
        $checklist = $this->resolveChecklistModel();
        if (!$checklist) {
            $this->dispatch('toast', ['type'=>'error','message'=>__('Salva prima la checklist.')]);
            return;
        }

        // Blocco modifica dopo firma (Opzione B)
        if ($checklist->isLocked()) {
            $this->dispatch('toast', ['type'=>'warning','message'=>__('Checklist bloccata: non è possibile rigenerare il PDF.')]);
            return;
        }

        // Costruzione payload+hash dai dati PERSISTITI
        $payload = $this->buildPdfPayload($checklist);
        $hash = $this->payloadHash($payload);

        // Se non ci sono cambiamenti ed esiste già un PDF → nothing to do
        if ($checklist->last_pdf_payload_hash === $hash && $checklist->lastPdf) {
            $this->last_pdf_url = $checklist->lastPdf->getUrl();
            $this->pdf_dirty = false;
            $this->dispatch('toast', ['type'=>'info','message'=>__('Nessuna modifica: PDF già aggiornato.')]);
            return;
        }

        // Render PDF (usa la stessa libreria dei contratti: DomPDF)
        // Prepara i dati minimi; il template Blade lo inseriremo nel prossimo step.
        $viewData = [
            'checklist' => $checklist->fresh(['rental','rental.vehicle','rental.customer']),
            'payload'   => $payload,
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('pdfs.checklist', $viewData)
                  ->setPaper('a4'); // portrait di default

        // Nome file coerente e stabile
        $fileName = sprintf('checklist-%s-%d-%s.pdf', $checklist->type, $checklist->id, now()->format('Ymd_His'));

        DB::transaction(function () use ($checklist, $pdf, $fileName, $hash) {
            // Salva su media library (collection "checklist_pdfs")
            $media = $checklist->addMediaFromString($pdf->output())
                ->usingFileName($fileName)
                ->toMediaCollection('checklist_pdfs');

            // Aggiorna stato "ultimo PDF"
            $checklist->forceFill([
                'last_pdf_payload_hash' => $hash,
                'last_pdf_media_id'     => $media->id,
            ])->save();
        });

        // Aggiorna stato UI
        $this->refreshPdfState();

        // Notifica
        $this->dispatch('toast', ['type'=>'success','message'=>__('PDF generato correttamente.')]);
    }

    // -------------------------------
    //   RENDER
    // -------------------------------

    public function render()
    {
        return view('livewire.rentals.checklist-form');
    }
}