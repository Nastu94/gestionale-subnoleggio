{{-- resources/views/livewire/locations/show.blade.php --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Parco veicoli assegnati a questa sede --}}
    <div class="border rounded p-4">
        <h2 class="font-medium mb-3">Veicoli su questa sede</h2>
        <div class="space-y-2 max-h-[460px] overflow-auto">
            @forelse($assigned as $v)
                <div class="flex items-center justify-between border rounded p-2">
                    <div>
                        <div class="font-medium">{{ $v->plate }}</div>
                        <div class="text-xs text-gray-600">{{ $v->make }} {{ $v->model }} – {{ $v->segment }}</div>
                    </div>
                    <span class="text-xs text-gray-500">Sede base attuale</span>
                </div>
            @empty
                <p class="text-gray-500 text-sm">Nessun veicolo assegnato a questa sede.</p>
            @endforelse
        </div>
    </div>

    {{-- Assegna veicoli a questa sede (cambio istantaneo) --}}
    <div class="border rounded p-4">
        <h2 class="font-medium mb-3">Assegna veicoli a questa sede</h2>

        <div class="mb-3">
            <input type="text" wire:model.debounce.400ms="vehicleSearch" class="form-input w-80"
                   placeholder="Cerca targa, marca, modello...">
        </div>

        <div class="space-y-2 max-h-[380px] overflow-auto">
            @forelse($assignable as $v)
                <label class="flex items-center justify-between border rounded p-2 cursor-pointer">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" value="{{ $v->id }}" wire:model="selectedVehicleIds">
                        <div>
                            <div class="font-medium">{{ $v->plate }}</div>
                            <div class="text-xs text-gray-600">{{ $v->make }} {{ $v->model }} – {{ $v->segment }}</div>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500">
                        {{-- Tooltip eventuale: "no rental attivo" già filtrato a monte --}}
                        @if($v->default_pickup_location_id) attualmente: #{{ $v->default_pickup_location_id }} @endif
                    </div>
                </label>
            @empty
                <p class="text-gray-500 text-sm">Nessun veicolo assegnabile (forse tutti in noleggio attivo o non assegnati al tuo renter).</p>
            @endforelse
        </div>

        <div class="pt-3">
            <button wire:click="assignSelected" class="btn btn-primary">Assegna selezionati</button>
        </div>
    </div>
</div>
