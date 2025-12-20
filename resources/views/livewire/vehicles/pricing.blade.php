<div class="space-y-6">
    @php
        $fmt = fn($cents) => number_format($cents/100, 2, ',', '.').' €';
        $canPublish = auth()->user()->can('vehicle_pricing.publish');
        $canArchive = auth()->user()->can('vehicle_pricing.archive');
        $canCreate  = auth()->user()->can('vehicle_pricing.create');
        $canUpdate  = auth()->user()->can('vehicle_pricing.update');
    @endphp

    {{-- NAV SOTTO-TAB --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex gap-6 text-sm">
            @foreach(['overview'=>'Panoramica','settings'=>'Impostazioni','seasons'=>'Stagioni','tiers'=>'Tiers','simulator'=>'Simulatore','history'=>'Storico'] as $k=>$label)
                <button type="button" wire:click="setTab('{{ $k }}')"
                        class="px-2 pb-2 border-b-2 focus:outline-none {{ $subtab===$k ? 'border-slate-800 text-slate-900 dark:text-white' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- AVVISI / CONTESTO RENTER --}}
    @php
        $effectiveRenterId = $this->effectiveRenterOrgId;
        $isAdminFallback = $effectiveRenterId && ($effectiveRenterId === $this->vehicle->admin_organization_id);
    @endphp

    @unless($effectiveRenterId)
        <div class="rounded border border-amber-300 bg-amber-50 p-4 text-amber-800">
            Nessun renter attivo per questo veicolo e nessuna gestione diretta disponibile con le tue autorizzazioni.
            Contatta un amministratore per abilitare la gestione.
        </div>
    @else
        @if($isAdminFallback)
            <div class="rounded border border-emerald-300 bg-emerald-50 p-3 text-emerald-800 text-sm">
                Veicolo non assegnato: stai gestendo il listino come <b>organizzazione proprietaria</b>.
            </div>
        @endif
    @endunless

    {{-- ======================== OVERVIEW ======================== --}}
    @if($subtab==='overview')
        <div class="rounded-lg border p-4 bg-white dark:bg-gray-800 dark:border-gray-700 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm">
                    <div class="font-semibold">
                        {{ $pricelist?->name ?: 'Listino corrente' }}
                        @if($pricelist)
                            <span class="ml-2 inline-flex items-center rounded bg-gray-100 dark:bg-gray-900 px-2 py-0.5 text-xs">
                                Ver. {{ $pricelist->version }} · {{ strtoupper($pricelist->status_label ?? $pricelist->status) }}
                            </span>
                            @if($pricelist->status === 'active')
                                <span class="ml-2 inline-flex items-center rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs">ATTIVO</span>
                            @elseif($pricelist->status === 'draft')
                                <span class="ml-2 inline-flex items-center rounded bg-amber-100 text-amber-800 px-2 py-0.5 text-xs">BOZZA</span>
                            @else
                                <span class="ml-2 inline-flex items-center rounded bg-gray-200 text-gray-700 px-2 py-0.5 text-xs">ARCHIVIATO</span>
                            @endif
                        @endif
                    </div>
                    <div class="mt-1 text-gray-500">
                        @if($pricelist?->published_at)
                            Pubblicato il {{ $pricelist->published_at->format('d/m/Y H:i') }}
                        @endif
                        @if($pricelist?->notes)
                            · Note: {{ $pricelist->notes }}
                        @endif
                    </div>
                </div>
                <div class="flex gap-2">
                    @if($pricelist)
                        @if($canCreate && $effectiveRenterId)
                            <button type="button" wire:click="duplicateActiveToDraft"
                                    class="rounded-md bg-slate-800 px-3 py-1.5 text-white hover:bg-slate-900">
                                Duplica attiva → bozza
                            </button>
                        @endif
                        @if($pricelist && $pricelist->status==='draft' && $canPublish)
                            <button type="button" wire:click="publish"
                                    class="rounded-md bg-emerald-700 px-3 py-1.5 text-white hover:bg-emerald-800">
                                Pubblica
                            </button>
                        @endif
                    @endif
                </div>
            </div>

            {{-- RIEPILOGO BADGE --}}
            @if($pricelist)
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-900 px-2 py-1">
                        Base: {{ number_format($pricelist->base_daily_cents/100,2,',','.') }} €
                    </span>
                    <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-900 px-2 py-1">
                        Weekend: {{ $pricelist->weekend_pct }}%
                    </span>
                    <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-900 px-2 py-1">
                        Km/giorno: {{ $pricelist->km_included_per_day ?? '—' }}
                    </span>
                    <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-900 px-2 py-1">
                        Km extra: {{ $pricelist->extra_km_cents ? number_format($pricelist->extra_km_cents/100,2,',','.') : '—' }} €/km
                    </span>
                    <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-900 px-2 py-1">
                        Cauzione: {{ $pricelist->deposit_cents ? number_format($pricelist->deposit_cents/100,0,',','.') : '—' }} €
                    </span>
                    <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-900 px-2 py-1">
                        Rounding: {{ $pricelist->rounding }}
                    </span>
                </div>
            @else
                <div class="mb-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                    Nessun listino trovato. Crea una bozza di listino nelle impostazioni.
                </div>
            @endif
        </div>
    @endif

    {{-- ======================== SETTINGS ======================== --}}
    @if($subtab==='settings')
        <div class="rounded-lg border p-4 bg-white dark:bg-gray-800 dark:border-gray-700 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold">Impostazioni di base</h3>
                <div class="flex gap-2">
                    @if($this->canEdit && ($canCreate || $canUpdate))
                        <button type="button" wire:click="saveDraft"
                                class="rounded-md bg-slate-800 px-3 py-1.5 text-white hover:bg-slate-900">
                            Salva bozza
                        </button>
                    @endif
                    @if($this->canEdit===false && $canCreate && $effectiveRenterId)
                        <button type="button" wire:click="duplicateActiveToDraft"
                                class="rounded-md border px-3 py-1.5 text-slate-800 dark:text-white">
                            Crea bozza da attiva
                        </button>
                    @endif
                    @if($this->canEdit && $canPublish)
                        <button type="button" wire:click="publish"
                                class="rounded-md bg-emerald-700 px-3 py-1.5 text-white hover:bg-emerald-800">
                            Pubblica
                        </button>
                    @endif
                </div>
            </div>

            @if(!$this->canEdit)
                <div class="mb-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                    Questa versione non è una bozza. Duplica l’attiva per creare una bozza modificabile.
                </div>
            @endif

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium">Nome (opz.)</label>
                    <input type="text" wire:model.defer="name" @disabled(!$this->canEdit)
                           class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @error('name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium">Valuta</label>
                    <select wire:model="currency" @disabled(!$this->canEdit)
                            class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                        <option value="EUR">EUR</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium">Tariffa base giornaliera (€)</label>
                    <input type="number" step="0.01" min="0" wire:model.lazy="base_daily_eur" @disabled(!$this->canEdit)
                           class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="es. 35">
                    <p class="text-xs text-gray-500 mt-1">Inserisci l’importo in euro, verrà salvato in centesimi.</p>
                    @error('base_daily_eur')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium">% weekend (sa/do)</label>
                    <input type="number" step="1" min="0" max="100" wire:model.lazy="weekend_pct" @disabled(!$this->canEdit)
                           class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @error('weekend_pct')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium">Km inclusi al giorno</label>
                    <input type="number" min="0" wire:model.lazy="km_included_per_day" @disabled(!$this->canEdit)
                           class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @error('km_included_per_day')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium">Costo km extra (€)</label>
                    <input type="number" step="0.01" min="0" wire:model.lazy="extra_km_eur" @disabled(!$this->canEdit)
                           class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="es. 0.20">
                    @error('extra_km_eur')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium">Cauzione (€)</label>
                    <input type="number" step="1" min="0" wire:model.lazy="deposit_eur" @disabled(!$this->canEdit)
                           class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="facoltativo">
                    @error('deposit_eur')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium">Arrotondamento</label>
                    <select wire:model="rounding" @disabled(!$this->canEdit)
                            class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                        <option value="none">Nessuno</option>
                        <option value="up_1">Al € superiore</option>
                        <option value="up_5">Al 5€ superiore</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium">Note (opz.)</label>
                    <textarea wire:model.defer="notes" rows="2" @disabled(!$this->canEdit)
                              class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700"></textarea>
                </div>
            </div>
        </div>
    @endif

    {{-- ======================== SEASONS ======================== --}}
    @if($subtab ==='seasons')
        <div class="rounded-lg border p-4 bg-white dark:bg-gray-800 dark:border-gray-700">
            <h3 class="font-semibold mb-1">Stagioni</h3>
            <p class="text-xs text-gray-500 mb-3">
                Questa sezione serve ad impostare un <strong>listino stagionale</strong> che modifica la tariffa base:
                puoi applicare una variazione percentuale (±%) e, se vuoi, anche un <em>override</em> della percentuale weekend
                solo per il periodo indicato. In caso di sovrapposizioni, vince la <strong>priorità</strong> più alta.
            </p>

            @if(!$this->canEdit)
                <div class="mb-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                    Modifica una bozza per aggiungere o rimuovere stagioni.
                </div>
            @endif

            <div class="grid sm:grid-cols-6 gap-3">
                <div class="sm:col-span-1">
                    <label class="block text-xs text-gray-600 mb-1">Nome</label>
                    <input type="text" wire:model.defer="season_name" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="Alta">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Inizio</label>
                    <input type="date" wire:model.defer="season_start_date" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Fine</label>
                    <input type="date" wire:model.defer="season_end_date" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">±% stagione</label>
                    <input type="number" step="1" wire:model.defer="season_pct" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="+15">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Weekend % (override)</label>
                    <input type="number" step="1" min="0" max="100" wire:model.defer="season_weekend_override" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="opzionale">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Priorità</label>
                    <input type="number" step="1" wire:model.defer="season_priority" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" placeholder="0">
                </div>
            </div>
            <div class="mt-3">
                <button type="button" wire:click="addSeason" @disabled(!$this->canEdit)
                        class="inline-flex h-9 items-center rounded-md bg-slate-800 px-3 text-white hover:bg-slate-900 disabled:opacity-50">
                    Aggiungi stagione
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1">Nome</th>
                            <th>Periodo</th>
                            <th>±%</th>
                            <th>Weekend%</th>
                            <th>Prio</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($this->seasons as $s)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="py-1">{{ $s->name }}</td>
                            <td>{{ $s->start_mmdd }} → {{ $s->end_mmdd }}</td>
                            <td>{{ $s->season_pct }}</td>
                            <td>{{ $s->weekend_pct_override ?? '—' }}</td>
                            <td>{{ $s->priority }}</td>
                            <td>
                                <button type="button" wire:click="deleteSeason({{ $s->id }})" @disabled(!$this->canEdit)
                                        class="text-red-600 hover:underline disabled:opacity-50">Elimina</button>
                            </td>
                        </tr>
                    @endforeach
                    @if($this->seasons->isEmpty())
                        <tr>
                            <td colspan="6" class="py-4 text-center text-gray-500">Nessuna stagione trovata.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ======================== TIERS ======================== --}}
    @if($subtab ==='tiers')
        <div class="rounded-lg border p-4 bg-white dark:bg-gray-800 dark:border-gray-700">
            <h3 class="font-semibold mb-1">Fasce di durata (Tiers)</h3>
            <p class="text-xs text-gray-500 mb-3">
                Questa sezione imposta degli <strong>scalini di prezzo per durata</strong>:
                per ogni fascia di giorni puoi definire un <strong>override €/giorno</strong> (ignora weekend/stagioni)
                <em>oppure</em> uno <strong>sconto %</strong> sul totale. In caso di sovrapposizione, prevale la
                <strong>priorità</strong> più alta.
            </p>

            @if(!$this->canEdit)
                <div class="mb-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                    Modifica una bozza per aggiungere o rimuovere tiers.
                </div>
            @endif

            <div class="grid sm:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Nome (opz.)</label>
                    <input type="text" wire:model.defer="tier_name" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Min giorni</label>
                    <input type="number" step="1" wire:model.defer="tier_min_days" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Max giorni (opz.)</label>
                    <input type="number" step="1" wire:model.defer="tier_max_days" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Override €/giorno (opz.)</label>
                    <input type="number" step="0.01" wire:model.defer="tier_override_daily_eur" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Sconto % (opz.)</label>
                    <input type="number" step="1" wire:model.defer="tier_discount_pct" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Priorità</label>
                    <input type="number" step="1" wire:model.defer="tier_priority" @disabled(!$this->canEdit)
                           class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                </div>
            </div>
            <div class="mt-3">
                <button type="button" wire:click="addTier" @disabled(!$this->canEdit)
                        class="inline-flex h-9 items-center rounded-md bg-slate-800 px-3 text-white hover:bg-slate-900 disabled:opacity-50">
                    Aggiungi tier
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1">Nome</th>
                            <th>Giorni</th>
                            <th>Override €/g</th>
                            <th>Sconto %</th>
                            <th>Prio</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($this->tiers as $t)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="py-1">{{ $t->name ?? '—' }}</td>
                            <td>{{ $t->min_days }}–{{ $t->max_days ?? '∞' }}</td>
                            <td>{{ $t->override_daily_cents ? number_format($t->override_daily_cents/100,2,',','.') : '—' }}</td>
                            <td>{{ $t->discount_pct ?? '—' }}</td>
                            <td>{{ $t->priority }}</td>
                            <td>
                                <button type="button" wire:click="deleteTier({{ $t->id }})" @disabled(!$this->canEdit)
                                        class="text-red-600 hover:underline disabled:opacity-50">Elimina</button>
                            </td>
                        </tr>
                    @endforeach
                    @if($this->tiers->isEmpty())
                        <tr>
                            <td colspan="6" class="py-4 text-center text-gray-500">Nessun tier trovato.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ======================== SIMULATOR ======================== --}}
    @if($subtab==='simulator')
        <div class="rounded-lg border p-4 bg-white dark:bg-gray-800 dark:border-gray-700">
            <h3 class="font-semibold mb-1">Simulatore dettagliato</h3>
            <p class="text-xs text-gray-500 mb-3">
                Inserisci periodo e km previsti per ottenere un <strong>preventivo</strong> secondo il listino selezionato
                (base + stagioni + weekend + eventuali tiers). Il pulsante <em>Stampa</em> creerà in futuro un layout
                adatto da condividere con il cliente.
            </p>

            <div class="grid sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium">Ritiro</label>
                    <input type="datetime-local" wire:model="pickup_at"
                           class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @error('pickup_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">Riconsegna</label>
                    <input type="datetime-local" wire:model="dropoff_at"
                           class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                    @error('dropoff_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">Km previsti</label>
                    <input type="number" min="0" wire:model="expected_km"
                           class="mt-1 w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                </div>
                <div class="flex items-end gap-2">
                    <button type="button" wire:click="calc"
                            class="inline-flex h-9 items-center rounded-md bg-slate-800 px-3 text-white hover:bg-slate-900">
                        Calcola
                    </button>
                    <button type="button" disabled title="Layout stampa in sviluppo"
                            class="inline-flex h-9 items-center rounded-md border px-3 text-gray-600 dark:text-gray-300">
                        Stampa
                    </button>
                </div>
            </div>

            @if($quote)
                <div class="mt-4 grid sm:grid-cols-8 gap-4 text-sm">
                    <div><span class="text-gray-500">Giorni</span><div class="font-medium">{{ $quote['days'] }}</div></div>
                    <div><span class="text-gray-500">Quota giorni</span><div class="font-medium">{{ $fmt($quote['daily_total']) }}</div></div>
                    <div><span class="text-gray-500">Km extra</span><div class="font-medium">{{ $fmt($quote['km_extra']) }}</div></div>
                    <div><span class="text-gray-500">Cauzione</span><div class="font-medium">{{ $fmt($quote['deposit']) }}</div></div>
                    <div><span class="text-gray-500">Totale</span><div class="font-semibold">{{ $fmt($quote['total']) }}</div></div>

                    {{-- Nuovi indicatori economici --}}
                    <div><span class="text-gray-500">Costo L/T (€/g)</span><div class="font-medium">{{ $fmt($quote['lt_daily_cost']) }}</div></div>
                    <div><span class="text-gray-500">Prezzo medio /g</span><div class="font-medium">{{ $fmt($quote['avg_daily_price']) }}</div></div>
                    <div>
                        <span class="text-gray-500">Margine €/g</span>
                        <div class="font-semibold @if(($quote['net_daily_after_lt'] ?? 0) < 0) text-rose-600 @endif">
                            {{ $fmt($quote['net_daily_after_lt']) }}
                        </div>
                    </div>
                </div>

                {{-- se vuoi, sotto puoi mostrare anche il margine complessivo --}}
                <div class="mt-2 text-xs text-gray-500">
                    Margine totale dopo L/T: <strong>{{ $fmt($quote['net_total_after_lt']) }}</strong>
                </div>
                @if(!empty($quote['tier']))
                    <div class="mt-2 text-xs text-gray-500">
                        Tier applicato:
                        @if($quote['tier']['override_daily_cents'] ?? null)
                            override {{ number_format($quote['tier']['override_daily_cents']/100,2,',','.') }} €/g
                        @elseif($quote['tier']['discount_pct'] ?? null)
                            sconto {{ $quote['tier']['discount_pct'] }} %
                        @endif
                        @if($quote['tier']['name'] ?? null) — {{ $quote['tier']['name'] }} @endif
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- ======================== HISTORY ======================== --}}
    @if($subtab==='history')
        <div class="rounded-lg border p-4 bg-white dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold">Storico versioni</h3>
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-gray-500">per pagina</span>
                    <select wire:model="historyPerPage" class="rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700">
                        @foreach([10,20,50] as $pp)
                            <option value="{{ $pp }}">{{ $pp }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1">Ver.</th>
                            <th>Stato</th>
                            <th>Pubblicato</th>
                            <th>Nome</th>
                            <th>Base €/g</th>
                            <th>Weekend%</th>
                            <th>Arrotondamento</th>
                            <th>Note</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($history as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="py-1">{{ $row->version }}</td>
                            <td class="uppercase">{{ $row->status_label ?? $row->status }}</td>
                            <td>{{ $row->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $row->name ?? '—' }}</td>
                            <td>{{ number_format($row->base_daily_cents/100,2,',','.') }}</td>
                            <td>{{ $row->weekend_pct }}%</td>
                            <td>{{ $row->rounding }}</td>
                            <td>{{ $row->notes ?? '—' }}</td>
                            <td class="text-right space-x-2">
                                <button type="button" wire:click="openVersion({{ $row->id }})"
                                        class="text-slate-700 hover:underline">Apri</button>
                                @if($row->status==='draft' && $canPublish)
                                    <button type="button" wire:click="openVersion({{ $row->id }}); publish();"
                                            class="text-emerald-700 hover:underline">Attiva</button>
                                @endif
                                @if($canArchive)
                                    <button type="button" wire:click="archive({{ $row->id }})"
                                            class="text-red-600 hover:underline">Archivia</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if($history->isEmpty())
                        <tr>
                            <td colspan="9" class="py-4 text-center text-gray-500">Nessuna versione trovata.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>

                <div class="mt-3">
                    {{ $history->links() }}
                </div>
            </div>
        </div>
    @endif
</div>
