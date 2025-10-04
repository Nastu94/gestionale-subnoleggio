{{-- resources/views/livewire/locations/index.blade.php --}}
<div class="space-y-4">
    <div>
        <input type="text" wire:model.debounce.400ms="q" class="form-input w-72"
               placeholder="Cerca sede, città, CAP...">
    </div>

    <div class="overflow-x-auto">
        <table class="table-auto w-full text-sm">
            <thead>
            <tr class="text-left border-b">
                <th class="py-2 pr-2">Nome</th>
                <th class="py-2 pr-2">Indirizzo</th>
                <th class="py-2 pr-2">Azioni</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $loc)
                <tr class="border-b">
                    <td class="py-2 pr-2">{{ $loc->name }}</td>
                    <td class="py-2 pr-2">
                        {{ $loc->address_line }},
                        {{ $loc->postal_code }} {{ $loc->city }},
                        {{ $loc->province }} ({{ $loc->country_code }})
                    </td>
                    <td class="py-2 pr-2">
                        <a href="{{ route('locations.show', $loc) }}" class="text-blue-600 hover:underline">Apri</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-6 text-center text-gray-500">Nessuna sede</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $items->links() }}</div>
</div>
