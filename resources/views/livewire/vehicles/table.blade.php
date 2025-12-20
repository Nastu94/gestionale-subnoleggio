{{--
  View Livewire: vehicles.table
  - Toolbar compatta a griglia (ricerca/filtri + "Mostra archiviati" nella STESSA riga)
  - Tabella con sorting asc/desc, giorni scadenza interi
  - Barra selezione con pulsanti BULK (manutenzione, archivia)
  - Azioni riga: Agg. km, Manutenzione/Ripristina, Apri, Archivia, Ripristina (se archiviato)
--}}

<div class="space-y-4">

    {{-- Header + KPI --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2 text-sm">
            <span class="inline-flex items-center rounded-md bg-green-100 px-2 py-1">Disponibili: {{ $kpi['available'] }}</span>
            <span class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1">Assegnati: {{ $kpi['assigned'] }}</span>
            <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-1">In manut.: {{ $kpi['maintenance'] }}</span>
            <span class="inline-flex items-center rounded-md bg-rose-100 px-2 py-1">Scadenze ≤60gg: {{ $kpi['expiring'] }}</span>
        </div>
    </div>

    {{-- Toolbar (griglia): ricerca/filtri + "Mostra archiviati" nella STESSA riga --}}
    <div class="grid grid-cols-12 items-end gap-3">
        <div class="col-span-3">
            <label class="block text-sm font-medium text-gray-700">Ricerca</label>
            <input type="search"
                   wire:model.live.debounce.400ms="search"
                   placeholder="Targa, VIN, marca, modello…"
                   class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>

        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700">Stato tecnico</label>
            <div class="relative">
                <select wire:model.live="filterTechnical"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm appearance-none pr-8 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Qualsiasi</option>
                    <option value="ok">OK</option>
                    <option value="maintenance">Manutenzione / Fuori servizio</option>
                </select>
            </div>
        </div>

        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700">Disponibilità</label>
            <div class="relative">
                <select wire:model.live="filterAvailability"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm appearance-none pr-8 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Qualsiasi</option>
                    <option value="free">Libero ora</option>
                    <option value="assigned">Assegnato ora</option>
                </select>
            </div>
        </div>

        <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700">Carburante</label>
            <div class="relative">
                <select wire:model.live="filterFuel"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm appearance-none pr-8 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tutti</option>
                    <option value="petrol">Benzina</option>
                    <option value="diesel">Diesel</option>
                    <option value="hybrid">Ibrido</option>
                    <option value="electric">Elettrico</option>
                    <option value="lpg">GPL</option>
                    <option value="cng">Metano</option>
                </select>
            </div>
        </div>

        <div class="col-span-1">
            <label class="block text-sm font-medium text-gray-700">Righe/pagina</label>
            <div class="relative">
                <select wire:model.live="perPage"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm appearance-none pr-8 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="col-span-1 flex items-center">
            <label class="inline-flex items-center gap-2 mt-6">
                <input type="checkbox" wire:model.live="showArchived" class="rounded border-gray-300">
                <span class="text-sm text-gray-700">Mostra archiviati</span>
            </label>
        </div>
    </div>

    {{-- Barra selezione + azioni bulk --}}
    <div class="flex flex-wrap items-center justify-between rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
        <div class="flex items-center gap-3">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox"
                    wire:click="toggleSelectAllOnPage(@js($idsSelectableOnPage))"
                    @checked(count(array_intersect($selected, $idsSelectableOnPage)) === count($idsSelectableOnPage) && count($idsSelectableOnPage) > 0)
                    class="rounded border-gray-300">
                <span>Seleziona/Deseleziona tutti (pagina)</span>
            </label>

            <span>Selezionati: <strong>{{ count($selected) }}</strong></span>
        </div>

        <div class="flex items-center gap-2">
            @can('vehicles.manage_maintenance')
                <button type="button"
                        class="rounded bg-amber-600 px-3 py-1.5 text-white hover:bg-amber-700 disabled:opacity-50"
                        wire:click="setMaintenanceSelected"
                        @disabled(!count($selected))>
                    Segna in manutenzione (selezionati)
                </button>
            @endcan

            @can('vehicles.delete')
                <button type="button"
                        class="rounded bg-gray-800 px-3 py-1.5 text-white hover:bg-gray-900 disabled:opacity-50"
                        wire:click="archiveSelected"
                        @disabled(!count($selected))>
                    Archivia (selezionati)
                </button>
            @endcan
        </div>
    </div>

    {{-- Tabella --}}
    <div class="overflow-auto rounded-md border border-gray-200">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="w-8 px-3 py-2"></th>
                @php
                    $cols = [
                        'plate'            => 'Targa',
                        'make'             => 'Marca',
                        'model'            => 'Modello',
                        'year'             => 'Anno',
                        'mileage_current'  => 'Km',
                    ];
                @endphp
                @foreach($cols as $field => $label)
                    <th class="px-3 py-2 text-left font-semibold text-gray-700">
                        <button type="button" class="inline-flex items-center gap-1" wire:click="sortBy('{{ $field }}')">
                            <span>{{ $label }}</span>
                            @if($sortField === $field)
                                <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                            @endif
                        </button>
                    </th>
                @endforeach
                <th class="px-3 py-2">Stato</th>
                <th class="px-3 py-2">Disponibilità</th>
                <th class="px-3 py-2">Scadenze</th>
                <th class="px-3 py-2">Azioni</th>
            </tr>
            </thead>

            <tbody class="divide-y divide-gray-200 bg-white">
            @foreach($vehicles as $v)
                @php
                    $isArchived = method_exists($v, 'trashed') ? $v->trashed() : false;
                    $techLabel  = $v->is_maintenance ? 'Manutenzione' : 'OK';
                    $availLabel = $v->is_assigned ? 'Assegnato' : 'Libero';

                    // Giorni interi alla prossima scadenza (se presente)
                    $next  = $v->next_expiry_date ? \Illuminate\Support\Carbon::parse($v->next_expiry_date)->startOfDay() : null;
                    $days  = $next ? now()->startOfDay()->diffInDays($next, false) : null;
                    $badge = $days === null ? '' : ($days <= 7 ? 'bg-rose-100 text-rose-700' : ($days <= 60 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'));
                @endphp

                <tr class="hover:bg-gray-50 {{ $isArchived ? 'opacity-60' : '' }}">
                    <td class="px-3 py-2">
                        @php $isArchived = method_exists($v, 'trashed') ? $v->trashed() : false; @endphp

                        <input type="checkbox"
                            value="{{ $v->id }}"
                            wire:model.live="selected"
                            @checked(in_array($v->id, $selected, true))
                            @disabled($isArchived)
                            class="rounded border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed" />
                    </td>

                    {{-- Targa: apre SEMPRE il drawer --}}
                    <td class="px-3 py-2 font-medium">
                        <button type="button" class="text-indigo-700 hover:underline" wire:click="openDrawer({{ $v->id }})">
                            {{ $v->plate }}
                        </button>
                    </td>

                    <td class="px-3 py-2">{{ $v->make }}</td>
                    <td class="px-3 py-2">{{ $v->model }}</td>
                    <td class="px-3 py-2">{{ $v->year }}</td>
                    <td class="px-3 py-2">{{ number_format((int)$v->mileage_current, 0, ',', '.') }}</td>

                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-1 text-xs {{ $v->is_maintenance ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                            {{ $techLabel }}
                        </span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-1 text-xs {{ $v->is_assigned ? 'bg-sky-100 text-sky-800' : 'bg-green-100 text-green-800' }}">
                            {{ $availLabel }}
                        </span>
                    </td>

                    {{-- Scadenze: SOLO giorni interi --}}
                    <td class="px-3 py-2">
                        @if(!is_null($days))
                            <span class="rounded px-2 py-1 text-xs {{ $badge }}">{{ (int)$days }} gg</span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>

                    {{-- Azioni riga --}}
                    <td class="px-3 py-2">
                        @if($isArchived)
                            @can('restore', $v)
                                <button type="button" class="rounded bg-emerald-600 px-2 py-1 text-white" wire:click="restore({{ $v->id }})">
                                    Ripristina
                                </button>
                            @endcan
                        @else
                            @can('updateMileage', $v)
                                <button type="button" class="rounded bg-slate-100 px-2 py-1 mt-1"
                                        x-data
                                        x-on:click="$dispatch('open-mileage-modal', { id: {{ $v->id }}, current: {{ (int)$v->mileage_current }} })">
                                    Agg. km
                                </button>
                            @endcan

                            @can('manageMaintenance', $v)
                                @if(!$v->is_maintenance)
                                    <button type="button"
                                            class="rounded bg-amber-600 px-2 py-1 mt-1 text-white"
                                            x-data
                                            x-on:click="$dispatch('open-maintenance-open-modal', { id: {{ $v->id }} })">
                                        Manutenzione
                                    </button>
                                @else
                                    <button type="button"
                                            class="rounded bg-emerald-700 px-2 py-1 mt-1 text-white"
                                            x-data
                                            x-on:click="$dispatch('open-maintenance-close-modal', { id: {{ $v->id }} })">
                                        Chiudi manutenzione
                                    </button>
                                @endif
                            @endcan

                            @can('vehicles.view', $v)
                                <a href="{{ route('vehicles.show', $v) }}" class="rounded bg-white px-2 py-1 mt-1 mr-1 ring-1 ring-gray-300">
                                    Apri
                                </a>
                            @endcan

                            @can('vehicles.delete', $v)
                                <button type="button" class="rounded bg-gray-800 px-2 py-1 mt-1 text-white" wire:click="archive({{ $v->id }})">
                                    Archivia
                                </button>
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
            @if($vehicles->isEmpty())
                <tr>
                    <td class="px-3 py-6 text-center text-gray-500" colspan="100%">Nessun veicolo trovato.</td>
                </tr>
            @endif
            </tbody>
        </table>

        <div class="border-top px-3 py-2">
            {{ $vehicles->links() }}
        </div>
    </div>

    {{-- Drawer laterale (apre su click targa – non viene forzato da altre azioni) --}}
    @if($drawer)
        <div class="fixed inset-0 z-[70]">
            <div class="fixed inset-0 bg-black/40" wire:click="closeDrawer"></div>

            <div class="fixed right-0 top-0 h-full w-full max-w-3xl overflow-y-auto bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <div>
                        <div class="text-lg font-semibold">{{ $drawer['v']->plate }} — {{ $drawer['v']->make }} {{ $drawer['v']->model }}</div>
                        <div class="text-sm text-gray-500">
                            Anno {{ $drawer['v']->year }} • {{ $drawer['v']->fuel_type_label }} • {{ $drawer['v']->transmission_label }}
                        </div>
                    </div>
                    <button type="button" class="rounded border px-3 py-1" wire:click="closeDrawer">Chiudi</button>
                </div>

                <div class="space-y-6 p-4">
                    {{-- Profilo --}}
                    <section>
                        <h3 class="mb-2 font-semibold">Profilo</h3>
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <dt class="text-gray-500">VIN</dt><dd>{{ $drawer['v']->vin ?? '—' }}</dd>
                            <dt class="text-gray-500">Colore</dt><dd>{{ $drawer['v']->color ?? '—' }}</dd>
                            <dt class="text-gray-500">Posti</dt><dd>{{ $drawer['v']->seats ?? '—' }}</dd>
                            <dt class="text-gray-500">Segmento</dt><dd>{{ $drawer['v']->segment ?? '—' }}</dd>
                            <dt class="text-gray-500">Sede</dt><dd>{{ $drawer['v']->defaultPickupLocation?->name ?? '—' }}</dd>
                            <dt class="text-gray-500">Org.</dt><dd>{{ $drawer['v']->adminOrganization?->name ?? '—' }}</dd>
                            <dt class="text-gray-500">Km</dt><dd>{{ number_format((int)$drawer['v']->mileage_current, 0, ',', '.') }}</dd>
                            <dt class="text-gray-500">Note</dt><dd class="col-span-1">{{ $drawer['v']->notes ?? '—' }}</dd>
                        </dl>
                    </section>

                    {{-- Stato & disponibilità --}}
                    <section>
                        <h3 class="mb-2 font-semibold">Stato & Disponibilità</h3>
                        <div class="text-sm">
                            Stato tecnico:
                            <strong>{{ $drawer['currentStateLabel'] ?? ($drawer['currentState'] ?? 'OK') }}</strong>
                        </div>
                        @if($drawer['assignedNow'])
                            <div class="text-sm">
                                Affidato a: <strong>{{ $drawer['assignedNow']->renter_name }}</strong>
                            </div>
                        @endif
                    </section>

                    {{-- Documenti (giorni interi) --}}
                    <section>
                        <h3 class="mb-2 font-semibold">Documenti</h3>
                        <div class="text-sm mb-2">
                            In scadenza ≤60gg: <strong>{{ $drawer['expiring'] }}</strong> — Scaduti: <strong>{{ $drawer['expired'] }}</strong>
                            @can('vehicle_documents.viewAny')
                                <a class="ml-2 text-indigo-600 hover:underline"
                                   href="{{ route('vehicle-documents.index', ['vehicle_id' => $drawer['v']->id]) }}">Apri documenti</a>
                            @endcan
                        </div>

                        <div class="space-y-1 text-sm">
                            @forelse($drawer['v']->documents as $doc)
                                @php
                                    $exp  = $doc->expiry_date ? \Illuminate\Support\Carbon::parse($doc->expiry_date)->startOfDay() : null;
                                    $days = $exp ? now()->startOfDay()->diffInDays($exp, false) : null;
                                    $cls  = is_null($days) ? '' : ($days <= 7 ? 'text-rose-600' : ($days <= 60 ? 'text-amber-600' : 'text-green-600'));
                                @endphp
                                <div class="flex justify-between">
                                    <div>{{ strtoupper($doc->type_label) }} @if($doc->number)<span class="text-gray-500">#{{ $doc->number }}</span>@endif</div>
                                    <div>
                                        @if(!is_null($days))
                                            <span class="{{ $cls }}">{{ (int)$days }} gg</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-gray-500">Nessun documento caricato.</div>
                            @endforelse
                        </div>
                    </section>

                    {{-- Azioni rapide nel drawer --}}
                    <section class="pb-6">
                        <h3 class="font-semibold">Azioni rapide</h3>
                        <div class="flex items-center gap-2">
                            @can('vehicles.update', $drawer['v'])
                                <button type="button" class="rounded bg-slate-100 px-2 py-1"
                                        x-data
                                        x-on:click="$dispatch('open-mileage-modal', { id: {{ $drawer['v']->id }}, current: {{ (int)$drawer['v']->mileage_current }} })">
                                    Agg. km
                                </button>
                                @if(($drawer['currentState'] ?? null) !== 'maintenance')
                                    <button type="button" class="rounded bg-amber-600 px-2 py-1 text-white" wire:click="setMaintenance({{ $drawer['v']->id }})">
                                        Manutenzione
                                    </button>
                                @else
                                    <button type="button" class="rounded bg-emerald-700 px-2 py-1 text-white" wire:click="clearTechnicalState({{ $drawer['v']->id }})">
                                        Chiudi manutenzione
                                    </button>
                                @endif
                            @endcan
                        </div>
                    </section>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Aggiorna km (semplice, non interferisce col drawer) --}}
    <div x-data="{ open:false, id:null, current:0, value:'' }"
         x-on:open-mileage-modal.window="open=true; id=$event.detail.id; current=$event.detail.current; value=$event.detail.current;">
        <template x-if="open">
            <div class="fixed inset-0 z-[100]">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="absolute left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md bg-white p-4 shadow-xl">
                    <div class="text-lg font-semibold">Aggiorna chilometraggio</div>
                    <div class="mt-2 text-sm text-gray-600">Valore attuale: <strong x-text="current.toLocaleString('it-IT')"></strong> km</div>
                    <div class="mt-3">
                        <input type="number" min="0" step="1"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               x-model="value">
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded border px-3 py-1" x-on:click="open=false">Annulla</button>
                        <button type="button" class="rounded bg-indigo-600 px-3 py-1 text-white hover:bg-indigo-700"
                                x-on:click="$wire.updateMileage(id, parseInt(value,10)); open=false;">
                            Salva
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Modal: APERTURA manutenzione (workshop) -->
    <div x-data="{ open:false, id:null, workshop:'', notes:'' }"
        x-on:open-maintenance-open-modal.window="open=true; id=$event.detail.id; workshop=''; notes='';">
        <template x-if="open">
            <div class="fixed inset-0 z-[100]">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="absolute left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md bg-white p-4 shadow-xl">
                    <div class="text-lg font-semibold">Apri manutenzione</div>
                    <div class="mt-2 text-sm text-gray-600">
                        Inserisci l’officina / luogo in cui avverrà la manutenzione.
                    </div>
                    <div class="mt-3 space-y-2">
                        <div>
                            <label class="block text-xs text-gray-600">Officina / Luogo *</label>
                            <input type="text" x-model="workshop" class="w-full rounded-md border-gray-300" placeholder="Officina Rossi (Via…)">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Note (opz.)</label>
                            <textarea rows="2" x-model="notes" class="w-full rounded-md border-gray-300" placeholder="Dettagli intervento…"></textarea>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded border px-3 py-1" x-on:click="open=false">Annulla</button>
                        <button type="button" class="rounded bg-amber-600 px-3 py-1 text-white hover:bg-amber-700"
                                x-on:click="$wire.confirmOpenMaintenance(id, workshop, notes); open=false;">
                            Conferma apertura
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Modal: CHIUSURA manutenzione (costo) -->
    <div x-data="{ open:false, id:null, cost:'', notes:'' }"
        x-on:open-maintenance-close-modal.window="open=true; id=$event.detail.id; cost=''; notes='';">
        <template x-if="open">
            <div class="fixed inset-0 z-[100]">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="absolute left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md bg-white p-4 shadow-xl">
                    <div class="text-lg font-semibold">Chiudi manutenzione</div>
                    <div class="mt-2 text-sm text-gray-600">
                        Inserisci il costo totale (se disponibile). Potrai aggiornarlo anche in seguito.
                    </div>
                    <div class="mt-3 space-y-2">
                        <div>
                            <label class="block text-xs text-gray-600">Costo totale (€)</label>
                            <input type="number" min="0" step="0.01" x-model="cost" class="w-full rounded-md border-gray-300" placeholder="0,00">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Note (opz.)</label>
                            <textarea rows="2" x-model="notes" class="w-full rounded-md border-gray-300" placeholder="Esito intervento…"></textarea>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded border px-3 py-1" x-on:click="open=false">Annulla</button>
                        <button type="button" class="rounded bg-emerald-700 px-3 py-1 text-white hover:bg-emerald-800"
                                x-on:click="$wire.confirmCloseMaintenance(id, (cost ? parseFloat(cost) : null), notes); open=false;">
                            Conferma chiusura
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

</div>
