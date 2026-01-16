<?php

namespace App\Livewire\Vehicles;

use App\Domain\Pricing\VehiclePricingService;
use App\Models\Vehicle;
use App\Models\VehiclePricelist;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Livewire\Component;
use App\Domain\Pricing\VehicleQuotePdfService;

class Pricing extends Component
{
    use AuthorizesRequests, WithPagination;

    protected $paginationTheme = 'tailwind';

    public Vehicle $vehicle;

    public bool $canEdit = true;

    // Sotto-tab del listino, salvate come ?pricing_tab=...
    // 'except' mantiene pulita la URL quando sei sulla default 'overview'.
    // Se vuoi vederlo SEMPRE in URL, rimuovi `except: 'overview'`.
    #[Url(as: 'pricing_tab', history: true, except: 'overview')]
    public string $subtab = 'overview';

    // listino corrente (attivo o bozza)
    public ?VehiclePricelist $pricelist = null;

    // impostazioni base
    public ?string $name = null;
    public string $currency = 'EUR';
    public float $base_daily_eur = 35.0;
    public int $weekend_pct = 0;
    public ?int $km_included_per_day = null;
    public ?float $extra_km_eur = null;
    public ?float $deposit_eur = null;
    public string $rounding = 'none';
    public ?string $notes = null;
    public ?float $second_driver_daily_eur = null;

    // simulatore
    public ?string $pickup_at = null;
    public ?string $dropoff_at = null;
    public int $expected_km = 0;
    public ?array $quote = null;

    // form stagioni
    public ?string $season_name = null;
    public ?string $season_start_date = null;
    public ?string $season_end_date = null;
    public int $season_pct = 0;
    public ?int $season_weekend_override = null;
    public int $season_priority = 0;

    // form tiers
    public ?string $tier_name = null;
    public int $tier_min_days = 1;
    public ?int $tier_max_days = null;
    public ?float $tier_override_daily_eur = null;
    public ?int $tier_discount_pct = null;
    public int $tier_priority = 0;

    // history
    public int $historyPerPage = 10;

    // computed “soft”: usato anche dalle viste
    public function getCanEditProperty(): bool
    {
        return $this->pricelist?->status === 'draft';
    }

    // Manteniamo la stessa API usata dalla Blade:
    public function setTab(string $tab): void
    {
        $this->subtab = $tab;
        $this->resetValidation();
        // niente resetPage() qui a meno che non vuoi resettare anche la paginazione storico ad ogni cambio sotto-tab
    }

    private function recomputeFlags(): void
    {
        $this->canEdit = (!$this->pricelist) || ($this->pricelist->status === 'draft');
    }

    public function mount(Vehicle $vehicle, VehiclePricingService $svc): void
    {
        $this->vehicle = $vehicle;
        if (!auth()->user()->can('vehicle_pricing.viewAny')) abort(403);

        // prova a caricare l’attiva del renter corrente
        $active = $svc->findActivePricelistForCurrentRenter($vehicle);

        if ($active) {
            $this->pricelist = $active;
        } else {
            $renterId = $this->currentRenterOrgId(); // può essere null
            $this->pricelist = VehiclePricelist::where('vehicle_id', $vehicle->id)
                ->when($renterId, fn($q,$rid) => $q->where('renter_org_id', $rid))
                ->orderByRaw("status = 'draft' DESC")
                ->orderByDesc('id')
                ->first();
        }

        if ($this->pricelist) {
            $pl = $this->pricelist;
            $this->fill($pl->only(['name','currency','weekend_pct','km_included_per_day','rounding','notes']));
            $this->base_daily_eur = $pl->base_daily_cents / 100;
            $this->extra_km_eur   = $pl->extra_km_cents !== null ? $pl->extra_km_cents / 100 : null;
            $this->deposit_eur    = $pl->deposit_cents !== null ? $pl->deposit_cents / 100 : null;
            $this->second_driver_daily_eur = $pl->second_driver_daily_cents !== null
                ? $pl->second_driver_daily_cents / 100
                : null;
        }

        $now = now();
        $this->pickup_at  = $now->format('Y-m-d\TH:00');
        $this->dropoff_at = $now->copy()->addDay()->format('Y-m-d\TH:00');

        $this->recomputeFlags();
    }

