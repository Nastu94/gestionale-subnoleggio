{{-- resources/views/livewire/customers/show.blade.php --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Form dati identitari + contatti + indirizzo base --}}
    <form wire:submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium">Nome/Ragione sociale</label>
                <input type="text" wire:model.defer="name" class="form-input w-full">
                @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Data di nascita</label>
                <input type="date" wire:model.defer="birthdate" class="form-input w-full">
                @error('birthdate') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium">Tipo documento</label>
                <select wire:model.defer="doc_id_type" class="form-select w-full">
                    <option value="">—</option>
                    <option value="id">Carta identità</option>
                    <option value="passport">Passaporto</option>
                    <option value="license">Patente</option>
                    <option value="other">Altro</option>
                </select>
                @error('doc_id_type') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium">Numero documento</label>
                <input type="text" wire:model.defer="doc_id_number" class="form-input w-full">
                @error('doc_id_number') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium">Email</label>
                <input type="email" wire:model.defer="email" class="form-input w-full">
                @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Telefono</label>
                <input type="text" wire:model.defer="phone" class="form-input w-full">
                @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium">Indirizzo</label>
                <input type="text" wire:model.defer="address_line" class="form-input w-full">
                @error('address_line') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Città</label>
                <input type="text" wire:model.defer="city" class="form-input w-full">
                @error('city') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium">Provincia</label>
                <input type="text" wire:model.defer="province" class="form-input w-full">
                @error('province') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">CAP</label>
                <input type="text" wire:model.defer="postal_code" class="form-input w-full">
                @error('postal_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Nazione (ISO-2)</label>
                <input type="text" wire:model.defer="country_code" maxlength="2" class="form-input w-full">
                @error('country_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium">Note</label>
            <textarea wire:model.defer="notes" class="form-textarea w-full" rows="3"></textarea>
            @error('notes') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2">
            <button type="submit" class="btn btn-primary">Salva modifiche</button>
        </div>
    </form>

    {{-- Placeholder tabs: Indirizzi / Documenti / Contratti / Note (espandibili in seguito) --}}
    <div class="border rounded p-4">
        <p class="text-gray-600 text-sm">Altre schede (Indirizzi multipli, Documenti, Contratti) verranno integrate qui.</p>
    </div>
</div>
