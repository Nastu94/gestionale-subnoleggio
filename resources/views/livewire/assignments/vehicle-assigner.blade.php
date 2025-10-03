<div class="space-y-6">

    {{-- FILTRI SUPERIORI --}}
    <div class="bg-white shadow rounded p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Renter --}}
            <div>
                <label class="block text-sm font-medium mb-1">Renter (organizzazione)</label>
                <select class="w-full border rounded p-2" wire:model.live="renterOrgId">
                    <option value="">— seleziona —</option>
                    @foreach($renterOptions as $opt)
                        <option value="{{ $opt->id }}">{{ $opt->name }}</option>
                    @endforeach
                </select>
                @error('renterOrgId') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Dal --}}
            <div>
                <label class="block text-sm font-medium mb-1">Dal</label>
                <input type="datetime-local" class="w-full border rounded p-2" wire:model.live="dateFrom">
                @error('dateFrom') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Al (facoltativo) --}}
            <div>
                <label class="block text-sm font-medium mb-1">Al (facoltativo)</label>
                {{-- FIX: binding mancante su dateTo --}}
                <input type="datetime-local" class="w-full border rounded p-2" wire:model.live="dateTo">
                @error('dateTo') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Ricerca libera --}}
            <div>
                <label class="block text-sm font-medium mb-1">Cerca (targa, marca, modello, …)</label>
                <input type="text" class="w-full border rounded p-2" placeholder="es. *AB 123*" wire:model.live.debounce.400ms="q">
            </div>
        </div>

        {{-- Filtri secondari --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Alimentazione</label>
                <input class="w-full border rounded p-2" wire:model.live="filters.fuel_type" placeholder="es. diesel">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Cambio</label>
                <input class="w-full border rounded p-2" wire:model.live="filters.transmission" placeholder="es. automatico">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Posti</label>
                <input type="number" class="w-full border rounded p-2" wire:model.live="filters.seats" min="1">
            </div>
            <label class="inline-flex items-center space-x-2 mt-6">
                <input type="checkbox" class="border rounded" wire:model.live="filters.only_available">
                <span class="text-sm">Solo disponibili nel range</span>
            </label>
        </div>
    </div>

    {{-- DUE COLONNE --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- SINISTRA: Veicoli disponibili / filtrati --}}
        <div class="bg-white shadow rounded">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="font-semibold">Veicoli</h3>
                <div class="flex items-center gap-2">
                    {{-- Seleziona/Deseleziona TUTTI della pagina corrente --}}
                    <div x-data>
                        <button class="px-3 py-2 border rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                                type="button"
                                wire:click="toggleSelectAll"
                                :disabled="!$wire.get('renterOrgId')">
                            Seleziona/Deseleziona pagina
                        </button>
                    </div>

                    {{-- Conferma assegnazione --}}
                    <div x-data>
                        <button class="px-3 py-2 bg-blue-600 text-white rounded disabled:opacity-50"
                                wire:click="assignSelected"
                                :disabled="!$wire.get('renterOrgId') || $wire.get('selectedVehicleIds').length === 0">
                            Assegna al renter
                        </button>
                    </div>
                </div>
            </div>

            <div class="divide-y">
                @forelse($vehicles as $v)
                    @php $available = $this->isVehicleAvailable($v->id); @endphp
                    {{-- Chiave stabile per diff corretto e conteggio selezionati in tempo reale --}}
                    <label class="flex items-center justify-between p-3" wire:key="vehicle-row-{{ $v->id }}">
                        <div class="flex items-center space-x-3">
                            <input
                                type="checkbox"
                                class="border rounded"
                                {{-- Sync immediato col server --}}
                                wire:model.live="selectedVehicleIds"
                                value="{{ $v->id }}"
                                @disabled(!$available || !$renterOrgId)
                            >
                            <div>
                                <div class="font-medium">
                                    {{ $v->make }} {{ $v->model }}
                                    <span class="text-sm text-gray-500">({{ $v->plate }})</span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $v->fuel_type }} • {{ $v->transmission }} • {{ $v->seats }} posti
                                </div>
                            </div>
                        </div>
                        <span class="text-xs px-2 py-1 rounded {{ $available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $available ? 'disponibile' : 'occupato' }}
                        </span>
                    </label>
                @empty
                    <p class="p-4 text-sm text-gray-500">Nessun veicolo trovato.</p>
                @endforelse
            </div>

            <div class="p-3">
                {{ $vehicles->onEachSide(1)->links() }}
            </div>
        </div>

        {{-- DESTRA: Riepilogo azione / Messaggi + Tabella assegnazioni --}}
        <div class="bg-white shadow rounded p-4 space-y-3">
            <h3 class="font-semibold">Riepilogo</h3>
            <ul class="text-sm text-gray-700">
                <li><strong>Renter:</strong>
                    @php $r = $renterOptions->firstWhere('id', $renterOrgId); @endphp
                    {{ $r?->name ?? '—' }}
                </li>
                <li><strong>Periodo:</strong> {{ $dateFrom }} → {{ $dateTo ?: 'aperto' }}</li>
                <li><strong>Selezionati:</strong> {{ count($selectedVehicleIds) }}</li>
            </ul>

            @if($confirmMessage)
                <div class="p-3 bg-gray-50 border rounded text-sm"
                     x-data="{ show: true }" x-init="setTimeout(() => show = false, 8000)" x-show="show"
                     x-transition.opacity.duration.400ms>
                    {{ $confirmMessage }}
                </div>
            @endif

            <p class="text-xs text-gray-500">
                Le assegnazioni create avranno stato <em>scheduled</em> se future,
                altrimenti <em>active</em>. Gli overlap con altre assegnazioni o blocchi vengono bloccati.
            </p>

            {{-- Tabella assegnazioni del renter selezionato --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold">Assegnazioni del renter selezionato</h3>
                    <div class="inline-flex rounded border overflow-hidden">
                        <button type="button"
                                class="px-3 py-1 text-sm {{ $tab === 'active' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800' }}"
                                wire:click="changeTab('active')">
                            Attive
                        </button>
                        <button type="button"
                                class="px-3 py-1 text-sm {{ $tab === 'scheduled' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800' }}"
                                wire:click="changeTab('scheduled')">
                            Programmate
                        </button>
                        <button type="button"
                                class="px-3 py-1 text-sm {{ $tab === 'history' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800' }}"
                                wire:click="changeTab('history')">
                            Storico
                        </button>
                    </div>
                </div>

                @if(!$renterOrgId)
                    <p class="text-sm text-gray-500">Seleziona un'organizzazione per vedere le assegnazioni.</p>
                @else
                    <div class="overflow-x-auto overflow-y-visible border rounded">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr class="text-left">
                                    <th class="px-3 py-2">Veicolo</th>
                                    <th class="px-3 py-2">Periodo</th>
                                    <th class="px-3 py-2">Stato</th>
                                    <th class="px-3 py-2">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse($this->assignments as $a)
                                    <tr wire:key="a-{{ $a->id }}">
                                        <td class="px-3 py-2">
                                            @if($a->vehicle)
                                                <div class="font-medium">{{ $a->vehicle->make }} {{ $a->vehicle->model }}</div>
                                                <div class="text-xs text-gray-500">{{ $a->vehicle->plate }}</div>
                                            @else
                                                <span class="text-xs text-gray-400">[veicolo rimosso]</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <div>
                                                {{ \Illuminate\Support\Carbon::parse($a->start_at)->format('Y-m-d H:i') }}
                                                →
                                                {{ $a->end_at ? \Illuminate\Support\Carbon::parse($a->end_at)->format('Y-m-d H:i') : 'aperto' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            @php
                                                $badge = match($a->status) {
                                                    'active'    => 'bg-green-100 text-green-800',
                                                    'scheduled' => 'bg-blue-100 text-blue-800',
                                                    'ended'     => 'bg-gray-200 text-gray-800',
                                                    'revoked'   => 'bg-red-100 text-red-800',
                                                    default     => 'bg-gray-100 text-gray-700',
                                                };
                                            @endphp
                                            <span class="text-xs px-2 py-1 rounded {{ $badge }}">{{ $a->status }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            {{-- Menu azioni teletrasportato nel body per non "rompere" la tabella --}}
                                            <div x-data="{ open:false, rect:null }" class="relative inline-block" x-id="['menu']">
                                                <button x-ref="btn"
                                                        @click="open=!open; rect=$refs.btn.getBoundingClientRect()"
                                                        class="px-2 py-1 text-xs rounded border border-gray-300 hover:bg-gray-50 dark:border-gray-700">
                                                    Azioni ▾
                                                </button>

                                                <template x-teleport="body">
                                                    <div x-cloak x-show="open" @click.outside="open=false" x-transition
                                                         class="fixed z-50"
                                                         :style="rect ? `top:${rect.bottom + window.scrollY}px; left:${rect.right - 224 + window.scrollX}px; width:224px` : ''">
                                                        <div class="bg-white dark:bg-gray-800 border dark:border-gray-700 rounded shadow-lg">
                                                            @if($a->status === 'active')
                                                                <button class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 dark:hover:bg-gray-700"
                                                                        @click.prevent="$wire.closeAssignmentNow({{ $a->id }}); open=false">
                                                                    Chiudi ora
                                                                </button>
                                                                <div class="px-3 py-2 border-t dark:border-gray-700">
                                                                    <label class="sr-only">Nuova data fine</label>
                                                                    <input type="datetime-local"
                                                                           class="w-full border rounded px-2 py-1 text-xs dark:bg-gray-800 dark:border-gray-700"
                                                                           wire:model.live="extend.{{ $a->id }}">
                                                                    <button class="mt-2 w-full px-2 py-1 text-xs rounded border border-blue-300 text-blue-700 hover:bg-blue-50 dark:border-blue-400/40 dark:text-blue-300"
                                                                            @click.prevent="$wire.extendAssignment({{ $a->id }}); open=false">
                                                                        Estendi
                                                                    </button>
                                                                </div>
                                                                <button class="w-full text-left px-3 py-2 text-xs text-red-700 hover:bg-red-50 dark:hover:bg-gray-700"
                                                                        @click.prevent="confirm('Revocare questa assegnazione?') && ($wire.deleteAssignment({{ $a->id }}), open=false)">
                                                                    Revoca
                                                                </button>
                                                            @elseif($a->status === 'scheduled')
                                                                <button class="w-full text-left px-3 py-2 text-xs text-red-700 hover:bg-red-50 dark:hover:bg-gray-700"
                                                                        @click.prevent="confirm('Annullare questa assegnazione programmata?') && ($wire.deleteAssignment({{ $a->id }}), open=false)">
                                                                    Annulla
                                                                </button>
                                                                <div class="px-3 py-2 border-t dark:border-gray-700">
                                                                    <input type="datetime-local"
                                                                           class="w-full border rounded px-2 py-1 text-xs dark:bg-gray-800 dark:border-gray-700"
                                                                           wire:model.live="extend.{{ $a->id }}">
                                                                    <button class="mt-2 w-full px-2 py-1 text-xs rounded border border-blue-300 text-blue-700 hover:bg-blue-50 dark:border-blue-400/40 dark:text-blue-300"
                                                                            @click.prevent="$wire.extendAssignment({{ $a->id }}); open=false">
                                                                        Estendi
                                                                    </button>
                                                                </div>
                                                            @else
                                                                <button class="w-full text-left px-3 py-2 text-xs text-red-700 hover:bg-red-50 dark:hover:bg-gray-700"
                                                                        @click.prevent="confirm('Eliminare definitivamente questa riga? (lo storico degli stati resta)') && ($wire.deleteAssignment({{ $a->id }}), open=false)">
                                                                    Elimina
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-4 text-sm text-gray-500">
                                            Nessuna assegnazione trovata per questa tab.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginazione separata per la tabella assegnazioni --}}
                    <div class="pt-2">
                        {{ $this->assignments->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