    // computed collections
    public function getSeasonsProperty() {
        return $this->pricelist?->seasons()->orderByDesc('priority')->get() ?? collect();
    }
    public function getTiersProperty() {
        return $this->pricelist?->tiers()->orderByDesc('priority')->get() ?? collect();
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable','string','max:128'],
            'currency' => ['required','in:EUR'],
            'base_daily_eur' => ['required','numeric','min:0'],
            'weekend_pct' => ['required','integer','min:0','max:100'],
            'km_included_per_day' => ['nullable','integer','min:0'],
            'extra_km_eur' => ['nullable','numeric','min:0'],
            'deposit_eur' => ['nullable','numeric','min:0'],
            'rounding' => ['required','in:none,up_1,up_5'],
            'notes' => ['nullable','string','max:255'],
            'second_driver_daily_eur' => ['nullable','numeric','min:0'],
        ];
    }

    private function currentRenterOrgId(): ?int
    {
        $now = now();

        $rid = DB::table('vehicle_assignments')
            ->where('vehicle_id', $this->vehicle->id)
            ->where('status', 'active')
            ->where('start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>', $now);
            })
            ->value('renter_org_id');

        if ($rid) {
            return (int) $rid;
        }

        // ⬇️ Fallback admin org se l'utente può gestire i non assegnati
        $user = auth()->user();
        if ($this->vehicle->admin_organization_id && $user && ($user->hasRole('admin') || $user->can('vehicle_pricing.manage_unassigned'))) {
            return (int) $this->vehicle->admin_organization_id;
        }

        return null;
    }

    /** Computed per la Blade: id org effettiva con cui stai lavorando (renter assegnato o admin org) */
    public function getEffectiveRenterOrgIdProperty(): ?int
    {
        return $this->currentRenterOrgId();
    }

    // CREA/AGGIORNA BOZZA (mai attiva qui)
    public function saveDraft(): void
    {
        $this->validate();
        if (!auth()->user()->can('vehicle_pricing.create') && !auth()->user()->can('vehicle_pricing.update')) abort(403);

        $renterOrgId = $this->currentRenterOrgId() ?? $this->pricelist?->renter_org_id;
        if (!$renterOrgId) { $this->dispatch('toast', type:'error', message:'Nessun renter disponibile su cui salvare la bozza.'); return; }

        $payload = [
            'vehicle_id' => $this->vehicle->id,
            'renter_org_id' => $renterOrgId,
            'name' => $this->name,
            'currency' => $this->currency,
            'base_daily_cents' => (int) round($this->base_daily_eur * 100),
            'weekend_pct' => (int) $this->weekend_pct,
            'km_included_per_day' => $this->km_included_per_day ?: null,
            'extra_km_cents' => $this->extra_km_eur !== null ? (int) round($this->extra_km_eur * 100) : null,
            'deposit_cents' => $this->deposit_eur !== null ? (int) round($this->deposit_eur * 100) : null,
            'rounding' => $this->rounding,
            'notes' => $this->notes,
            'status' => 'draft',
            'is_active' => 0,              // 👈 FIX
            'active_flag' => null,
            'second_driver_daily_cents' => $this->second_driver_daily_eur !== null
                ? (int) round($this->second_driver_daily_eur * 100)
                : null,
        ];

        if ($this->pricelist && $this->pricelist->status === 'draft') {
            $this->pricelist->update($payload);
        } else {
            $maxVer = VehiclePricelist::where('vehicle_id',$this->vehicle->id)
                ->where('renter_org_id',$renterOrgId)->max('version') ?? 0;
            $payload['version'] = $maxVer + 1;
            $this->pricelist = VehiclePricelist::create($payload);
        }

        $this->recomputeFlags();
        $this->dispatch('toast', type:'success', message:'Bozza salvata.');
    }

    // DUPLICA ATTIVA → BOZZA (robusta)
    public function duplicateActiveToDraft(): void
    {
        if (!auth()->user()->can('vehicle_pricing.create')) abort(403);

        // 1) Trova una versione di partenza “attiva”: preferisci quella aperta se è active.
        $active = null;

        if ($this->pricelist && $this->pricelist->status === 'active') {
            $active = $this->pricelist->loadMissing('seasons','tiers');
        }

        // se non era già quella aperta, cerca l’attiva per renter corrente o, in fallback, qualsiasi attiva del veicolo
        if (!$active) {
            $rid = $this->currentRenterOrgId();
            $q = VehiclePricelist::where('vehicle_id',$this->vehicle->id)->where('status','active');
            if ($rid) $q->where('renter_org_id',$rid);
            $active = $q->with(['seasons','tiers'])->first();
        }

        // se ancora nulla, usa comunque la versione aperta come sorgente (capita se stai visualizzando un’archiviata)
        if (!$active && $this->pricelist) {
            $active = $this->pricelist->loadMissing('seasons','tiers');
        }

        if (!$active) {
            $this->dispatch('toast', type:'error', message:'Nessuna versione di partenza da duplicare.');
            return;
        }

        // 2) Se esiste già una bozza per lo stesso renter, aprila
        $existingDraft = VehiclePricelist::where('vehicle_id',$this->vehicle->id)
            ->where('renter_org_id',$active->renter_org_id)
            ->where('status','draft')
            ->first();

        if ($existingDraft) {
            $this->pricelist = $existingDraft;
            $this->fill($existingDraft->only(['name','currency','weekend_pct','km_included_per_day','rounding','notes']));
            $this->base_daily_eur = $existingDraft->base_daily_cents / 100;
            $this->extra_km_eur   = $existingDraft->extra_km_cents !== null ? $existingDraft->extra_km_cents / 100 : null;
            $this->deposit_eur    = $existingDraft->deposit_cents !== null ? $existingDraft->deposit_cents / 100 : null;

            $this->recomputeFlags();
            $this->dispatch('toast', type:'info', message:'Esiste già una bozza: l’ho aperta.');
            $this->setTab('settings');
            return;
        }

        // 3) Duplica l’attiva
        $copy = $active->replicate([
            'status','active_flag','published_at','version','id','created_at','updated_at'
        ]);
        $copy->status = 'draft';
        $copy->active_flag = null;
        $copy->published_at = null;
        $copy->is_active = 0;   // 👈 FIX: la bozza NON deve risultare attiva

        $maxVer = VehiclePricelist::where('vehicle_id',$this->vehicle->id)
            ->where('renter_org_id',$active->renter_org_id)->max('version') ?? 0;
        $copy->version = $maxVer + 1;

        $copy->save();

        // duplica figli
        foreach ($active->seasons as $s) {
            $copy->seasons()->create($s->only([
                'name','start_mmdd','end_mmdd','season_pct','weekend_pct_override','priority','is_active'
            ]));
        }
        foreach ($active->tiers as $t) {
            $copy->tiers()->create($t->only([
                'name','min_days','max_days','override_daily_cents','discount_pct','priority','is_active'
            ]));
        }

        // 4) Apri la bozza
        $this->pricelist = $copy;
        $this->fill($copy->only(['name','currency','weekend_pct','km_included_per_day','rounding','notes']));
        $this->base_daily_eur = $copy->base_daily_cents / 100;
        $this->extra_km_eur   = $copy->extra_km_cents !== null ? $copy->extra_km_cents / 100 : null;
        $this->deposit_eur    = $copy->deposit_cents !== null ? $copy->deposit_cents / 100 : null;

        $this->recomputeFlags();
        $this->dispatch('toast', type:'success', message:'Bozza creata dalla versione di partenza.');
        $this->setTab('settings');
    }

    // PUBBLICA (attiva) la bozza corrente
    public function publish(): void
    {
        if (!auth()->user()->can('vehicle_pricing.publish')) abort(403);
        if (!$this->pricelist || $this->pricelist->status !== 'draft') {
            $this->dispatch('toast', type:'error', message:'Seleziona/salva prima una bozza.');
            return;
        }

        // usa sempre il renter del listino corrente (già coerente con l’assegnazione)
        $renterOrgId = $this->pricelist->renter_org_id ?? $this->currentRenterOrgId();
        if (!$renterOrgId) {
            $this->dispatch('toast', type:'error', message:'Nessun renter disponibile per la pubblicazione.');
            return;
        }

        DB::transaction(function () use ($renterOrgId) {
            // Spegni SOLO l’eventuale versione attiva corrente (active_flag=1)
            \App\Models\VehiclePricelist::where('vehicle_id', $this->vehicle->id)
                ->where('renter_org_id', $renterOrgId)
                ->where('active_flag', 1)
                ->update([
                    'is_active'   => 0,
                    'active_flag' => null,
                    'status'      => DB::raw("IF(status='active','archived',status)"),
                ]);

            // Accendi questa bozza
            $this->pricelist->update([
                'status'       => 'active',
                'is_active'    => 1,
                'active_flag'  => 1,         // chiave unica “parziale”
                'published_at' => now(),
                'renter_org_id'=> $renterOrgId,
            ]);
        });

        $this->recomputeFlags();
        $this->dispatch('toast', type:'success', message:'Versione pubblicata e attivata.');
        $this->setTab('overview');
    }

    // ARCHIVIA una versione (attiva o bozza)
    public function archive(int $id): void
    {
        if (!auth()->user()->can('vehicle_pricing.archive')) abort(403);

        $pl = VehiclePricelist::where('vehicle_id',$this->vehicle->id)->whereKey($id)->first();
        if (!$pl) return;

        $pl->update(['status'=>'archived','active_flag'=>null]);

        if ($this->pricelist && $this->pricelist->id === $id) {
            $this->pricelist = null;
        }

        $this->recomputeFlags();
        $this->dispatch('toast', type:'success', message:'Versione archiviata.');
    }

    public function openVersion(int $id): void
    {
        $pl = VehiclePricelist::where('vehicle_id',$this->vehicle->id)->whereKey($id)->first();
        if (!$pl) return;

        $this->pricelist = $pl;
        $this->fill($pl->only(['name','currency','weekend_pct','km_included_per_day','rounding','notes']));
        $this->base_daily_eur = $pl->base_daily_cents / 100;
        $this->extra_km_eur   = $pl->extra_km_cents !== null ? $pl->extra_km_cents / 100 : null;
        $this->deposit_eur    = $pl->deposit_cents !== null ? $pl->deposit_cents / 100 : null;
        $this->second_driver_daily_eur = $pl->second_driver_daily_cents !== null
            ? $pl->second_driver_daily_cents / 100
            : null;

        $this->recomputeFlags();
        $this->dispatch('toast', type:'info', message:'Versione caricata.');
        $this->setTab('overview');
    }

    // Stagioni
    public function addSeason(): void
    {
        if (!$this->pricelist || $this->pricelist->status !== 'draft') {
            $this->dispatch('toast', type:'error', message:'Apri/crea una bozza per aggiungere stagioni.');
            return;
        }

        $this->validate([
            'season_name' => ['required','string','max:64'],
            'season_start_date' => ['required','date'],
            'season_end_date' => ['required','date'],
            'season_pct' => ['required','integer','between:-100,100'],
            'season_weekend_override' => ['nullable','integer','between:0,100'],
            'season_priority' => ['integer','between:-128,127'],
        ]);

        $from = \Carbon\Carbon::parse($this->season_start_date)->format('m-d');
        $to   = \Carbon\Carbon::parse($this->season_end_date)->format('m-d');

        $this->pricelist->seasons()->create([
            'name' => $this->season_name,
            'start_mmdd' => $from,
            'end_mmdd' => $to,
            'season_pct' => (int)$this->season_pct,
            'weekend_pct_override' => $this->season_weekend_override !== null ? (int)$this->season_weekend_override : null,
            'priority' => (int)$this->season_priority,
            'is_active' => true,
        ]);

        $this->reset(['season_name','season_start_date','season_end_date','season_pct','season_weekend_override','season_priority']);
        $this->dispatch('toast', type:'success', message:'Stagione aggiunta.');
    }

    public function deleteSeason(int $id): void
    {
        if (!$this->pricelist || $this->pricelist->status !== 'draft') return;
        $this->pricelist->seasons()->whereKey($id)->delete();
    }

    // Tiers
    public function addTier(): void
    {
        if (!$this->pricelist || $this->pricelist->status !== 'draft') {
            $this->dispatch('toast', type:'error', message:'Apri/crea una bozza per aggiungere tiers.');
            return;
        }

        $this->validate([
            'tier_min_days' => ['required','integer','min:1'],
            'tier_max_days' => ['nullable','integer','min:1'],
            'tier_override_daily_eur' => ['nullable','numeric','min:0'],
            'tier_discount_pct' => ['nullable','integer','between:0,100'],
            'tier_priority' => ['integer','between:-128,127'],
            'tier_name' => ['nullable','string','max:64'],
        ]);

        if (is_null($this->tier_override_daily_eur) && is_null($this->tier_discount_pct)) {
            $this->addError('tier_override_daily_eur', 'Indica override giornaliero oppure sconto %.');
            return;
        }

        $this->pricelist->tiers()->create([
            'name' => $this->tier_name,
            'min_days' => (int)$this->tier_min_days,
            'max_days' => $this->tier_max_days ? (int)$this->tier_max_days : null,
            'override_daily_cents' => $this->tier_override_daily_eur !== null ? (int) round($this->tier_override_daily_eur*100) : null,
            'discount_pct' => $this->tier_discount_pct !== null ? (int)$this->tier_discount_pct : null,
            'priority' => (int)$this->tier_priority,
            'is_active' => true,
        ]);

        $this->reset(['tier_name','tier_min_days','tier_max_days','tier_override_daily_eur','tier_discount_pct','tier_priority']);
        $this->dispatch('toast', type:'success', message:'Tier aggiunto.');
    }

    public function deleteTier(int $id): void
    {
        if (!$this->pricelist || $this->pricelist->status !== 'draft') return;
        $this->pricelist->tiers()->whereKey($id)->delete();
    }

    // parsing datetime-local
    private function parseDateTimeLocal(?string $value): ?DateTimeImmutable
    {
        if (!$value) return null;
        $tz = new DateTimeZone('Europe/Rome');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', trim($value), $tz);
        return $dt ?: null;
    }

    public function calc(VehiclePricingService $svc): void
    {
        if (!$this->pricelist) {
            $this->dispatch('toast', type:'error', message:'Nessun listino selezionato.');
            return;
        }

        $pickup  = $this->parseDateTimeLocal($this->pickup_at);
        $dropoff = $this->parseDateTimeLocal($this->dropoff_at);

        if (!$pickup) { $this->addError('pickup_at','Formato non valido.'); return; }
        if (!$dropoff || $dropoff <= $pickup) { $this->addError('dropoff_at','Riconsegna successiva al ritiro.'); return; }

        $this->quote = $svc->quote($this->pricelist, $pickup, $dropoff, (int)$this->expected_km);
        $this->dispatch('toast', type:'success', message:'Calcolo effettuato.');
    }

    /**
     * Genera e scarica il PDF del preventivo usando la quote già calcolata.
     * NON ricalcola nulla: si basa su $this->quote.
     */
    public function printQuote(VehicleQuotePdfService $pdfSvc)
    {
        // Autorizzazione coerente con l’accesso alla pagina listini
        if (!auth()->user()->can('vehicle_pricing.viewAny')) {
            abort(403);
        }

        // Serve un listino e una quote già calcolata
        if (!$this->pricelist) {
            $this->dispatch('toast', type: 'error', message: 'Nessun listino selezionato.');
            return null;
        }

        if (!$this->quote) {
            $this->dispatch('toast', type: 'error', message: 'Calcola prima il preventivo.');
            return null;
        }

        // Recupero le date dal form (solo per intestazione/nome file PDF)
        $pickup  = $this->parseDateTimeLocal($this->pickup_at);
        $dropoff = $this->parseDateTimeLocal($this->dropoff_at);

        if (!$pickup || !$dropoff || $dropoff <= $pickup) {
            $this->dispatch('toast', type: 'error', message: 'Periodo non valido per la stampa.');
            return null;
        }

        /**
         * ✅ PDF basato sulla quote già presente.
         * Nota: il service esclude campi interni (margini, costo L/T, ecc.)
         */
        $binary = $pdfSvc->render(
            $this->vehicle,
            $this->pricelist,
            $pickup,
            $dropoff,
            (int) $this->expected_km,
            $this->quote
        );

        $filename = $pdfSvc->filename($this->vehicle, $pickup, $dropoff);

        /**
         * Livewire (v3) può ritornare una Response per effettuare il download.
         * StreamDownload evita di salvare file temporanei sul server.
         */
        return response()->streamDownload(
            function () use ($binary) {
                echo $binary;
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    public function render()
    {
        $history = VehiclePricelist::where('vehicle_id',$this->vehicle->id)
            ->when($this->currentRenterOrgId(), fn($q,$renterId) => $q->where('renter_org_id',$renterId))
            ->orderByRaw("FIELD(status,'active','draft','archived')")
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($this->historyPerPage);

        return view('livewire.vehicles.pricing', [
            'history' => $history,
        ]);
    }
}
