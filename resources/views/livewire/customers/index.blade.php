{{-- resources/views/livewire/customers/index.blade.php --}}
<div class="space-y-4">
    {{-- Barra ricerca --}}
    <div class="flex items-center gap-2">
        <input type="text" wire:model.debounce.400ms="q" class="form-input w-72"
               placeholder="Cerca nome, email, telefono, documento...">
    </div>

    {{-- Tabella --}}
    <div class="overflow-x-auto">
        <table class="table-auto w-full text-sm">
            <thead>
            <tr class="text-left border-b">
                <th class="py-2 pr-2">Nome</th>
                <th class="py-2 pr-2">Doc.</th>
                <th class="py-2 pr-2">Email</th>
                <th class="py-2 pr-2">Telefono</th>
                <th class="py-2 pr-2">Azioni</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $c)
                <tr class="border-b">
                    <td class="py-2 pr-2">{{ $c->name }}</td>
                    <td class="py-2 pr-2">{{ $c->doc_id_type }} {{ $c->doc_id_number }}</td>
                    <td class="py-2 pr-2">{{ $c->email }}</td>
                    <td class="py-2 pr-2">{{ $c->phone }}</td>
                    <td class="py-2 pr-2">
                        <a href="{{ route('customers.show', $c) }}" class="text-blue-600 hover:underline">Apri scheda</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-gray-500">Nessun cliente trovato</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginazione --}}
    <div>{{ $items->links() }}</div>
</div>
