<div class="space-y-6">
    @php
        // class helper per input "morbidi"
        $input = 'block rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400
                dark:bg-gray-800 dark:border-gray-700';
        $btnIndigo = 'inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                    text-xs font-semibold text-white uppercase hover:bg-indigo-500
                    focus:outline-none focus:ring-2 focus:ring-indigo-300 transition';
        $btnSoft = 'inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold uppercase
                bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600';
    @endphp
    {{-- Toolbar: Nuova bozza + ricerca + toggle vista --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('rentals.create') }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                           text-xs font-semibold text-white uppercase hover:bg-indigo-500
                           focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                + Nuova bozza
            </a>

            <div class="relative">
                <input type="text" wire:model.live.debounce.400ms="q"
                       placeholder="Cerca per riferimento o cliente…"
                       class="{{ $input }} w-72 pr-8" />
                <div class="absolute right-2 top-1/2 -translate-y-1/2 opacity-60">🔎</div>
            </div>
        </div>

        <div class="join">
            <button class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold uppercase 
                           join-item {{ $view==='table' ? 'btn-primary bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700' }}"
                    wire:click="setView('table')">Elenco</button>
            <button class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold uppercase
                           join-item {{ $view==='kanban' ? 'btn-primary bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700' }}"
                    wire:click="setView('kanban')">Bacheca</button>
            <button class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold uppercase
                           join-item {{ $view==='planner' ? 'btn-primary bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700' }}"
                    wire:click="setView('planner')">
                Planner
            </button>
        </div>
    </div>

    {{-- KPI header (italiano + colori per stato) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3">
        @foreach($this->states as $s)
            @php
                $isActive = $state === $s;
                $classes  = $stateColors[$s] ?? 'bg-base-200';
                $label    = $stateLabels[$s] ?? strtoupper($s);
            @endphp
            <button type="button"
                    class="border rounded-xl p-4 text-left shadow-sm hover:shadow transition
                           {{ $classes }} {{ $isActive ? 'ring-2 ring-offset-1 ring-primary' : '' }}"
                    wire:click="filterState('{{ $s }}')">
                <div class="text-sm opacity-80">{{ $label }}</div>
                <div class="text-2xl font-semibold">{{ number_format($kpis[$s] ?? 0, 0, ',', '.') }}</div>
            </button>
        @endforeach
    </div>

    @if($view === 'table')
        {{-- ELENCO (Tabella) --}}
        <div class="card shadow rounded-lg">
            {{-- Tabella --}}
            <div class="overflow-x-auto p-3">
                <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-300 dark:bg-gray-700">
                        <tr class="text-xs uppercase text-gray-500">
                            <th class="w-40">Riferimento</th>
                            <th>Cliente</th>
                            <th>Veicolo</th>
                            <th class="w-40">Ritiro</th>
                            <th class="w-40">Riconsegna</th>
                            <th class="w-28 text-right">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800">
                    @foreach($rows as $r)
                        @php
                            // Etichetta e stile dello stato (resta compatibile con il tuo mapping)
                            $badge = $stateLabels[$r->status] ?? $r->status;
                            $badgeClass = match($r->status) {
                                'draft'       => 'bg-gray-100 text-gray-800 ring-1 ring-gray-300',
                                'reserved'    => 'bg-blue-100 text-blue-900 ring-1 ring-blue-300',
                                'in_use'      => 'bg-violet-100 text-violet-900 ring-1 ring-violet-300',
                                'checked_in'  => 'bg-cyan-100 text-cyan-900 ring-1 ring-cyan-300',
                                'closed'      => 'bg-green-100 text-green-900 ring-1 ring-green-300',
                                'cancelled'   => 'bg-rose-100 text-rose-900 ring-1 ring-rose-300',
                                default       => 'bg-gray-100 text-gray-700 ring-1 ring-gray-300',
                            };

                            // Chip di completezza (contratto, firma, foto, danni, pagamento)
                            $pickup   = $r->checklists->firstWhere('type','pickup');
                            $return   = $r->checklists->firstWhere('type','return');

                            $hasContract     = $r->getMedia('contract')->isNotEmpty();
                            $hasSignedRental = $r->getMedia('signatures')->isNotEmpty();
                            $hasSignedPU     = $pickup ? $pickup->getMedia('signatures')->isNotEmpty() : false;

                            $photosPU = $pickup ? $pickup->getMedia('photos')->count() : 0;
                            $photosRT = $return ? $return->getMedia('photos')->count() : 0;

                            $dmgCount = $r->damages->count();
                            $dmgNoPic = $r->damages->filter(fn($d)=>$d->getMedia('photos')->isEmpty())->count();
                        @endphp

                        <tr class="align-middle hover:bg-gray-50/50 dark:hover:bg-gray-700/30">
                            <td class="font-medium">
                                {{ $r->reference ?? ('#'.$r->id) }}
                                {{-- Chip: piccoli indicatori inline --}}
                                <div class="mt-1 flex flex-wrap gap-1.5 text-[11px]">
                                    {{-- Contratto generato --}}
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $hasContract ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        📄 <span>Contratto</span>
                                    </span>

                                    {{-- Contratto firmato (Rental) --}}
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $hasSignedRental ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                                                                    : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        ✍️ <span>Firmato</span>
                                    </span>

                                    {{-- Firma su Checklist pickup --}}
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $hasSignedPU ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        🧾 <span>Firma pickup</span>
                                    </span>

                                    {{-- Foto pickup --}}
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $photosPU>0 ? 'bg-sky-100 text-sky-900 ring-1 ring-sky-300'
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        📸 <span>Pickup {{ $photosPU }}</span>
                                    </span>

                                    {{-- Foto return --}}
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $photosRT>0 ? 'bg-sky-100 text-sky-900 ring-1 ring-sky-300'
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        📸 <span>Return {{ $photosRT }}</span>
                                    </span>

                                    {{-- Danni (con attenzione se senza foto) --}}
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $dmgCount>0 ? ($dmgNoPic>0 ? 'bg-amber-100 text-amber-900 ring-1 ring-amber-300'
                                                                            : 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300')
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        ⚠️ <span>Danni {{ $dmgCount }}</span>
                                        @if($dmgNoPic>0)
                                            <span class="ml-1 text-[10px]">({{ $dmgNoPic }} senza foto)</span>
                                        @endif
                                    </span>

                                    {{-- Pagamento registrato --}}
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $r->base_payment_at ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                                                                        : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        € <span>Pagamento</span>
                                    </span>
                                </div>
                            </td>

                            <td class="text-center">{{ optional($r->customer)->name ?? '—' }}</td>
                            <td class="text-center">{{ optional($r->vehicle)->plate . ' — ' . optional($r->vehicle)->make . ' ' . optional($r->vehicle)->model ?? '—' }}</td>
                            <td class="text-center">{{ optional($r->planned_pickup_at)->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-center">{{ optional($r->planned_return_at)->format('d/m/Y H:i') ?? '—' }}</td>

                            <td class="text-right">
                                <a href="{{ route('rentals.show', $r) }}"
                                class="inline-flex items-center px-2.5 py-1.5 rounded-md bg-indigo-600 text-white text-xs font-semibold uppercase
                                        hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                                    Apri
                                </a>
                            </td>
                        </tr>
                    @endforeach

                    @if($rows->isEmpty())
                        <tr>
                            <td colspan="6" class="text-center opacity-60 py-6">
                                Nessun noleggio trovato
                            </td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $rows->links() }}
            </div>
        </div>
    @elseif($view === 'kanban')
        {{-- BACHECA (Kanban) --}}
        @php
            $cols = $state ? [$state] : ['draft','reserved','in_use','checked_in','closed'];
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-{{ count($cols) }} gap-4">
            @foreach($cols as $col)
                <div class="card shadow bg-base-100">
                    <div class="card-title px-4 pt-4">
                        {{ $stateLabels[$col] ?? strtoupper($col) }}
                    </div>
                    <div class="p-4 space-y-3 max-h-[70vh] overflow-auto">
                        @foreach($rows->where('status',$col) as $r)
                            @php
                                $pickup   = $r->checklists->firstWhere('type','pickup');
                                $return   = $r->checklists->firstWhere('type','return');

                                $hasContract     = $r->getMedia('contract')->isNotEmpty();
                                $hasSignedRental = $r->getMedia('signatures')->isNotEmpty();
                                $hasSignedPU     = $pickup ? $pickup->getMedia('signatures')->isNotEmpty() : false;

                                $photosPU = $pickup ? $pickup->getMedia('photos')->count() : 0;
                                $photosRT = $return ? $return->getMedia('photos')->count() : 0;

                                $dmgCount = $r->damages->count();
                                $dmgNoPic = $r->damages->filter(fn($d)=>$d->getMedia('photos')->isEmpty())->count();
                            @endphp

                            <div class="rounded-xl border p-3 bg-base-200">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold">{{ $r->reference ?? ('#'.$r->id) }}</div>
                                    <a href="{{ route('rentals.show', $r) }}"
                                    class="inline-flex items-center px-2 py-1 rounded-md bg-indigo-600 text-white text-[11px] font-semibold uppercase
                                            hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                                        Apri
                                    </a>
                                </div>

                                <div class="text-sm opacity-80">
                                    {{ optional($r->customer)->name ?? '—' }} ·
                                    {{ optional($r->vehicle)->plate ?? optional($r->vehicle)->make . ' ' . optional($r->vehicle)->model ?? '—' }}
                                </div>

                                <div class="text-xs opacity-60">
                                    {{ optional($r->planned_pickup_at)->format('d/m H:i') ?? '—' }}
                                    →
                                    {{ optional($r->planned_return_at)->format('d/m H:i') ?? '—' }}
                                </div>

                                {{-- Chip compatti --}}
                                <div class="mt-2 flex flex-wrap gap-1.5 text-[11px]">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $hasContract ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        📄 <span>Contratto</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $hasSignedRental ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                                                                    : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        ✍️ <span>Firmato</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $hasSignedPU ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        🧾 <span>Firma pickup</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $photosPU>0 ? 'bg-sky-100 text-sky-900 ring-1 ring-sky-300'
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        📸 <span>PU {{ $photosPU }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $photosRT>0 ? 'bg-sky-100 text-sky-900 ring-1 ring-sky-300'
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        📸 <span>RT {{ $photosRT }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $dmgCount>0 ? ($dmgNoPic>0 ? 'bg-amber-100 text-amber-900 ring-1 ring-amber-300'
                                                                            : 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300')
                                                                : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        ⚠️ <span>Danni {{ $dmgCount }}</span>
                                        @if($dmgNoPic>0)
                                            <span class="ml-1 text-[10px]">({{ $dmgNoPic }} s/foto)</span>
                                        @endif
                                    </span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                {{ $r->base_payment_at ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
                                                                        : 'bg-gray-100 text-gray-700 ring-1 ring-gray-300' }}">
                                        € <span>Pagamento</span>
                                    </span>
                                </div>
                            </div>
                        @endforeach
                        @if($rows->where('status',$col)->isEmpty())
                            <div class="opacity-60 text-sm">Nessun noleggio</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @elseif($view === 'planner')
        {{-- PLANNER (veicoli x calendario) --}}
        <div class="card shadow rounded-lg p-4 space-y-4">

            {{-- Toolbar del planner: periodo + modalità vista --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex-1"></div>
                {{-- Navigazione periodo (settimana/giorno) --}}
                <div class="flex items-center gap-2 mx-auto">
                    <button type="button"
                            class="inline-flex items-center justify-center px-2 py-1 border rounded-md text-sm
                                   hover:bg-gray-100 dark:hover:bg-gray-800"
                            wire:click="goToPreviousPeriod">
                        ‹
                    </button>

                    <div class="font-medium">
                        {{ $this->plannerPeriodLabel }}
                    </div>

                    <button type="button"
                            class="inline-flex items-center justify-center px-2 py-1 border rounded-md text-sm
                                   hover:bg-gray-100 dark:hover:bg-gray-800"
                            wire:click="goToNextPeriod">
                        ›
                    </button>
                </div>

                {{-- Toggle vista: Settimana / Giorno --}}
                <div class="flex-1 flex items-center justify-end gap-2">
                    <span class="text-xs uppercase opacity-70">Vista:</span>
                    <div class="join">
                        <button type="button"
                                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold uppercase
                                       join-item {{ $plannerMode === 'week' ? 'btn-primary bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700' }}"
                                wire:click="setPlannerMode('week')">
                            Settimana
                        </button>
                        <button type="button"
                                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold uppercase
                                       join-item {{ $plannerMode === 'day' ? 'btn-primary bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700' }}"
                                wire:click="setPlannerMode('day')">
                            Giorno
                        </button>
                    </div>
                </div>
            </div>

            {{-- Filtri specifici del planner (stato + organizzazione per admin) --}}
            <div class="flex flex-wrap items-center gap-4 border-t pt-3 mt-1">

                {{-- Filtro per stato dei noleggi nel planner --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs uppercase opacity-70">Stato:</span>
                    <select wire:model.live="plannerStatusFilter"
                            class="{{$input}} w-40">
                        {{-- Opzione 'tutti gli stati' (default) --}}
                        <option value="all">Tutti gli stati</option>

                        {{-- Le altre opzioni usano le etichette già definite in $stateLabels --}}
                        @foreach($stateLabels as $statusKey => $label)
                            <option value="{{ $statusKey }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filtro organizzazione: solo per admin / super-admin --}}
                @if(auth()->user()->hasAnyRole(['admin', 'super-admin']))
                    <div class="flex items-center gap-2">
                        <span class="text-xs uppercase opacity-70">Organizzazione:</span>
                        <select wire:model.live="plannerOrganizationFilter"
                                class="{{$input}} w-48">
                            {{-- Default admin: veicoli non assegnati a nessun renter --}}
                            <option value="">Veicoli non assegnati</option>

                            {{-- Opzioni dinamiche dalle organizzazioni visibili all'admin --}}
                            @foreach($this->plannerOrganizations as $org)
                                <option value="{{ $org->id }}">
                                    {{ $org->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- Griglia base del planner: per ora solo header settimanale / placeholder righe --}}
            @if($plannerMode === 'week')
                {{-- Vista settimanale: header con colonna veicolo + giorni lun–dom --}}
                <div class="border rounded-lg overflow-x-auto bg-gray-50 dark:bg-gray-900/40">
                    {{-- min-w-max assicura che header e righe abbiano SEMPRE la stessa larghezza,
                         e che background + griglia si vedano bene anche con scroll orizzontale --}}
                    <div class="min-w-max">
                        {{-- HEADER --}}
                        <div
                            class="flex border-b border-gray-200 dark:border-gray-700 text-xs font-semibold uppercase text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/60">
                            {{-- Colonna "Veicolo" allineata con le righe sotto --}}
                            <div class="px-3 py-2 border-r border-gray-200 dark:border-gray-700 min-w-[220px] bg-gray-50 dark:bg-gray-900">
                                Veicolo
                            </div>

                            {{-- Parte destra: stessa struttura delle righe (relative + grid 7 colonne) --}}
                            <div class="relative flex-1">
                                <div class="grid grid-cols-7">
                                    @foreach($this->plannerWeekDays as $day)
                                        <button
                                            type="button"
                                            class="border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 hover:bg-gray-100 dark:hover:bg-gray-800 min-w-[120px] w-full text-center py-2"
                                            wire:click="openPlannerDay('{{ $day['date'] }}')"
                                        >
                                            <div class="font-medium">
                                                {{ ucfirst($day['label']) }}
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        @php
                            // Veicoli da mostrare nel planner
                            $vehicles = $this->plannerVehicles;

                            // Set di ID noleggi in overbooking (per lookup O(1) in Blade)
                            // plannerOverbookedRentalIds è la computed property definita nel componente.
                            $overbookedSet = array_flip($this->plannerOverbookedRentalIds);

                            // Nuova mappa: giorni occupati per veicolo
                            $busyWeek = $this->plannerWeekBusyDaysByVehicle;
                        @endphp

                        @if($vehicles->isEmpty())
                            <div class="p-4 text-sm opacity-70 bg-white dark:bg-gray-900/40">
                                Nessun veicolo disponibile per i filtri selezionati.
                            </div>
                        @else
                            {{-- CORPO: righe veicoli --}}
                            <div class="bg-white dark:bg-gray-900/40 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($vehicles as $vehicle)
                                    @php
                                        $weekDays    = $this->plannerWeekDays;
                                        $daysCount   = count($weekDays);
                                        $vehicleBars = $this->plannerBars[$vehicle->id] ?? [];
                                        $busyDays    = $busyWeek[$vehicle->id] ?? [];
                                    @endphp

                                    <div class="flex">
                                        {{-- Colonna veicolo --}}
                                        <div class="px-3 py-2 border-r border-gray-200 dark:border-gray-700 min-w-[220px] bg-white dark:bg-gray-900">
                                            <div class="font-medium text-sm">
                                                {{ $vehicle->plate ?? '—' }}
                                            </div>
                                            <div class="text-xs opacity-70">
                                                {{ $vehicle->make }} {{ $vehicle->model }}
                                            </div>
                                        </div>

                                        {{-- Colonne giorni + barre noleggio --}}
                                        <div class="relative flex-1">
                                            {{-- Griglia di sfondo: 7 colonne (lun–dom) --}}
                                            <div class="grid grid-cols-7 h-14">
                                                @foreach($weekDays as $day)
                                                    @php
                                                        $date   = $day['date'];
                                                        $isBusy = !empty($busyDays[$date]);
                                                    @endphp

                                                    @if($isBusy)
                                                        {{-- Giorno occupato: cella muta, non cliccabile --}}
                                                        <div
                                                            class="border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/30 min-w-[120px] w-full h-full"
                                                            title="Slot occupato da almeno un noleggio"
                                                        ></div>
                                                    @else
                                                        {{-- Giorno libero: cella cliccabile che apre il wizard --}}
                                                        <button
                                                            type="button"
                                                            wire:click="createRentalFromSlot({{ $vehicle->id }}, '{{ $date }}')"
                                                            class="border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/20 min-w-[120px] w-full h-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/60"
                                                            title="Crea noleggio per {{ $vehicle->plate ?? 'veicolo' }} in questo giorno"
                                                        >
                                                        </button>
                                                    @endif
                                                @endforeach
                                            </div>

                                            {{-- Barre continue per i noleggi di questo veicolo --}}
                                            @foreach($vehicleBars as $bar)
                                                @php
                                                    /** @var \App\Models\Rental $rental */
                                                    $rental = $bar['rental'];

                                                    // Etichetta e classi base in base allo stato del noleggio
                                                    $statusLabel = $stateLabels[$rental->status] ?? $rental->status;
                                                    $statusClass = $stateColors[$rental->status] ?? 'bg-gray-100 border-gray-300 text-gray-800';

                                                    $pickup = optional($rental->planned_pickup_at);
                                                    $return = optional($rental->planned_return_at);

                                                    $left = $bar['start_index'];
                                                    $span = $bar['span'];

                                                    // Verifica se questo rental è in overbooking
                                                    $isOverbooked = isset($overbookedSet[$rental->id]);

                                                    // Classi extra per evidenziare il conflitto (bordo/alone rosso)
                                                    $overClass = $isOverbooked
                                                        ? 'border-red-400 ring-2 ring-red-300 shadow-[0_0_0_1px_rgba(248,113,113,0.4)]'
                                                        : '';
                                                @endphp

                                                <a href="{{ route('rentals.show', $rental) }}"
                                                class="absolute inset-y-1 rounded-md border text-[10px] leading-snug px-2 flex flex-col justify-center cursor-pointer {{ $statusClass }} {{ $overClass }}"
                                                style="
                                                    left:  calc({{ $left }} * (100% / {{ $daysCount }}));
                                                    width: calc({{ $span }} * (100% / {{ $daysCount }}));
                                                ">
                                                    <div class="flex items-center justify-start gap-1">
                                                        @if($isOverbooked)
                                                            {{-- Piccola icona di warning per evidenziare il conflitto --}}
                                                            <span class="text-[11px] text-red-700 flex items-center">
                                                                ⚠
                                                            </span>
                                                        @endif

                                                        <div class="font-semibold truncate">
                                                            {{ $rental->reference ?? ('#'.$rental->id) }}
                                                        </div>
                                                    </div>
                                                    <div class="text-[9px] opacity-80 truncate">
                                                        {{ optional($rental->customer)->name ?? '—' }}
                                                    </div>
                                                    <div class="text-[9px] opacity-60 whitespace-nowrap">
                                                        {{ $pickup ? $pickup->format('d/m H:i') : '—' }}
                                                        →
                                                        {{ $return ? $return->format('d/m H:i') : '—' }}
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                            </div>
                        @endif
                    </div>
                </div>

                <div class="mt-2 text-xs opacity-60">
                    Noleggi nel periodo selezionato: {{ $this->plannerRentals->count() }}
                </div>
            @else
                {{-- ================= VISTA GIORNALIERA (veicoli × ore) ================= --}}
                @php
                    $vehicles = $this->plannerVehicles;
                    $hours    = $this->plannerDayHours;
                    // Data corrente del planner (formato Y-m-d, usato per creare noleggi dal giorno)
                    $currentDate = $this->plannerCurrentDate;
                    // nuova property: ore occupate per veicolo
                    $busyByVehicle = $this->plannerDayBusySlotsByVehicle;
                @endphp

                <div class="border rounded-lg overflow-x-auto bg-gray-50 dark:bg-gray-900/40">
                    <div class="min-w-max">
                        {{-- HEADER: giorno + ore (altezza allineata alle righe: h-16) --}}
                        <div class="flex border-b border-gray-200 dark:border-gray-700 text-xs font-semibold uppercase text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/60 h-16">
                            {{-- Colonna sinistra: giorno corrente (click = torna alla settimana) --}}
                            <div
                                wire:click="setPlannerMode('week')"
                                role="button"
                                tabindex="0"
                                class="border-r border-gray-200 dark:border-gray-700 min-w-[220px] text-left bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer flex flex-col justify-center px-3"
                                title="Torna alla vista settimanale"
                            >
                                <div class="text-[11px] opacity-70 leading-tight">
                                    Giorno selezionato
                                </div>
                                <div class="text-sm font-semibold leading-tight">
                                    {{ $this->plannerPeriodLabel }}
                                </div>
                            </div>

                            {{-- Parte destra: 24 colonne, stessa altezza delle righe --}}
                            <div class="relative flex-1">
                                <div class="flex h-full">
                                    @foreach($hours as $h)
                                        <div class="border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 min-w-[72px] w-full flex items-center justify-center">
                                            {{ $h['label'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        @if($vehicles->isEmpty())
                            <div class="p-4 text-sm opacity-70 bg-white dark:bg-gray-900/40">
                                Nessun veicolo disponibile per i filtri selezionati.
                            </div>
                        @else
                            {{-- CORPO: righe per veicolo --}}
                            <div class="bg-white dark:bg-gray-900/40 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($vehicles as $vehicle)
                                    @php
                                        $busySlots = $busyByVehicle[$vehicle->id] ?? [];
                                    @endphp
                                    <div class="flex">
                                        {{-- Colonna veicolo --}}
                                        <div class="px-3 py-2 border-r border-gray-200 dark:border-gray-700 min-w-[220px] bg-white dark:bg-gray-900">
                                            <div class="font-medium text-sm">
                                                {{ $vehicle->plate ?? '—' }}
                                            </div>
                                            <div class="text-xs opacity-70">
                                                {{ $vehicle->make }} {{ $vehicle->model }}
                                            </div>
                                        </div>

                                        {{-- Parte destra: slot orari per questo veicolo (per ora solo celle cliccabili) --}}
                                        <div class="relative flex-1">
                                            {{-- Griglia oraria di sfondo: 24 colonne --}}
                                            <div class="flex h-16">
                                                @foreach($hours as $h)
                                                    @php
                                                        $hourIndex = $h['index'];
                                                        $isBusy    = $busySlots[$hourIndex] ?? false;
                                                    @endphp

                                                    @if($isBusy)
                                                        {{-- slot occupato: solo cella muta, niente click --}}
                                                        <div
                                                            class="border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/30 min-w-[72px] w-full h-full"
                                                            title="Slot occupato da almeno un noleggio"
                                                        ></div>
                                                    @else
                                                        {{-- slot libero: cella cliccabile che apre il wizard --}}
                                                        <button
                                                            type="button"
                                                            wire:click="createRentalFromSlot({{ $vehicle->id }}, '{{ $currentDate }}', '{{ $h['label'] }}')"
                                                            class="border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/20 min-w-[72px] w-full h-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/60"
                                                            title="Crea noleggio per {{ $vehicle->plate ?? 'veicolo' }} in questo orario"
                                                        >
                                                        </button>
                                                    @endif
                                                @endforeach
                                            </div>

                                            @php
                                                // Barre per questo veicolo nel giorno selezionato
                                                $bars       = $this->plannerDayBarsByVehicle[$vehicle->id] ?? [];
                                                $overIds    = $this->plannerOverbookedRentalIds ?? [];
                                                $hoursCount = count($hours);
                                            @endphp

                                            @foreach($bars as $bar)
                                                @php
                                                    /** @var \App\Models\Rental $rental */
                                                    $rental = $bar['rental'];

                                                    $statusClass = $stateColors[$rental->status] ?? 'bg-gray-100 border-gray-300 text-gray-800';

                                                    $pickup = optional($rental->planned_pickup_at);
                                                    $return = optional($rental->planned_return_at);

                                                    $left = $bar['start_index'];
                                                    $span = $bar['span'];

                                                    $isOverbooked = in_array($rental->id, $overIds, true);

                                                    $overClass = $isOverbooked
                                                        ? 'border-red-400 ring-2 ring-red-300 shadow-[0_0_0_1px_rgba(248,113,113,0.4)]'
                                                        : '';
                                                @endphp

                                                <a href="{{ route('rentals.show', $rental) }}"
                                                class="absolute inset-y-1 rounded-md border text-[10px] leading-snug px-2 flex flex-col justify-center cursor-pointer {{ $statusClass }} {{ $overClass }} pointer-events-auto"
                                                style="
                                                        left:  calc({{ $left }} * (100% / {{ $hoursCount }}));
                                                        width: calc({{ $span }} * (100% / {{ $hoursCount }}));
                                                "
                                                title="#{{ $rental->reference ?? $rental->id }} · {{ optional($rental->customer)->name ?? '—' }} · {{ $pickup ? $pickup->format('H:i') : '—' }} → {{ $return ? $return->format('H:i') : '—' }}"
                                                >
                                                    <div class="flex items-center justify-start gap-1">
                                                        @if($isOverbooked)
                                                            <span class="text-[11px] text-red-700 flex items-center">
                                                                ⚠
                                                            </span>
                                                        @endif
                                                        <div class="font-semibold truncate">
                                                            {{ $rental->reference ?? ('#'.$rental->id) }}
                                                        </div>
                                                    </div>
                                                    <div class="text-[9px] opacity-80 truncate">
                                                        {{ optional($rental->customer)->name ?? '—' }}
                                                    </div>
                                                    <div class="text-[9px] opacity-60 whitespace-nowrap">
                                                        {{ $pickup ? $pickup->format('H:i') : '—' }}
                                                        →
                                                        {{ $return ? $return->format('H:i') : '—' }}
                                                    </div>
                                                </a>
                                            @endforeach


                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="mt-2 text-xs opacity-60">
                    Noleggi nel giorno selezionato: {{ $this->plannerRentals->count() }}
                </div>
            @endif
        </div>
    @endif
</div>
