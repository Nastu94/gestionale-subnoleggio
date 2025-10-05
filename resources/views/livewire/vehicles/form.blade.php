{{-- Livewire: Vehicles\Form (Create/Edit) — campi amministrativi rimossi dalla UI --}}
<div class="space-y-4">

    {{-- Header interno --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold">
                {{ $isEdit ? 'Modifica veicolo' : 'Nuovo veicolo' }}
            </h1>
            <p class="text-sm text-gray-500">
                I campi organizzazione/sede/attivo sono impostati automaticamente.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('vehicles.index') }}"
               class="rounded border px-3 py-2 text-gray-700 hover:bg-gray-50">
                Annulla
            </a>
            <button type="button"
                    class="rounded bg-slate-800 px-4 py-2 font-medium text-white hover:bg-slate-900 disabled:opacity-50"
                    wire:click="save"
                    wire:loading.attr="disabled">
                Salva
            </button>
        </div>
    </div>

    {{-- Card form --}}
    <div class="rounded-lg border bg-white p-4">
        <div class="grid grid-cols-12 gap-4">

            {{-- plate --}}
            <div class="col-span-3">
                <label class="block text-xs text-gray-500">Targa *</label>
                <input type="text" class="mt-1 w-full rounded border-gray-300 uppercase"
                       wire:model.defer="form.plate" maxlength="16" placeholder="ABC123DE" autofocus>
                @error('form.plate') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- make --}}
            <div class="col-span-3">
                <label class="block text-xs text-gray-500">Marca *</label>
                <input type="text" class="mt-1 w-full rounded border-gray-300"
                       wire:model.defer="form.make" maxlength="64" placeholder="Fiat">
                @error('form.make') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- model --}}
            <div class="col-span-3">
                <label class="block text-xs text-gray-500">Modello *</label>
                <input type="text" class="mt-1 w-full rounded border-gray-300"
                       wire:model.defer="form.model" maxlength="64" placeholder="Panda">
                @error('form.model') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- year --}}
            <div class="col-span-3">
                <label class="block text-xs text-gray-500">Anno</label>
                <input type="number" class="mt-1 w-full rounded border-gray-300"
                       wire:model.defer="form.year" min="1900" max="2100" step="1" placeholder="2024">
                @error('form.year') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- vin --}}
            <div class="col-span-4">
                <label class="block text-xs text-gray-500">VIN</label>
                <input type="text" class="mt-1 w-full rounded border-gray-300"
                       wire:model.defer="form.vin" maxlength="17" placeholder="Numero telaio (17 caratteri)">
                @error('form.vin') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- color --}}
            <div class="col-span-4">
                <label class="block text-xs text-gray-500">Colore</label>
                <input type="text" class="mt-1 w-full rounded border-gray-300"
                       wire:model.defer="form.color" maxlength="32" placeholder="Nero">
                @error('form.color') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- fuel_type (enum) --}}
            <div class="col-span-4">
                <label class="block text-xs text-gray-500">Alimentazione *</label>
                <select class="mt-1 w-full rounded border-gray-300"
                        wire:model.defer="form.fuel_type">
                    @foreach($fuelOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.fuel_type') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- transmission (enum) --}}
            <div class="col-span-4">
                <label class="block text-xs text-gray-500">Cambio *</label>
                <select class="mt-1 w-full rounded border-gray-300"
                        wire:model.defer="form.transmission">
                    @foreach($transOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('form.transmission') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- seats --}}
            <div class="col-span-4">
                <label class="block text-xs text-gray-500">Posti</label>
                <input type="number" class="mt-1 w-full rounded border-gray-300"
                       wire:model.defer="form.seats" min="1" max="99" step="1" placeholder="5">
                @error('form.seats') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- segment --}}
            <div class="col-span-4">
                <label class="block text-xs text-gray-500">Segmento</label>
                <input type="text" class="mt-1 w-full rounded border-gray-300"
                       wire:model.defer="form.segment" maxlength="32" placeholder="SUV / Compact / ...">
                @error('form.segment') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- mileage_current --}}
            <div class="col-span-4">
                <label class="block text-xs text-gray-500">Chilometraggio attuale *</label>
                <input type="number" class="mt-1 w-full rounded border-gray-300"
                       wire:model.defer="form.mileage_current" min="0" step="1" placeholder="0">
                @error('form.mileage_current') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- notes --}}
            <div class="col-span-12">
                <label class="block text-xs text-gray-500">Note</label>
                <textarea class="mt-1 w-full rounded border-gray-300" rows="4"
                          wire:model.defer="form.notes" placeholder="Annotazioni utili..."></textarea>
                @error('form.notes') <div class="mt-1 text-xs text-rose-600">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    {{-- Footer fisso --}}
    <div class="sticky bottom-0 -mx-4 border-t bg-white/75 p-3 backdrop-blur supports-[backdrop-filter]:bg-white/60">
        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('vehicles.index') }}" class="rounded border px-3 py-2 text-gray-700 hover:bg-gray-50">Annulla</a>
            <button type="button" class="rounded bg-slate-800 px-4 py-2 font-medium text-white hover:bg-slate-900 disabled:opacity-50"
                    wire:click="save" wire:loading.attr="disabled">
                Salva
            </button>
        </div>
    </div>
</div>
