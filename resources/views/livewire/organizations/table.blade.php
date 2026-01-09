{{-- Livewire: Organizations ▸ Tabella Renter (una riga per ogni utente dell'organizzazione) --}}
<div class="p-4">

    {{-- Barra comandi --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            {{-- Ricerca --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Cerca</label>
                <input type="text"
                       wire:model.live.debounce.400ms="search"
                       placeholder="Nome renter / nome utente / email"
                       class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                              text-gray-900 dark:text-gray-100">
            </div>

            {{-- Filtro veicoli --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Veicoli assegnati (oggi)</label>
                <select wire:model.live="countFilter"
                        class="px-6 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                               text-gray-900 dark:text-gray-100">
                    <option value="all">Tutti</option>
                    <option value="gt0">Almeno 1</option>
                    <option value="zero">Nessuno</option>
                </select>
            </div>

            {{-- Filtro stato renter (attive / archiviate / tutte) --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Stato</label>
                <select wire:model.live="statusFilter"
                        class="px-6 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                               text-gray-900 dark:text-gray-100">
                    <option value="active">Attive</option>
                    <option value="trashed">Archiviate</option>
                    <option value="all">Tutte</option>
                </select>
            </div>

            {{-- Per pagina --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Per pagina</label>
                <select wire:model.live="perPage"
                        class="px-6 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                               text-gray-900 dark:text-gray-100">
                    <option>10</option>
                    <option selected>15</option>
                    <option>25</option>
                    <option>50</option>
                </select>
            </div>
        </div>

        {{-- Nuovo renter --}}
        <div class="flex justify-end">
            <button wire:click="openCreate"
                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                           text-xs font-semibold text-white uppercase hover:bg-indigo-500
                           focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                <i class="fas fa-plus mr-1"></i> Nuovo renter
            </button>
        </div>
    </div>

    {{-- Tabella --}}
    <div class="overflow-x-auto">
        <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-300 dark:bg-gray-700">
                <tr class="uppercase tracking-wider text-left">
                    <th class="px-6 py-2 w-16">#</th>

                    {{-- Renter: sortabile --}}
                    <th class="px-6 py-2">
                        <button type="button" wire:click="setSort('name')" class="inline-flex items-center gap-1">
                            Renter
                            @if($sort === 'name')
                                <i class="fas fa-sort-alpha-{{ $dir === 'asc' ? 'down' : 'up' }}"></i>
                            @else
                                <i class="fas fa-sort text-gray-500"></i>
                            @endif
                        </button>
                    </th>

                    {{-- Utente collegato --}}
                    <th class="px-6 py-2">Utente</th>

                    {{-- Veicoli assegnati oggi: sortabile --}}
                    <th class="px-6 py-2">
                        <button type="button" wire:click="setSort('vehicles_count')" class="inline-flex items-center gap-1">
                            Veicoli assegnati
                            @if($sort === 'vehicles_count')
                                <i class="fas fa-sort-amount-{{ $dir === 'asc' ? 'up' : 'down' }}"></i>
                            @else
                                <i class="fas fa-sort text-gray-500"></i>
                            @endif
                        </button>
                    </th>
                </tr>
            </thead>

            <tbody class="bg-white dark:bg-gray-800" x-data="{ openId: null }">
                @forelse($organizations as $row)
                    @php
                        // RowKey unico per riga (org + user); se user NULL, uso 0
                        $rowKey   = $row->id . '-' . ($row->user_id ?? 0);
                        $rowNum   = $loop->iteration + ($organizations->currentPage()-1)*$organizations->perPage();
                        $vehCnt   = (int) $row->vehicles_count;
                        $userName = $row->user_name ?: '—';
                        $userMail = $row->user_email ?: '';
                    @endphp

                    {{-- Riga principale (espandibile per riga) --}}
                    <tr
                        @click="openId = openId === '{{ $rowKey }}' ? null : '{{ $rowKey }}'"
                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                        :class="openId === '{{ $rowKey }}' ? 'bg-gray-200 dark:bg-gray-700' : ''"
                    >
                        <td class="px-6 py-2 whitespace-nowrap">{{ $rowNum }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span>{{ $row->name }}</span>

                                {{-- Badge stato: utile quando mostri "Tutte" --}}
                                @if(method_exists($row, 'trashed') && $row->trashed())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                                bg-gray-300 text-gray-800 dark:bg-gray-600 dark:text-gray-100">
                                        Archiviato
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            @if($row->user_id)
                                <div class="flex flex-col">
                                    <span>{{ $userName }}</span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $userMail }}</span>
                                </div>
                            @else
                                <span class="text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap">{{ $vehCnt }}</td>
                    </tr>

                    {{-- Riga azioni (per-utente/per-riga) --}}
                    <tr x-show="openId === '{{ $rowKey }}'" x-cloak>
                        <td colspan="4" class="px-6 py-2 bg-gray-200 dark:bg-gray-700">
                            <div class="flex flex-wrap gap-6 items-center text-xs">
                                @if(! (method_exists($row, 'trashed') && $row->trashed()))
                                    {{-- Aggiungi utente (solo user su renter esistente) --}}
                                    <button type="button"
                                            wire:click.stop="openAddUser({{ $row->id }})"
                                            class="inline-flex items-center hover:text-indigo-600">
                                        <i class="fas fa-user-plus mr-1"></i> Aggiungi utente
                                    </button>

                                    {{-- Modifica: apre modale precompilando org + (eventuale) user di questa riga --}}
                                    <button type="button"
                                            wire:click.stop="openEdit({{ $row->id }}, {{ $row->user_id ?? 'null' }})"
                                            class="inline-flex items-center hover:text-yellow-600">
                                        <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                    </button>
                                    
                                    {{-- Anagrafica + Licenza (solo renter attivo) --}}
                                    <button type="button"
                                            wire:click.stop="openAnagraphic({{ $row->id }})"
                                            class="inline-flex items-center hover:text-indigo-600">
                                        <i class="fas fa-id-card mr-1"></i> Anagrafica
                                    </button>

                                    {{-- Cargos (solo renter attivo) --}}
                                    <button type="button"
                                            wire:click.stop="openCargos({{ $row->id }})"
                                            class="inline-flex items-center hover:text-indigo-600">
                                        <i class="fas fa-sim-card mr-1"></i> Cargos
                                    </button>

                                    {{-- azioni riga organization --}}
                                    @if(auth()->user()->hasRole('admin'))
                                        <button
                                            type="button"
                                            class="text-xs hover:text-indigo-600"
                                            x-on:click="Livewire.dispatch('org-fees:open', { organizationId: {{ $row->id }} })"
                                        >
                                            <i class="fas fa-percent mr-1"></i>Tassazione
                                        </button>
                                    @endif
                                @endif

                                {{-- Archivia / Abilita (soft delete / restore) --}}
                                @if(method_exists($row, 'trashed') && $row->trashed())
                                    {{-- Abilita (restore) --}}
                                    <form
                                        action="{{ route('organizations.restore', $row->id) }}"
                                        method="POST"
                                        onsubmit="return confirm('Riabilitare questo renter archiviato?');"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit" class="inline-flex items-center hover:text-green-700">
                                            <i class="fas fa-rotate-left mr-1"></i> Abilita
                                        </button>
                                    </form>
                                @else
                                    {{-- Archivia (soft delete) --}}
                                    <form
                                        action="{{ route('organizations.destroy', $row->id) }}"
                                        method="POST"
                                        onsubmit="return confirm('Archiviare questo renter? Gli utenti collegati verranno bloccati all’accesso.');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="inline-flex items-center hover:text-red-600">
                                            <i class="fas fa-box-archive mr-1"></i> Archivia
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            Nessun renter trovato.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginazione --}}
    <div class="mt-4">
        {{ $organizations->links() }}
    </div>
</div>
