<div class="space-y-6">
    @php
        $fmt = fn($cents) => number_format($cents/100, 2, ',', '.').' €';
    @endphp

    @php $hasRenter = app('db')->table('vehicle_assignments')
        ->where('vehicle_id', $this->vehicle->id)
        ->where('status','active')
        ->where('start_at','<=', now())
        ->where(fn($q)=>$q->whereNull('end_at')->orWhere('end_at','>',now()))
        ->exists(); @endphp

    @unless($hasRenter)
        <div class="rounded border border-amber-300 bg-amber-50 p-4 text-amber-800">
            Nessun renter attivo per questo veicolo: assegna il veicolo per poter creare/modificare il listino.
        </div>
    @endunless

    <div class="rounded-lg border p-4 bg-white dark:bg-gray-800 dark:border-gray-700">
        <h3 class="font-semibold mb-3">Impostazioni listino (renter corrente)</h3>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium">Nome (opz.)</label>
                <input type="text" wire:model.defer="name" class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                @error('name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Valuta</label>
                <select wire:model="currency" class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    <option value="EUR">EUR</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Tariffa base giornaliera (€)</label>
                <input type="number" step="1" min="0" wire:model.lazy="base_daily_cents"
                       x-on:blur="$wire.base_daily_cents = parseInt(($event.target.value||0)*100)"
                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="es. 35">
                <p class="text-xs text-gray-500 mt-1">Inserisci l’importo in euro, verrà salvato in centesimi.</p>
                @error('base_daily_cents')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium">% weekend (sa/do)</label>
                <input type="number" step="1" min="0" max="100" wire:model.lazy="weekend_pct"
                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                @error('weekend_pct')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Km inclusi al giorno</label>
                <input type="number" min="0" wire:model.lazy="km_included_per_day"
                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                @error('km_included_per_day')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Costo km extra (€)</label>
                <input type="number" step="0.01" min="0"
                       x-on:blur="$wire.extra_km_cents = parseInt( (parseFloat($event.target.value||0)*100).toFixed(0) )"
                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="es. 0.20">
                @error('extra_km_cents')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Cauzione (€)</label>
                <input type="number" step="1" min="0"
                       x-on:blur="$wire.deposit_cents = parseInt(($event.target.value||0)*100)"
                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="facoltativo">
                @error('deposit_cents')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Arrotondamento</label>
                <select wire:model="rounding" class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    <option value="none">Nessuno</option>
                    <option value="up_1">Al € superiore</option>
                    <option value="up_5">Al 5€ superiore</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <input id="pl_active" type="checkbox" wire:model="is_active"
                       class="rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                <label for="pl_active" class="text-sm">Listino attivo</label>
            </div>
        </div>

        <div class="mt-4 flex gap-2">
            @canany(['vehicle_pricing.create','vehicle_pricing.update'])
                <button wire:click="save"
                        class="inline-flex h-9 items-center rounded-md bg-slate-800 px-3 text-white hover:bg-slate-900">
                    Salva listino
                </button>
            @endcanany
        </div>
    </div>

    <div class="rounded-lg border p-4 bg-white dark:bg-gray-800 dark:border-gray-700">
        <h3 class="font-semibold mb-3">Anteprima</h3>
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium">Ritiro</label>
                <input type="datetime-local" wire:model="pickup_at"
                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium">Riconsegna</label>
                <input type="datetime-local" wire:model="dropoff_at"
                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium">Km previsti</label>
                <input type="number" min="0" wire:model="expected_km"
                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
            </div>
            <div class="flex items-end">
                <button wire:click="calc"
                        class="inline-flex h-9 items-center rounded-md bg-slate-800 px-3 text-white hover:bg-slate-900">
                    Calcola
                </button>
            </div>
        </div>

        @if($quote)
            <div class="mt-4 grid sm:grid-cols-5 gap-4 text-sm">
                <div><span class="text-gray-500">Giorni</span><div class="font-medium">{{ $quote['days'] }}</div></div>
                <div><span class="text-gray-500">Quota giorni</span><div class="font-medium">{{ $fmt($quote['daily_total']) }}</div></div>
                <div><span class="text-gray-500">Km extra</span><div class="font-medium">{{ $fmt($quote['km_extra']) }}</div></div>
                <div><span class="text-gray-500">Cauzione</span><div class="font-medium">{{ $fmt($quote['deposit']) }}</div></div>
                <div><span class="text-gray-500">Totale</span><div class="font-semibold">{{ $fmt($quote['total']) }}</div></div>
            </div>
        @endif
    </div>
</div>
