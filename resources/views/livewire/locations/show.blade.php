{{-- resources/views/livewire/locations/show.blade.php --}}

<div class="p-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Card info sede --}}
    <div class="border rounded-md p-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Informazioni sede</h3>
        <div class="text-sm text-gray-800 dark:text-gray-100 space-y-1">
            <div><span class="font-medium">Nome:</span> {{ $location->name }}</div>
            <div class="text-gray-600 dark:text-gray-300">
                {{ $location->address_line }},
                {{ $location->postal_code }} {{ $location->city }},
                {{ $location->province }} ({{ $location->country_code }})
            </div>
            @if($location->notes)
                <div class="text-gray-600 dark:text-gray-300">
                    <span class="font-medium">Note:</span> {{ $location->notes }}
                </div>
            @endif
        </div>
    </div>

    {{-- Mini-card: Statistiche sede --}}
    <div class="border rounded-md p-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Statistiche sede</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 text-sm">
            <div class="border rounded p-2">
                <div class="text-[11px] text-gray-500 dark:text-gray-400">Veicoli assegnati</div>
                <div class="text-lg font-semibold">{{ $stats['vehicles_count'] ?? 0 }}</div>
            </div>

            <div class="border rounded p-2">
                <div class="text-[11px] text-gray-500 dark:text-gray-400">Noleggi attivi (pickup qui)</div>
                <div class="text-lg font-semibold">{{ $stats['active_pickup_here'] ?? 0 }}</div>
            </div>

            <div class="border rounded p-2">
                <div class="text-[11px] text-gray-500 dark:text-gray-400">Noleggi attivi (return qui)</div>
                <div class="text-lg font-semibold">{{ $stats['active_return_here'] ?? 0 }}</div>
            </div>

            <div class="border rounded p-2">
                <div class="text-[11px] text-gray-500 dark:text-gray-400">Ritiri previsti oggi</div>
                <div class="text-lg font-semibold">{{ $stats['planned_pickups_today'] ?? 0 }}</div>
            </div>

            <div class="border rounded p-2">
                <div class="text-[11px] text-gray-500 dark:text-gray-400">Rientri previsti oggi</div>
                <div class="text-lg font-semibold">{{ $stats['planned_returns_today'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    {{-- Card assegnazione veicoli (ricerca + bulk) --}}
    <div class="border rounded-md p-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Assegna veicoli a questa sede</h3>

        {{-- Ricerca veicoli --}}
        <div class="mb-3">
            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Cerca</label>
            <input type="text" wire:model.live.debounce.400ms="vehicleSearch"
                   placeholder="Targa / marca / modello"
                   class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                          text-gray-900 dark:text-gray-100 w-full">
        </div>

        {{-- Lista veicoli assegnabili --}}
        <div class="space-y-2 max-h-[360px] overflow-auto">
            @forelse($assignable as $v)
                <label class="flex items-center justify-between border rounded p-2 cursor-pointer">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" value="{{ $v->id }}" wire:model="selectedVehicleIds">
                        <div>
                            <div class="font-medium text-sm">{{ $v->plate }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-300">{{ $v->make }} {{ $v->model }} — {{ $v->segment }}</div>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500">
                        @if($v->default_pickup_location_id)
                            attuale: #{{ $v->default_pickup_location_id }}
                        @else
                            nessuna sede base
                        @endif
                    </div>
                </label>
            @empty
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    Nessun veicolo assegnabile (forse tutti in noleggio attivo o non assegnati al tuo renter).
                </p>
            @endforelse
        </div>

        {{-- Azione bulk --}}
        <div class="pt-3 flex justify-end">
            <button wire:click="assignSelected"
                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                           text-xs font-semibold text-white uppercase hover:bg-indigo-500
                           focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                <i class="fas fa-random mr-1"></i> Assegna selezionati
            </button>
        </div>
    </div>

    {{-- Card veicoli già in sede --}}
    <div class="border rounded-md p-4 lg:col-span-3">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Veicoli con sede base qui</h3>
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-2">
            @forelse($assigned as $v)
                <div class="border rounded p-2">
                    <div class="font-medium text-sm">{{ $v->plate }}</div>
                    <div class="text-xs text-gray-600 dark:text-gray-300">{{ $v->make }} {{ $v->model }} — {{ $v->segment }}</div>
                    <div class="text-[11px] text-gray-500 mt-1">Sede base attuale</div>
                </div>
            @empty
                <p class="text-gray-500 dark:text-gray-400 text-sm">Nessun veicolo assegnato a questa sede.</p>
            @endforelse
        </div>
    </div>
</div>
