{{-- Pagina dettaglio veicolo (Livewire: Vehicles\Show) --}}
<div class="space-y-4">

    {{-- Breadcrumb --}}
    <div class="text-sm text-gray-500">
        <a href="{{ route('vehicles.index') }}" class="hover:underline">Veicoli</a>
        <span class="mx-1">/</span>
        <span class="font-medium">{{ $v->plate }}</span>
    </div>

    {{-- Header sticky con badge e azioni --}}
    <div class="sticky top-0 z-20 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/70 border-b">
        <div class="mx-auto max-w-screen-2xl px-2 py-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <div class="text-lg font-semibold">
                        {{ $v->plate }} — {{ $v->make }} {{ $v->model }}
                        <span class="text-gray-500 font-normal">({{ $v->year }})</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        {{-- Stato disponibilità --}}
                        <span class="rounded bg-{{ $isAssigned ? 'sky' : 'green' }}-100 px-2 py-1 text-{{ $isAssigned ? 'sky' : 'green' }}-800">
                            {{ $isAssigned ? 'Assegnato' : 'Libero' }}
                        </span>
                        {{-- Stato tecnico --}}
                        <span class="rounded bg-{{ $isMaintenance ? 'amber' : 'green' }}-100 px-2 py-1 text-{{ $isMaintenance ? 'amber' : 'green' }}-800">
                            {{ $isMaintenance ? 'Manutenzione' : 'OK' }}
                        </span>
                        {{-- Archiviato --}}
                        @if($isArchived)
                            <span class="rounded bg-gray-200 px-2 py-1 text-gray-800">Archiviato</span>
                        @endif

                        {{-- Prossima scadenza (giorni interi) --}}
                        @if(!is_null($nextDays))
                            <span class="rounded bg-{{ $nextDays <= 7 ? 'rose' : ($nextDays <= 60 ? 'amber' : 'green') }}-100 px-2 py-1 text-{{ $nextDays <= 7 ? 'rose' : ($nextDays <= 60 ? 'amber' : 'green') }}-700">
                                Prossima scadenza: {{ (int) $nextDays }} gg
                            </span>
                        @endif

                        <span class="rounded bg-slate-100 px-2 py-1 text-slate-700">
                            Km: {{ number_format((int) $v->mileage_current, 0, ',', '.') }}
                        </span>

                        <span class="rounded bg-slate-100 px-2 py-1 text-slate-700">
                            Org: {{ $v->adminOrganization?->name ?? '—' }}
                        </span>
                        <span class="rounded bg-slate-100 px-2 py-1 text-slate-700">
                            Sede: {{ $v->defaultPickupLocation?->name ?? '—' }}
                        </span>
                    </div>
                </div>

                {{-- Azioni header (permessi granulari + restore/delete) --}}
                <div class="flex flex-wrap items-center gap-2">
                    @can('updateMileage', $v)
                        <button type="button" class="rounded bg-slate-100 px-3 py-1"
                                x-data
                                x-on:click="$dispatch('open-mileage-modal', { current: {{ (int)$v->mileage_current }} })"
                                @disabled($isArchived)>
                            Aggiorna km
                        </button>
                    @endcan

                    @can('manageMaintenance', $v)
                        @if(!$isArchived)
                            @if(!$isMaintenance)
                                <button type="button" class="rounded bg-amber-600 px-3 py-1 text-white" wire:click="setMaintenance">
                                    Manutenzione
                                </button>
                            @else
                                <button type="button" class="rounded bg-emerald-700 px-3 py-1 text-white" wire:click="clearMaintenance">
                                    Chiudi manutenzione
                                </button>
                            @endif
                        @endif
                    @endcan

                    @if(!$isArchived)
                        @can('vehicles.delete', $v)
                            <button type="button" class="rounded bg-gray-800 px-3 py-1 text-white" wire:click="archive">
                                Archivia
                            </button>
                        @endcan
                    @else
                        @can('restore', $v)
                            <button type="button" class="rounded bg-emerald-600 px-3 py-1 text-white" wire:click="restore">
                                Ripristina
                            </button>
                        @endcan
                    @endif
                </div>
            </div>

            {{-- Barra tab --}}
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                @php
                    $tabs = [
                        'profile'     => 'Profilo',
                        'photos'      => 'Foto',
                        'documents'   => "Documenti" . ($docSoon || $docExpired ? " ({$docSoon} ≤60gg / {$docExpired} scad.)" : ''),
                        'pricing'     => 'Listino',
                        'maintenance' => 'Stato tecnico',
                        'assignments' => 'Assegnazioni',
                        'notes'       => 'Note',
                    ];
                @endphp
                @foreach($tabs as $key => $label)
                    <button type="button"
                            class="rounded px-3 py-1 ring-1 ring-slate-300 {{ $tab === $key ? 'bg-slate-800 text-white' : 'bg-white text-slate-700' }}"
                            wire:click="switchTab('{{ $key }}')">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Banner se archiviato --}}
        @if($isArchived)
            <div class="bg-amber-50 border-t border-b border-amber-200 px-3 py-2 text-sm text-amber-800">
                Questo veicolo è archiviato. Le azioni sono disabilitate finché non viene ripristinato.
            </div>
        @endif
    </div>

    {{-- CONTENUTI TABS --}}
    <div class="mx-auto max-w-screen-2xl px-2 pb-8 pt-2">

        {{-- PROFILO --}}
        @if($tab === 'profile')
            <div class="rounded-lg border bg-white p-4">
                <h2 class="mb-3 text-base font-semibold">Profilo</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <dt class="text-gray-500">VIN</dt><dd>{{ $v->vin ?? '—' }}</dd>
                    <dt class="text-gray-500">Colore</dt><dd>{{ $v->color ?? '—' }}</dd>
                    <dt class="text-gray-500">Posti</dt><dd>{{ $v->seats ?? '—' }}</dd>
                    <dt class="text-gray-500">Segmento</dt><dd>{{ $v->segment ?? '—' }}</dd>
                    <dt class="text-gray-500">Carburante</dt><dd>{{ $v->fuel_type ?? '—' }}</dd>
                    <dt class="text-gray-500">Cambio</dt><dd>{{ $v->transmission ?? '—' }}</dd>
                    <dt class="text-gray-500">Creato il</dt><dd>{{ optional($v->created_at)->format('d/m/Y H:i') }}</dd>
                    <dt class="text-gray-500">Aggiornato il</dt><dd>{{ optional($v->updated_at)->format('d/m/Y H:i') }}</dd>
                </dl>
            </div>

            {{-- Ultimi aggiornamenti km (audit) --}}
            <div class="mt-4 rounded-lg border bg-white p-4">
                <h3 class="mb-2 text-sm font-semibold">Ultimi aggiornamenti km</h3>
                <div class="text-xs text-gray-500 mb-2">Mostro gli ultimi 5.</div>
                <div class="overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left">Quando</th>
                                <th class="px-3 py-2 text-left">Da → A</th>
                                <th class="px-3 py-2 text-left">Utente</th>
                                <th class="px-3 py-2 text-left">Sorgente</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($v->mileageLogs->take(5) as $log)
                                <tr>
                                    <td class="px-3 py-2">{{ $log->changed_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2">
                                        {{ number_format((int)($log->mileage_old ?? 0), 0, ',', '.') }}
                                        →
                                        <strong>{{ number_format((int)$log->mileage_new, 0, ',', '.') }}</strong>
                                    </td>
                                    <td class="px-3 py-2">{{ $log->user?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ strtoupper($log->source) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-gray-500">Nessun aggiornamento registrato.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- FOTO --}}
        @if($tab === 'photos')
            <div id="tab-foto" class="mt-6">
                @include('pages.vehicles.partials.photos', ['vehicle' => $vehicle])
            </div>
        @endif

        {{-- DOCUMENTI --}}
        @if($tab === 'documents')
            <div class="rounded-lg border bg-white p-4 space-y-3">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs text-gray-500">Stato</label>
                        <div class="relative">
                            <select wire:model.live="docState" class="mt-1 w-48 rounded border-gray-300 pr-8">
                                <option value="">Tutti</option>
                                <option value="expired">Scaduti</option>
                                <option value="soon">≤60 giorni</option>
                                <option value="ok">Oltre 60 giorni</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Tipo</label>
                        <div class="relative">
                            <select wire:model.live="docType" class="mt-1 w-48 rounded border-gray-300 pr-8">
                                <option value="">Tutti</option>
                                @foreach($docLabels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @can('vehicle_documents.viewAny')
                        <a href="{{ route('vehicle-documents.index', ['vehicle_id' => $v->id]) }}"
                           class="ml-auto rounded bg-slate-800 px-3 py-1 text-white">
                            Apri gestione documenti
                        </a>
                    @endcan
                </div>

                <div class="overflow-auto rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-left">Numero</th>
                            <th class="px-3 py-2 text-left">Scadenza</th>
                            <th class="px-3 py-2 text-left">Giorni</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y">
                        @forelse($docsFiltered as $doc)
                            @php
                                $exp  = $doc->expiry_date ? \Illuminate\Support\Carbon::parse($doc->expiry_date)->startOfDay() : null;
                                $days = $exp ? now()->startOfDay()->diffInDays($exp, false) : null;
                                $cls  = is_null($days) ? '' : ($days <= 7 ? 'bg-rose-100 text-rose-700' : ($days <= 60 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'));
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2">{{ $docLabels[$doc->type] ?? strtoupper($doc->type) }}</td>
                                <td class="px-3 py-2">{{ $doc->number ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $exp?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @if(!is_null($days))
                                        <span class="rounded px-2 py-1 text-xs {{ $cls }}">{{ (int)$days }} gg</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">Nessun documento.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- LISTINO --}}
        @if($tab === 'pricing')
            <div class="rounded-lg border bg-white p-4 space-y-3">
                @can('vehicle_pricing.viewAny')
                    <livewire:vehicles.pricing :vehicle="$vehicle" />
                @else
                    <div class="text-sm text-gray-600">Non hai i permessi per vedere questa sezione.</div>
                @endcan
            </div>
        @endif
        
        {{-- STATO TECNICO --}}
        @if($tab === 'maintenance')
            <div class="rounded-lg border bg-white p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <span class="rounded bg-{{ $isMaintenance ? 'amber' : 'green' }}-100 px-2 py-1 text-{{ $isMaintenance ? 'amber' : 'green' }}-800 text-xs">
                        {{ $isMaintenance ? 'MANUTENZIONE APERTA' : 'OK' }}
                    </span>

                    @can('manageMaintenance', $v)
                        @if(!$isArchived)
                            @if(!$isMaintenance)
                                <button type="button" class="rounded bg-amber-600 px-2 py-1 text-white" wire:click="setMaintenance">
                                    Apri manutenzione
                                </button>
                            @else
                                <button type="button" class="rounded bg-emerald-700 px-2 py-1 text-white" wire:click="clearMaintenance">
                                    Chiudi manutenzione
                                </button>
                            @endif
                        @endif
                    @endcan
                </div>

                <div>
                    <h3 class="mb-2 font-semibold">Storico stati</h3>
                    <div class="space-y-2 text-sm">
                        @forelse($v->states as $s)
                            <div class="rounded border p-2">
                                <div class="flex flex-wrap items-center justify-between">
                                    <div>
                                        <span class="font-medium">{{ strtoupper($s->state) }}</span>
                                        <span class="text-gray-500 ml-2">{{ \Illuminate\Support\Carbon::parse($s->started_at)->format('d/m/Y H:i') }}</span>
                                        <span class="mx-1">→</span>
                                        <span class="text-gray-500">{{ $s->ended_at ? \Illuminate\Support\Carbon::parse($s->ended_at)->format('d/m/Y H:i') : '—' }}</span>
                                    </div>
                                    @if($s->reason)
                                        <div class="text-gray-600">{{ $s->reason }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-500">Nessuno stato registrato.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- ASSEGNAZIONI (readonly) --}}
        @if($tab === 'assignments')
            <div class="rounded-lg border bg-white p-4 space-y-3">
                <div class="text-sm">
                    @if($assignedNow)
                        <div>
                            Assegnato a <strong>{{ $assignedNow->renter_name }}</strong>
                            dal {{ \Illuminate\Support\Carbon::parse($assignedNow->start_at)->format('d/m/Y') }}
                            al {{ $assignedNow->end_at ? \Illuminate\Support\Carbon::parse($assignedNow->end_at)->format('d/m/Y') : '—' }}.
                        </div>
                    @else
                        <div>Attualmente <strong>libero</strong>.</div>
                    @endif
                </div>

                <div>
                    <h3 class="mb-2 font-semibold">Storico (ultime 10)</h3>
                    <div class="overflow-auto rounded border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left">Org</th>
                                    <th class="px-3 py-2 text-left">Dal</th>
                                    <th class="px-3 py-2 text-left">Al</th>
                                    <th class="px-3 py-2 text-left">Stato</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse($v->assignments->take(10) as $a)
                                    <tr>
                                        <td class="px-3 py-2">#{{ $a->renter_org_id }}</td>
                                        <td class="px-3 py-2">{{ optional($a->start_at)->format('d/m/Y') }}</td>
                                        <td class="px-3 py-2">{{ optional($a->end_at)->format('d/m/Y') ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ $a->status }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">Nessuna assegnazione.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- NOTE --}}
        @if($tab === 'notes')
            <div class="rounded-lg border bg-white p-4">
                <h3 class="mb-2 font-semibold">Note</h3>
                <div class="prose max-w-none text-sm">
                    {{ $v->notes ?: '—' }}
                </div>
            </div>
        @endif
    </div>

    {{-- Modal: Aggiorna km --}}
    <div x-data="{ open:false, current:0, value:'' }"
         x-on:open-mileage-modal.window="open=true; current=$event.detail.current; value=$event.detail.current;">
        <template x-if="open">
            <div class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/40" x-on:click="open=false"></div>
                <div class="absolute left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded bg-white p-4 shadow-xl">
                    <div class="text-lg font-semibold">Aggiorna chilometraggio</div>
                    <div class="mt-2 text-sm text-gray-600">Attuale: <strong x-text="current.toLocaleString('it-IT')"></strong> km</div>
                    <div class="mt-3">
                        <input type="number" min="0" step="1" class="w-full rounded border-gray-300"
                               x-model="value">
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded border px-3 py-1" x-on:click="open=false">Annulla</button>
                        <button type="button" class="rounded bg-indigo-600 px-3 py-1 text-white"
                                x-on:click="$wire.updateMileage(parseInt(value,10)); open=false;">
                            Salva
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
