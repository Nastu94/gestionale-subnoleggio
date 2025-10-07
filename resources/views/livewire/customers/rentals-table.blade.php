{{-- resources/views/livewire/customers/rentals-table.blade.php --}}

<div>
    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Contratti del cliente</h3>

    {{-- Barra comandi (stile coerente) --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-3">
        <div class="flex flex-wrap items-end gap-3">
            {{-- Ricerca --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Cerca</label>
                <input type="text"
                       wire:model.live.debounce.400ms="search"
                       placeholder="ID contratto / stato / targa"
                       class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                              text-gray-900 dark:text-gray-100">
            </div>

            {{-- Per pagina --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Per pagina</label>
                <select wire:model.live="perPage"
                        class="px-6 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                               text-gray-900 dark:text-gray-100">
                    <option>10</option>
                    <option>15</option>
                    <option>25</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end">
            <span class="text-xs text-gray-500 dark:text-gray-400 italic">
                Elenco in sola lettura
            </span>
        </div>
    </div>

    {{-- Tabella contratti --}}
    <div class="overflow-x-auto">
        <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-300 dark:bg-gray-700">
                <tr class="uppercase tracking-wider text-left">
                    <th class="px-6 py-2 w-16">#</th>

                    {{-- ID contratto (sortabile) --}}
                    <th class="px-6 py-2">
                        <button type="button" wire:click="setSort('id')" class="inline-flex items-center gap-1">
                            ID
                            @if($sort === 'id')
                                <i class="fas fa-sort-numeric-{{ $dir === 'asc' ? 'down' : 'up' }}"></i>
                            @else
                                <i class="fas fa-sort text-gray-500"></i>
                            @endif
                        </button>
                    </th>

                    <th class="px-6 py-2">Veicolo</th>

                    {{-- Stato (sortabile) --}}
                    <th class="px-6 py-2">
                        <button type="button" wire:click="setSort('status')" class="inline-flex items-center gap-1">
                            Stato
                            @if($sort === 'status')
                                <i class="fas fa-sort-alpha-{{ $dir === 'asc' ? 'down' : 'up' }}"></i>
                            @else
                                <i class="fas fa-sort text-gray-500"></i>
                            @endif
                        </button>
                    </th>

                    {{-- Creato il (sortabile) --}}
                    <th class="px-6 py-2">
                        <button type="button" wire:click="setSort('created_at')" class="inline-flex items-center gap-1">
                            Creato il
                            @if($sort === 'created_at')
                                <i class="fas fa-sort-amount-{{ $dir === 'asc' ? 'up' : 'down' }}"></i>
                            @else
                                <i class="fas fa-sort text-gray-500"></i>
                            @endif
                        </button>
                    </th>

                    <th class="px-6 py-2">Azioni</th>
                </tr>
            </thead>

            <tbody class="bg-white dark:bg-gray-800">
                @forelse($rentals as $r)
                    @php
                        $rowNum = $loop->iteration + ($rentals->currentPage() - 1)*$rentals->perPage();
                        $plate  = $r->vehicle->plate ?? '—';
                        $model  = trim(($r->vehicle->make ?? '') . ' ' . ($r->vehicle->model ?? ''));
                    @endphp
                    <tr class="hover:bg-gray-200 dark:hover:bg-gray-700">
                        <td class="px-6 py-2 whitespace-nowrap">{{ $rowNum }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">#{{ $r->id }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            {{ $plate }}
                            <span class="text-[11px] text-gray-500 dark:text-gray-400 ml-1">{{ $model }}</span>
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap">{{ strtoupper($r->status) }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            {{ optional($r->created_at)->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            <a href="{{ route('rentals.show', $r) }}"
                               class="inline-flex items-center hover:text-indigo-600">
                                <i class="fas fa-eye mr-1"></i> Apri
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            Nessun contratto trovato per questo cliente.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginazione --}}
    <div class="mt-3">
        {{ $rentals->links() }}
    </div>
</div>
