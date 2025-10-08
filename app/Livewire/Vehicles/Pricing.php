<?php

namespace App\Livewire\Vehicles;

use App\Domain\Pricing\VehiclePricingService;
use App\Models\Vehicle;
use App\Models\VehiclePricelist;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Pricing extends Component
{
    use AuthorizesRequests;

    // il veicolo di cui stiamo gestendo il listino
    public Vehicle $vehicle;

    // form state
    public ?VehiclePricelist $pricelist = null;
    public $name, $currency='EUR';
    public $base_daily_cents, $weekend_pct=0;
    public $km_included_per_day, $extra_km_cents;
    public $deposit_cents, $rounding='none';
    public $is_active = true;

    // preview
    public $pickup_at, $dropoff_at, $expected_km = 0;
    public ?array $quote = null;

    // mount con il veicolo
    public function mount(Vehicle $vehicle, VehiclePricingService $svc)
    {
        $this->vehicle = $vehicle;

        // autorizzazione a visualizzare l’area listino
        if (!auth()->user()->can('vehicle_pricing.viewAny')) {
            abort(403);
        }

        $pl = $svc->findActivePricelistForCurrentRenter($vehicle);
        $this->pricelist = $pl;

        if ($pl) {
            $this->fill($pl->only([
                'name','currency','base_daily_cents','weekend_pct',
                'km_included_per_day','extra_km_cents','deposit_cents',
                'rounding','is_active',
            ]));
        } else {
            // valori di default per un nuovo listino
            $this->base_daily_cents = 3500;
            $this->weekend_pct = 0;
            $this->rounding = 'none';
            $this->is_active = true;
        }

        $now = now();
        $this->pickup_at  = $now->format('Y-m-d\TH:00');
        $this->dropoff_at = $now->copy()->addDay()->format('Y-m-d\TH:00');
    }

    // regole di validazione
    public function rules(): array
    {
        return [
            'name' => ['nullable','string','max:128'],
            'currency' => ['required','in:EUR'],
            'base_daily_cents' => ['required','integer','min:0'],
            'weekend_pct' => ['required','integer','min:0','max:100'],
            'km_included_per_day' => ['nullable','integer','min:0'],
            'extra_km_cents' => ['nullable','integer','min:0'],
            'deposit_cents' => ['nullable','integer','min:0'],
            'rounding' => ['required','in:none,up_1,up_5'],
            'is_active' => ['boolean'],
        ];
    }

    // trova l’organizzazione renter corrente (se esiste)
    private function currentRenterOrgId(): ?int
    {
        $now = now();
        return DB::table('vehicle_assignments')
            ->where('vehicle_id', $this->vehicle->id)
            ->where('status', 'active')
            ->where('start_at', '<=', $now)
            ->where(function($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at','>',$now);
            })
            ->value('renter_org_id');
    }

    // salva il listino (create o update)
    public function save()
    {
        $this->validate();

        // serve update/create?
        $canUpdate = auth()->user()->can('vehicle_pricing.update');
        $canCreate = auth()->user()->can('vehicle_pricing.create');

        if (!$canUpdate && !$canCreate) abort(403);

        $renterOrgId = $this->currentRenterOrgId();
        if (!$renterOrgId) {
            $this->dispatchBrowserEvent('notify', ['type'=>'error','message'=>'Nessun renter attivo: assegna il veicolo prima di creare un listino.']);
            return;
        }

        if ($this->pricelist) {
            if (!$canUpdate) abort(403);
            $this->pricelist->update([
                'name' => $this->name,
                'currency' => $this->currency,
                'base_daily_cents' => (int) $this->base_daily_cents,
                'weekend_pct' => (int) $this->weekend_pct,
                'km_included_per_day' => $this->km_included_per_day ? (int)$this->km_included_per_day : null,
                'extra_km_cents' => $this->extra_km_cents ? (int)$this->extra_km_cents : null,
                'deposit_cents' => $this->deposit_cents ? (int)$this->deposit_cents : null,
                'rounding' => $this->rounding,
                'is_active' => (bool) $this->is_active,
            ]);
        } else {
            if (!$canCreate) abort(403);

            // opzionale: disattiva eventuali altri attivi per stesso (vehicle, renter)
            VehiclePricelist::where('vehicle_id',$this->vehicle->id)
                ->where('renter_org_id',$renterOrgId)
                ->update(['is_active'=>false]);

            $this->pricelist = VehiclePricelist::create([
                'vehicle_id' => $this->vehicle->id,
                'renter_org_id' => $renterOrgId,
                'name' => $this->name,
                'currency' => $this->currency,
                'base_daily_cents' => (int) $this->base_daily_cents,
                'weekend_pct' => (int) $this->weekend_pct,
                'km_included_per_day' => $this->km_included_per_day ? (int)$this->km_included_per_day : null,
                'extra_km_cents' => $this->extra_km_cents ? (int)$this->extra_km_cents : null,
                'deposit_cents' => $this->deposit_cents ? (int)$this->deposit_cents : null,
                'rounding' => $this->rounding,
                'is_active' => (bool) $this->is_active,
                'published_at' => now(),
            ]);
        }

        $this->dispatchBrowserEvent('notify', ['type'=>'success','message'=>'Listino salvato.']);
    }
    
    // calcola il preventivo
    public function calc(VehiclePricingService $svc)
    {
        if (!$this->pricelist) {
            $this->dispatchBrowserEvent('notify', ['type'=>'error','message'=>'Salva il listino prima di calcolare.']);
            return;
        }
        $this->quote = $svc->quote(
            $this->pricelist,
            new \DateTimeImmutable($this->pickup_at),
            new \DateTimeImmutable($this->dropoff_at),
            (int) $this->expected_km
        );
    }

    public function render()
    {
        return view('livewire.vehicles.pricing');
    }
}
