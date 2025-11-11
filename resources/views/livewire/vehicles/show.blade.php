{{-- resources/views/livewire/vehicles/show.blade.php --}}
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

                {{-- Azioni header --}}
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
                                <button type="button" class="rounded bg-amber-600 px-3 py-1 text-white"
                                        x-data
                                        x-on:click="$dispatch('open-maint-open-modal')">
                                    Apri manutenzione
                                </button>
                            @else
                                <button type="button" class="rounded bg-emerald-700 px-3 py-1 text-white"
                                        x-data
                                        x-on:click="$dispatch('open-maint-close-modal')">
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

                    // Aggiungi tab Danni con badge (se autorizzato)
                    if ($canViewDamages ?? false) {
                        $badge = '';
                        if (isset($damageOpenCount, $damageTotalCount)) {
                            $badge = " ({$damageOpenCount}/{$damageTotalCount})";
                        }
                        $tabs = array_merge($tabs, ['damages' => 'Danni' . $badge]);
                    }
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
            @php
                $euro = fn($cents) => is_null($cents) ? '—' : number_format($cents/100, 2, ',', '.') . ' €';
            @endphp

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

                    {{-- --- Nuovi campi costi --- --}}
                    <dt class="text-gray-500">Noleggio L/T (mensile)</dt>
                    <dd>{{ $euro($v->lt_rental_monthly_cents) }}</dd>

                    <dt class="text-gray-500">Franchigia RCA</dt>
                    <dd>{{ $euro($v->insurance_rca_cents) }}</dd>

                    <dt class="text-gray-500">Franchigia Kasko</dt>
                    <dd>{{ $euro($v->insurance_kasko_cents) }}</dd>

                    <dt class="text-gray-500">Franchigia Cristalli</dt>
                    <dd>{{ $euro($v->insurance_cristalli_cents) }}</dd>

                    <dt class="text-gray-500">Franchigia Furto/Incendio</dt>
                    <dd>{{ $euro($v->insurance_furto_cents) }}</dd>
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
                                <button type="button"
                                        class="rounded bg-amber-600 px-2 py-1 text-white"
                                        x-data
                                        x-on:click="$dispatch('open-maint-open-modal')">
                                    Apri manutenzione
                                </button>
                            @else
                                <button type="button"
                                        class="rounded bg-emerald-700 px-2 py-1 text-white"
                                        x-data
                                        x-on:click="$dispatch('open-maint-close-modal')">
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

                                {{-- Dettaglio manutenzione --}}
                                @if($s->state === 'maintenance')
                                    @php
                                        $meta = $s->maintenanceDetail;
                                        $euro = fn($c) => is_null($c) ? null : number_format($c/100, 2, ',', '.').' €';
                                    @endphp
                                    <dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                        <dt class="text-gray-500">Officina/Luogo</dt>
                                        <dd>{{ $meta?->workshop ?? '—' }}</dd>

                                        <dt class="text-gray-500">Costo</dt>
                                        <dd>{{ isset($meta?->cost_cents) ? $euro((int)$meta->cost_cents) : '—' }}</dd>

                                        @if(!empty($meta?->notes))
                                            <dt class="text-gray-500">Note</dt>
                                            <dd>{{ $meta->notes }}</dd>
                                        @endif
                                    </dl>
                                @endif
                            </div>
                        @empty
                            <div class="text-gray-500">Nessuno stato registrato.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        {{-- ASSEGNAZIONI --}}
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

        {{-- DANNI --}}
        @if($tab === 'damages' && ($canViewDamages ?? false))
            @php
                $fmtEur = fn($v) => is_null($v) ? '—' : number_format((float)$v, 2, ',', '.') . ' €';
                $sevCls = function($sev) {
                    return match($sev) {
                        'low'    => 'bg-green-100 text-green-800',
                        'medium' => 'bg-amber-100 text-amber-800',
                        'high'   => 'bg-rose-100 text-rose-700',
                        default  => 'bg-gray-100 text-gray-700',
                    };
                };
            @endphp

            <div class="rounded-lg border bg-white p-4 space-y-4">

                {{-- KPI --}}
                <div class="flex flex-wrap gap-3 text-sm">
                    <span class="inline-flex items-center rounded bg-slate-100 px-2 py-1">Aperti: <strong class="ml-1">{{ $damageOpenCount }}</strong></span>
                    <span class="inline-flex items-center rounded bg-slate-100 px-2 py-1">Totali: <strong class="ml-1">{{ $damageTotalCount }}</strong></span>
                    <span class="inline-flex items-center rounded bg-slate-100 px-2 py-1">Costo riparazioni (12 mesi): <strong class="ml-1">{{ $fmtEur($damageCost12m) }}</strong></span>
                </div>

                {{-- Filtri --}}
                <div class="grid md:grid-cols-6 gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500">Stato</label>
                        <select class="mt-1 w-full rounded border-gray-300" wire:model.live="damageStatus">
                            <option value="open">Aperti</option>
                            <option value="closed">Chiusi</option>
                            <option value="all">Tutti</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Origine</label>
                        <select class="mt-1 w-full rounded border-gray-300" wire:model.live="damageSource">
                            <option value="">Tutte</option>
                            <option value="manual">Manuale</option>
                            <option value="inspection">Ispezione</option>
                            <option value="service">Officina</option>
                            <option value="rental">Rental</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Severità (non-rental)</label>
                        <select class="mt-1 w-full rounded border-gray-300" wire:model.live="damageSeverity">
                            <option value="">Tutte</option>
                            <option value="low">Bassa</option>
                            <option value="medium">Media</option>
                            <option value="high">Alta</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500">Ricerca</label>
                        <input type="text" class="mt-1 w-full rounded border-gray-300" placeholder="area/descrizione/note…" wire:model.live="damageSearch">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs text-gray-500">Ordina</label>
                        <select class="mt-1 w-full rounded border-gray-300" wire:model.live="damageSort">
                            <option value="default">Default</option>
                            <option value="opened_desc">Apertura ↓</option>
                            <option value="opened_asc">Apertura ↑</option>
                            <option value="closed_desc">Chiusura ↓</option>
                            <option value="closed_asc">Chiusura ↑</option>
                            <option value="cost_desc">Costo ↓</option>
                            <option value="cost_asc">Costo ↑</option>
                            <option value="severity_desc">Severità ↓</option>
                            <option value="severity_asc">Severità ↑</option>
                            <option value="origin_asc">Origine A→Z</option>
                            <option value="origin_desc">Origine Z→A</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Dal</label>
                        <input type="date" class="mt-1 w-full rounded border-gray-300" wire:model.live="damageFromDate">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Al</label>
                        <input type="date" class="mt-1 w-full rounded border-gray-300" wire:model.live="damageToDate">
                    </div>
                    <div class="md:col-span-4">
                        <button type="button" class="mt-6 inline-flex h-9 items-center rounded border px-3 text-slate-700"
                                wire:click="resetDamageFilters">
                            Reimposta filtri
                        </button>
                    </div>
                </div>

                {{-- === NUOVO DANNO (collassabile) === --}}
                @can('vehicle_damages.create', $v)
                <div class="rounded border bg-white p-3">
                    <div x-data="{open:false}">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold">Nuovo danno</div>
                            <button type="button"
                                    class="text-xs rounded px-2 py-1 ring-1 ring-slate-300"
                                    x-on:click="open=!open"
                                    x-text="open ? 'Chiudi' : 'Apri'"></button>
                        </div>

                        <div class="mt-3" x-show="open" x-cloak>
                            <div class="grid sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500">Origine</label>
                                    <select wire:model.defer="newDamage.source" class="mt-1 w-full rounded border-gray-300">
                                        <option value="manual">Manuale</option>
                                        <option value="inspection">Ispezione</option>
                                        <option value="service">Officina/Service</option>
                                        {{-- NIENTE 'rental': i danni rental nascono dalle checklist --}}
                                    </select>
                                    @error('newDamage.source')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                                </div>

                                <div>
                                    <label class="block text-xs text-gray-500">Area</label>
                                    <select wire:model.defer="newDamage.area" class="mt-1 w-full rounded border-gray-300">
                                        <option value="">{{ __('—') }}</option>
                                        <option value="front">Anteriore</option>
                                        <option value="rear">Posteriore</option>
                                        <option value="left">Sinistra</option>
                                        <option value="right">Destra</option>
                                        <option value="interior">Interno</option>
                                        <option value="roof">Tetto</option>
                                        <option value="windshield">Parabrezza</option>
                                        <option value="wheel">Ruota</option>
                                        <option value="other">Altro</option>
                                    </select>
                                    @error('newDamage.area')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                                </div>

                                <div>
                                    <label class="block text-xs text-gray-500">Severità</label>
                                    <select wire:model.defer="newDamage.severity" class="mt-1 w-full rounded border-gray-300">
                                        <option value="">{{ __('—') }}</option>
                                        <option value="low">Bassa</option>
                                        <option value="medium">Media</option>
                                        <option value="high">Alta</option>
                                    </select>
                                    @error('newDamage.severity')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                                </div>

                                <div class="sm:col-span-4">
                                    <label class="block text-xs text-gray-500">Descrizione</label>
                                    <textarea rows="2" wire:model.defer="newDamage.description"
                                            class="mt-1 w-full rounded border-gray-300"
                                            placeholder="Dettagli del danno (opzionale)…"></textarea>
                                    @error('newDamage.description')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="mt-3 flex justify-end">
                                <button type="button" wire:click="createDamage"
                                        class="rounded bg-slate-800 px-3 py-1.5 text-white">
                                    Aggiungi danno
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endcan

                {{-- Tabella Danni con riga espansa (no chevron) --}}
                <div class="overflow-x-auto rounded border">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left">Stato</th>
                                <th class="px-3 py-2 text-left">Origine</th>
                                <th class="px-3 py-2 text-left">Area</th>
                                <th class="px-3 py-2 text-left">Severità</th>
                                <th class="px-3 py-2 text-left">Descrizione</th>
                                <th class="px-3 py-2 text-left">Aperto il</th>
                                <th class="px-3 py-2 text-left">Chiuso il</th>
                                <th class="px-3 py-2 text-left">Costo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @php //dd($damages); @endphp
                        @forelse($damages as $d)
                            @php
                                $statusCls = $d->is_open ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800';
                                $sev = $d->resolved_severity ?? $d->severity;
                                $sevLabel  = ['low'=>'Bassa','medium'=>'Media','high'=>'Alta'][$sev] ?? '—';  
                                $sevClass = $sevCls($sev);
                                $area = $d->resolved_area ?? $d->area;
                                $desc = $d->resolved_description ?? $d->description;
                                $rentalId = $d->firstRentalDamage?->rental_id ?? null; // richiede eager load rental_id o farà lazy loading
                                $areaKey   = $d->resolved_area ?? $d->area;
                                $areaLabel = $areaKey ? ($areaLabels[$areaKey] ?? $areaKey) : '—';                              
                            @endphp

                            {{-- Riga principale (click per espandere) --}}
                            <tr wire:key="damage-row-{{ $d->id }}"
                                class="hover:bg-gray-50 cursor-pointer"
                                wire:click="toggleDamageRow({{ $d->id }})">
                                <td class="px-3 py-2" x-on:click.stop>
                                    <span class="rounded px-2 py-0.5 text-xs {{ $statusCls }}">
                                        {{ $d->is_open ? 'Aperto' : 'Chiuso' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2" x-on:click.self="open=!open">
                                    {{ strtoupper($d->source) }}
                                    @if($d->source === 'rental' && $rentalId)
                                        {{-- Link al rental: aggiorna il nome rotta se diverso --}}
                                        <a class="ml-1 text-indigo-600 underline" href="{{ route('rentals.show', $rentalId) }}" target="_blank">apri rental</a>
                                    @endif
                                </td>
                                <td class="px-3 py-2" x-on:click.self="open=!open">{{ $areaLabel }}</td>
                                <td class="px-3 py-2" x-on:click.self="open=!open">
                                    @if($sev)
                                        <span class="rounded px-2 py-0.5 text-xs {{ $sevClass }}">{{ strtoupper($sevLabel) }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2" x-on:click.self="open=!open">
                                    <span title="{{ $desc }}">{{ \Illuminate\Support\Str::limit($desc, 60) ?: '—' }}</span>
                                </td>
                                <td class="px-3 py-2" x-on:click.self="open=!open">{{ $d->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-3 py-2" x-on:click.self="open=!open">{{ $d->fixed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-3 py-2" x-on:click.self="open=!open">
                                    {{ $d->repair_cost !== null ? $fmtEur($d->repair_cost) : '—' }}
                                </td>
                            </tr>

                            {{-- Riga espansa: azioni + link noleggio (solo se source=rental) --}}
                            @if($expandedDamageId === $d->id)
                                <tr wire:key="damage-row-expanded-{{ $d->id }}" class="bg-gray-50/60">
                                    <td colspan="7" class="px-3 py-3">
                                        <div class="flex flex-wrap items-center gap-3 text-sm">

                                            {{-- Link al noleggio solo per source=rental e se esiste rental_id --}}
                                            @if($d->source === 'rental' && ($rid = $d->firstRentalDamage?->rental_id))
                                                <a href="{{ route('rentals.show', $rid) }}"
                                                class="inline-flex items-center text-indigo-700 hover:underline">
                                                    Apri noleggio #{{ $rid }}
                                                </a>

                                                <span class="text-gray-400">|</span>
                                            @endif

                                            {{-- Azioni con confirm (niente modali) --}}
                                            <button x-data
                                                    x-on:click.stop.prevent="$wire.openDamagePhotosSidebar({{ $d->id }})"
                                                    class="rounded bg-slate-700 px-2 py-1 text-white text-xs">
                                                Visualizza foto
                                            </button>

                                            <span class="text-gray-400">|</span>

                                            {{-- Azioni con confirm (niente modali) --}}
                                            @if($d->is_open)
                                                @can('vehicle_damages.close', $d)
                                                    <button x-data
                                                            x-on:click.stop.prevent="$wire.openCloseDamageModal({{ $d->id }})"
                                                            class="rounded bg-emerald-700 px-2 py-1 text-white text-xs">
                                                        Chiudi
                                                    </button>
                                                @endcan
                                            @else
                                                @can('vehicle_damages.reopen', $d)
                                                    <button x-data
                                                            x-on:click.stop.prevent="if(confirm('Riaprire questo danno?')) $wire.reopenDamage({{ $d->id }})"
                                                            class="rounded bg-amber-600 px-2 py-1 text-white text-xs">
                                                        Riapri
                                                    </button>
                                                @endcan
                                            @endif

                                            {{-- Pulsante MODIFICA (solo danni non da rental) --}}
                                            @if($d->source !== 'rental')
                                                @can('update', $d)
                                                    <button
                                                        x-data
                                                        x-on:click.stop.prevent="$wire.openEditDamageModal({{ $d->id }})"
                                                        class="rounded bg-indigo-600 px-2 py-1 text-white text-xs">
                                                        Modifica
                                                    </button>
                                                @endcan
                                            @endif

                                            @can('vehicle_damages.delete', $d)
                                                <button x-data
                                                        x-on:click.stop.prevent="if(confirm('Eliminare definitivamente questo danno?')) $wire.deleteDamage({{ $d->id }})"
                                                        class="rounded bg-rose-600 px-2 py-1 text-white text-xs">
                                                    Elimina
                                                </button>
                                            @endcan

                                            {{-- Info extra: costo riparazione e note --}}
                                            @if(!is_null($d->repair_cost) || $d->notes)
                                                <span class="text-gray-400">|</span>
                                                <div class="text-xs text-gray-600">
                                                    @if(!is_null($d->repair_cost))
                                                        Costo rip.: <strong>{{ number_format((float)$d->repair_cost, 2, ',', '.') }} €</strong>
                                                    @endif
                                                    @if($d->notes)
                                                        <span class="ml-2">Note: {{ $d->notes }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">Nessun danno trovato.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif($tab === 'damages')
            <div class="rounded-lg border bg-white p-4">
                <div class="text-sm text-gray-600">Non hai i permessi per vedere questa sezione.</div>
            </div>
        @endif
    </div>

    {{-- Modal: Aggiorna km --}}
    <div x-data="{ open:false, current:0, value:'' }"
         x-on:open-mileage-modal.window="open=true; current=$event.detail.current; value=$event.detail.current;">
        <template x-if="open">
            <div class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/40"></div>
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

    {{-- MODALE: APRI MANUTENZIONE --}}
    <div x-data="{ open:false }"
        x-on:open-maint-open-modal.window="open=true; $wire.set('maintWorkshop', null); $wire.set('maintNotes', null);">
        <template x-if="open">
            <div class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="absolute left-1/2 top-1/2 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded bg-white p-4 shadow-xl">
                    <div class="text-lg font-semibold">Apri manutenzione</div>
                    <div class="mt-3 grid gap-3">
                        <div>
                            <label class="block text-xs text-gray-500">Officina/Luogo *</label>
                            <input type="text" class="mt-1 w-full rounded border-gray-300"
                                wire:model.defer="maintWorkshop" maxlength="128" placeholder="Es. Officina Rossi, Via…">
                            @error('maintWorkshop')<div class="mt-1 text-xs text-rose-600">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Note (opz.)</label>
                            <textarea rows="3" class="mt-1 w-full rounded border-gray-300"
                                    wire:model.defer="maintNotes" placeholder="Dettagli…"></textarea>
                            @error('maintNotes')<div class="mt-1 text-xs text-rose-600">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded border px-3 py-1" x-on:click="open=false">Annulla</button>
                        <button type="button" class="rounded bg-amber-600 px-3 py-1 text-white"
                                x-on:click="$wire.setMaintenance().then(() => { open=false; })">
                            Apri
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- MODALE: CHIUDI MANUTENZIONE --}}
    <div x-data="{ open:false }"
        x-on:open-maint-close-modal.window="open=true; $wire.set('maintCloseCostEur', null); $wire.set('maintNotes', null);">
        <template x-if="open">
            <div class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="absolute left-1/2 top-1/2 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded bg-white p-4 shadow-xl">
                    <div class="text-lg font-semibold">Chiudi manutenzione</div>
                    <div class="mt-3 grid gap-3">
                        <div>
                            <label class="block text-xs text-gray-500">Costo totale (€) *</label>
                            <input type="number" min="0" step="0.01" class="mt-1 w-full rounded border-gray-300"
                                wire:model.defer="maintCloseCostEur" placeholder="0,00">
                            @error('maintCloseCostEur')<div class="mt-1 text-xs text-rose-600">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Note (opz.)</label>
                            <textarea rows="3" class="mt-1 w-full rounded border-gray-300"
                                    wire:model.defer="maintNotes" placeholder="Esito, ricambi, ecc."></textarea>
                            @error('maintNotes')<div class="mt-1 text-xs text-rose-600">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded border px-3 py-1" x-on:click="open=false">Annulla</button>
                        <button type="button" class="rounded bg-emerald-700 px-3 py-1 text-white"
                                x-on:click="$wire.clearMaintenance().then(() => { open=false; })">
                            Chiudi
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- MODALE: CHIUDI DANNO (costo + note) --}}
    <div x-data="{ open: @entangle('isCloseDamageModalOpen') }">
        <template x-if="open">
            <div class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/40"></div>

                <div class="absolute left-1/2 top-1/2 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded bg-white p-4 shadow-xl">
                    <div class="text-lg font-semibold">Chiudi danno</div>

                    <div class="mt-3 grid gap-3">
                        <div>
                            <label class="block text-xs text-gray-500">Costo riparazione (€) *</label>
                            <input type="number" step="0.01" min="0"
                                class="mt-1 w-full rounded border-gray-300"
                                wire:model.defer="damageCloseCostEur"
                                placeholder="0,00">
                            @error('damageCloseCostEur')
                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500">Note (opz.)</label>
                            <textarea rows="3" class="mt-1 w-full rounded border-gray-300"
                                    wire:model.defer="damageCloseNotes"
                                    placeholder="Dettagli intervento, ricambi, ecc."></textarea>
                            @error('damageCloseNotes')
                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded border px-3 py-1" x-on:click="open=false">Annulla</button>
                        <button type="button" class="rounded bg-emerald-700 px-3 py-1 text-white"
                                x-on:click="$wire.performCloseDamage()">
                            Salva chiusura
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- MODALE: MODIFICA DANNO (solo non-rental) --}}
    <div x-data="{ open: @entangle('isEditDamageModalOpen') }">
        <template x-if="open">
            <div class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/40" x-on:click="open=false"></div>

                <div class="absolute left-1/2 top-1/2 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded bg-white dark:bg-gray-900 p-4 shadow-xl">
                    <div class="text-lg font-semibold">Modifica danno</div>

                    <div class="mt-3 grid gap-3">
                        <div>
                            <label class="block text-xs text-gray-500">Severità *</label>
                            <select
                                class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                                wire:model.defer="editSeverity">
                                <option value="low">Bassa</option>
                                <option value="medium">Media</option>
                                <option value="high">Alta</option>
                            </select>
                            @error('editSeverity')
                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500">Descrizione *</label>
                            <textarea rows="4"
                                    class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                                    wire:model.defer="editDescription"></textarea>
                            @error('editDescription')
                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded border px-3 py-1" x-on:click="open=false">
                            Annulla
                        </button>
                        <button type="button"
                                class="rounded bg-indigo-600 px-3 py-1 text-white"
                                wire:click="performEditDamage">
                            Salva
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- === Sidebar: Foto del danno === --}}
    @if($isDamagePhotosSidebarOpen)
        <div class="fixed inset-0 z-50">
            {{-- Backdrop: chiude sidebar al click --}}
            <div class="fixed inset-0 bg-black/40" wire:click="closeDamagePhotosSidebar"></div>

            {{-- Pannello laterale destro --}}
            <aside class="fixed right-0 top-0 h-full w-full max-w-xl bg-white dark:bg-gray-900 shadow-xl
                        border-l border-gray-200 dark:border-gray-700 p-4 overflow-y-auto">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-base truncate">
                            Foto danno #{{ $damageIdViewing }}
                        </h3>
                        @php
                            $srcMap = [
                                'rental'     => 'Da noleggio (checklist)',
                                'manual'     => 'Inserito manualmente',
                                'inspection' => 'Da ispezione',
                                'service'    => 'Da officina',
                            ];
                            $srcLabel = $srcMap[$viewingDamageSource ?? ''] ?? '—';
                        @endphp
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Origine: <span class="font-medium">{{ $srcLabel }}</span> •
                            Foto: <span class="font-medium">{{ is_countable($damagePhotos) ? count($damagePhotos) : 0 }}</span>
                        </div>
                    </div>

                    <button type="button"
                            class="rounded border px-3 py-1 text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
                            wire:click="closeDamagePhotosSidebar">
                        Chiudi
                    </button>
                </div>

                @if(empty($damagePhotos))
                    <div class="mt-6 text-sm text-gray-500">
                        Nessuna foto collegata a questo danno.
                    </div>
                @else
                    <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($damagePhotos as $m)
                            <a wire:key="damage-photo-{{ $m['id'] ?? $loop->index }}"
                            href="{{ $m['url'] }}"
                            target="_blank"
                            class="group block rounded border overflow-hidden hover:shadow transition">
                                <div class="aspect-[4/3] bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                    <img src="{{ $m['thumb'] ?? $m['url'] }}"
                                        alt="{{ $m['file_name'] ?? 'foto-danno' }}"
                                        class="w-full h-full object-cover"
                                        loading="lazy" referrerpolicy="no-referrer">
                                </div>

                                <div class="px-2 py-1 text-xs flex items-center justify-between gap-2">
                                    <span class="truncate" title="{{ $m['file_name'] ?? '' }}">
                                        {{ \Illuminate\Support\Str::limit($m['file_name'] ?? '', 28) }}
                                    </span>
                                    @if(!empty($m['created_at']))
                                        <span class="text-gray-500 whitespace-nowrap">{{ $m['created_at'] }}</span>
                                    @endif
                                </div>

                                @php
                                    $badge = ($m['origin'] ?? null) === 'rental_damage' ? 'Checklist' : 'Danno veicolo';
                                @endphp
                                <div class="px-2 pb-2">
                                    <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-800
                                                text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400 px-1.5 py-0.5">
                                        {{ $badge }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </aside>
        </div>
    @endif
</div>
