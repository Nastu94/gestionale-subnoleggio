<div class="space-y-6">
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
                       class="input input-bordered w-72 pr-8" />
                <div class="absolute right-2 top-1/2 -translate-y-1/2 opacity-60">🔎</div>
            </div>
        </div>

        <div class="join">
            <button class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                           text-xs font-semibold text-white uppercase hover:bg-indigo-500
                           focus:outline-none focus:ring-2 focus:ring-indigo-300 transition 
                           join-item {{ $view==='table'?'btn-primary':'' }}"
                    wire:click="setView('table')">Elenco</button>
            <button class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                           text-xs font-semibold text-white uppercase hover:bg-indigo-500
                           focus:outline-none focus:ring-2 focus:ring-indigo-300 transition
                           join-item {{ $view==='kanban'?'btn-primary':'' }}"
                    wire:click="setView('kanban')">Bacheca</button>
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
                                'checked_out' => 'bg-amber-100 text-amber-900 ring-1 ring-amber-300',
                                'in_use'      => 'bg-violet-100 text-violet-900 ring-1 ring-violet-300',
                                'checked_in'  => 'bg-cyan-100 text-cyan-900 ring-1 ring-cyan-300',
                                'closed'      => 'bg-green-100 text-green-900 ring-1 ring-green-300',
                                'cancelled'   => 'bg-rose-100 text-rose-900 ring-1 ring-rose-300',
                                'no_show'     => 'bg-rose-100 text-rose-900 ring-1 ring-rose-300',
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
                                                {{ $r->payment_recorded ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
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
    @else
        {{-- BACHECA (Kanban) --}}
        @php
            $cols = $state ? [$state] : ['draft','reserved','checked_out','in_use','checked_in','closed'];
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
                                                {{ $r->payment_recorded ? 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300'
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
    @endif
</div>
