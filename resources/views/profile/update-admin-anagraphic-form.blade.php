{{-- resources/views/profile/update-admin-anagraphic-form.blade.php --}}

<x-form-section submit="updateAdminAnagraphic">
    <x-slot name="title">
        Dati anagrafici (Admin)
    </x-slot>

    <x-slot name="description">
        Aggiorna i dati anagrafici dell’organizzazione amministratrice.
    </x-slot>

    <x-slot name="form">
        {{-- Ragione sociale --}}
        <div class="col-span-6 sm:col-span-4">
            <x-label for="legal_name" value="Ragione sociale" />
            <x-input id="legal_name" type="text" class="mt-1 block w-full"
                     wire:model="state.legal_name" autocomplete="organization" />
            <x-input-error for="state.legal_name" class="mt-2" />
        </div>

        {{-- Partita IVA --}}
        <div class="col-span-6 sm:col-span-4">
            <x-label for="vat" value="Partita IVA" />
            <x-input id="vat" type="text" class="mt-1 block w-full"
                     wire:model="state.vat" autocomplete="off" />
            <x-input-error for="state.vat" class="mt-2" />
        </div>

        {{-- Email aziendale --}}
        <div class="col-span-6 sm:col-span-4">
            <x-label for="org_email" value="Email aziendale" />
            <x-input id="org_email" type="email" class="mt-1 block w-full"
                     wire:model="state.email" autocomplete="email" />
            <x-input-error for="state.email" class="mt-2" />
        </div>

        {{-- Telefono --}}
        <div class="col-span-6 sm:col-span-4">
            <x-label for="org_phone" value="Telefono" />
            <x-input id="org_phone" type="text" class="mt-1 block w-full"
                     wire:model="state.phone" autocomplete="tel" />
            <x-input-error for="state.phone" class="mt-2" />
        </div>

        {{-- ✅ Luogo (CARGOS) = unico punto di input per città/provincia/nazione --}}
        <div class="col-span-6 pt-2 border-t border-gray-200 dark:border-gray-700">
            <livewire:shared.cargos-luogo-picker
                wire:key="admin-org-police-place-code"
                wire:model="state.police_place_code"
                title="Luogo (CARGOS)"
                hint="Italia: seleziona provincia e comune. Estero: seleziona la nazione."
                mode="full"
            />
            <x-input-error for="state.police_place_code" class="mt-2" />
        </div>

        {{-- Indirizzo --}}
        <div class="col-span-6">
            <x-label for="address_line" value="Indirizzo" />
            <x-input id="address_line" type="text" class="mt-1 block w-full"
                     wire:model="state.address_line" autocomplete="street-address" />
            <x-input-error for="state.address_line" class="mt-2" />
        </div>

        {{-- CAP --}}
        <div class="col-span-6 sm:col-span-2">
            <x-label for="postal_code" value="CAP" />
            <x-input id="postal_code" type="text" class="mt-1 block w-full"
                     wire:model="state.postal_code" autocomplete="postal-code" />
            <x-input-error for="state.postal_code" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            Salvato.
        </x-action-message>

        <x-button wire:loading.attr="disabled">
            Salva
        </x-button>
    </x-slot>
</x-form-section>
