{{-- resources/views/livewire/customers/table.blade.php --}}

<div class="p-4">
    {{-- Barra comandi: identica per look&feel alla tua --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            {{-- Ricerca --}}
            <div>
                <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Cerca</label>
                <input type="text"
                       wire:model.live.debounce.400ms="search"
                       placeholder="Nome / email / telefono / documento"
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

                    {{-- Cliente: sortabile --}}
                    <th class="px-6 py-2">
                        <button type="button" wire:click="setSort('name')" class="inline-flex items-center gap-1">
                            Cliente
                            @if($sort === 'name')
                                <i class="fas fa-sort-alpha-{{ $dir === 'asc' ? 'down' : 'up' }}"></i>
                            @else
                                <i class="fas fa-sort text-gray-500"></i>
                            @endif
                        </button>
                    </th>

                    <th class="px-6 py-2">Documento</th>
                    <th class="px-6 py-2">Email</th>
                    <th class="px-6 py-2">Telefono</th>
                    <th class="px-6 py-2">Azioni</th>
                </tr>
            </thead>

            <tbody class="bg-white dark:bg-gray-800">
                @forelse($customers as $c)
                    @php
                        $rowNum = $loop->iteration + ($customers->currentPage()-1)*$customers->perPage();
                    @endphp
                    <tr class="hover:bg-gray-200 dark:hover:bg-gray-700">
                        <td class="px-6 py-2 whitespace-nowrap">{{ $rowNum }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">{{ $c->name }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            {{ $c->doc_id_type ? strtoupper($c->doc_id_type) : '—' }}
                            {{ $c->doc_id_number ?: '' }}
                        </td>
                        <td class="px-6 py-2 whitespace-nowrap">{{ $c->email ?: '—' }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">{{ $c->phone ?: '—' }}</td>
                        <td class="px-6 py-2 whitespace-nowrap">
                            <a href="{{ route('customers.show', $c) }}"
                               class="inline-flex items-center hover:text-indigo-600">
                                <i class="fas fa-eye mr-1"></i> Apri scheda
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            Nessun cliente trovato.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginazione --}}
    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</div>
