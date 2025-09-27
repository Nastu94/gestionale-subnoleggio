{{-- Livewire: Organizations ▸ Tabella Renter --}}
<div class="p-4">

    {{-- Barra comandi: ricerca / filtro / perPage + pulsante Nuovo (apre modale via evento browser) --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            {{-- Ricerca --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Cerca</label>
                <input type="text"
                       wire:model.live.debounce.400ms="search"
                       placeholder="Cerca per nome…"
                       class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                              text-gray-900 dark:text-gray-100">
            </div>

            {{-- Filtro per # veicoli --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1 ">Veicoli assegnati (oggi)</label>
                <select wire:model.live="countFilter"
                        class="px-6 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                               text-gray-900 dark:text-gray-100">
                    <option value="all">Tutti</option>
                    <option value="gt0">Almeno 1</option>
                    <option value="zero">Nessuno</option>
                </select>
            </div>

            {{-- Per page --}}
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

                    {{-- Nome: colonna sortabile --}}
                    <th class="px-6 py-2">
                        <button type="button" wire:click="setSort('name')" class="inline-flex items-center gap-1">
                            Nome
                            @if($sort === 'name')
                                <i class="fas fa-sort-{{ $dir === 'asc' ? 'alpha-down' : 'alpha-up' }}"></i>
                            @else
                                <i class="fas fa-sort text-gray-500"></i>
                            @endif
                        </button>
                    </th>

                    {{-- Veicoli assegnati oggi: colonna sortabile --}}
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
                @forelse($organizations as $org)
                    @php
                        $rowNum = $loop->iteration + ($organizations->currentPage()-1)*$organizations->perPage();
                        $vehCnt = (int) $org->vehicles_count;
                    @endphp

                    {{-- Riga principale (espandibile) --}}
                    <tr
                        @click="openId = openId === {{ $org->id }} ? null : {{ $org->id }}"
                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                        :class="openId === {{ $org->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                    >
                        <td class="px-6 py-2 whitespace-nowrap">{{ $rowNum }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">{{ $org->name }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">{{ $vehCnt }}</td>
                    </tr>

                    {{-- Riga azioni --}}
                    <tr x-show="openId === {{ $org->id }}" x-cloak>
                        <td colspan="3" class="px-6 py-2 bg-gray-200 dark:bg-gray-700">
                            <div class="flex flex-wrap gap-6 items-center text-xs">
                                {{-- Modifica: apre il modale (evento browser) --}}
                                <button type="button"
                                        wire:click.stop="openEdit({{ $org->id }})"
                                        class="inline-flex items-center hover:text-yellow-600">
                                    <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                </button>

                                {{-- Elimina: lasciamo form REST classica alle tue rotte --}}
                                <form
                                    action="{{ route('organizations.destroy', $org) }}"
                                    method="POST"
                                    onsubmit="return confirm('Eliminare definitivamente questo renter?');"
                                >
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center hover:text-red-600">
                                        <i class="fas fa-trash-alt mr-1"></i> Elimina
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
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
