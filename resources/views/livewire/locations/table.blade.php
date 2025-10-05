{{-- resources/views/livewire/locations/table.blade.php --}}

<div class="p-4">
    {{-- Barra comandi: coerente con organizations --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            {{-- Ricerca --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Cerca</label>
                <input type="text"
                       wire:model.live.debounce.400ms="search"
                       placeholder="Nome sede / città / CAP"
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
                    <option selected>15</option>
                    <option>25</option>
                    <option>50</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Tabella --}}
    <div class="overflow-x-auto">
        <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-300 dark:bg-gray-700">
                <tr class="uppercase tracking-wider text-left">
                    <th class="px-6 py-2 w-16">#</th>

                    {{-- Nome (sortabile) --}}
                    <th class="px-6 py-2">
                        <button type="button" wire:click="setSort('name')" class="inline-flex items-center gap-1">
                            Sede
                            @if($sort === 'name')
                                <i class="fas fa-sort-alpha-{{ $dir === 'asc' ? 'down' : 'up' }}"></i>
                            @else
                                <i class="fas fa-sort text-gray-500"></i>
                            @endif
                        </button>
                    </th>

                    {{-- Città (sortabile) --}}
                    <th class="px-6 py-2">
                        <button type="button" wire:click="setSort('city')" class="inline-flex items-center gap-1">
                            Città
                            @if($sort === 'city')
                                <i class="fas fa-sort-alpha-{{ $dir === 'asc' ? 'down' : 'up' }}"></i>
                            @else
                                <i class="fas fa-sort text-gray-500"></i>
                            @endif
                        </button>
                    </th>

                    <th class="px-6 py-2">Indirizzo</th>
                    <th class="px-6 py-2">Azioni</th>
                </tr>
            </thead>

            <tbody class="bg-white dark:bg-gray-800">
                @forelse($locations as $loc)
                    @php
                        $rowNum = $loop->iteration + ($locations->currentPage()-1)*$locations->perPage();
                    @endphp
                    <tr class="hover:bg-gray-200 dark:hover:bg-gray-700">
                        <td class="px-6 py-2 whitespace-nowrap">{{ $rowNum }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">{{ $loc->name }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">{{ $loc->city ?: '—' }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            {{ $loc->address_line }},
                            {{ $loc->postal_code }} {{ $loc->city }},
                            {{ $loc->province }} ({{ $loc->country_code }})
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            <a href="{{ route('locations.show', $loc) }}"
                               class="inline-flex items-center hover:text-indigo-600">
                                <i class="fas fa-eye mr-1"></i> Apri
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            Nessuna sede trovata.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginazione --}}
    <div class="mt-4">
        {{ $locations->links() }}
    </div>
</div>
