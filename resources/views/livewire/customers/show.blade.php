{{-- resources/views/livewire/customers/show.blade.php --}}

<div class="p-4"
     x-data="{
        tab: (window.location.hash ? window.location.hash.substring(1) : 'dati'),
        setTab(t){
            this.tab = t;
            history.replaceState(null, '', '#' + t);
        }
     }"
>
    {{-- =========================
        HEADER TABS
    ========================== --}}
    <div class="border-b mb-6">
        <nav class="flex gap-6 text-sm">
            <button type="button"
                @click="setTab('dati')"
                :class="tab === 'dati'
                    ? 'border-b-2 border-indigo-600 text-indigo-700 dark:text-indigo-300'
                    : 'text-gray-500 dark:text-gray-300'"
                class="pb-2 font-medium">
                Dati cliente
            </button>

            <button type="button"
                @click="setTab('contratti')"
                :class="tab === 'contratti'
                    ? 'border-b-2 border-indigo-600 text-indigo-700 dark:text-indigo-300'
                    : 'text-gray-500 dark:text-gray-300'"
                class="pb-2 font-medium">
                Contratti
            </button>
        </nav>
    </div>

    {{-- =========================
        TAB DATI
    ========================== --}}
    <section x-show="tab === 'dati'" x-cloak>
        <form wire:submit.prevent="save" class="space-y-10">

            {{-- ======================================================
                1. DATI ANAGRAFICI
            ======================================================= --}}
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <header class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Dati anagrafici
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Informazioni personali del cliente
                    </p>
                </header>

                <div class="p-6 space-y-6">
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-300">Nome</label>
                            <input wire:model.defer="first_name"
                                   class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                        </div>

                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-300">Cognome</label>
                            <input wire:model.defer="last_name"
                                   class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                        </div>

                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-300">Data di nascita</label>
                            <input type="date" wire:model.defer="birthdate"
                                   class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                        </div>
                    </div>

                    <livewire:shared.cargos-luogo-picker
                        wire:model="birth_place_code"
                        title="Luogo di nascita"
                        hint="Comune italiano o nazione estera"
                    />

                    <livewire:shared.cargos-luogo-picker
                        wire:model="citizenship_place_code"
                        title="Cittadinanza"
                        hint="Seleziona solo la nazione"
                        mode="country-only"
                    />
                </div>
            </section>

            {{-- ======================================================
                2. DOCUMENTI
            ======================================================= --}}
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <header class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-sm font-semibold">Documenti</h3>
                    <p class="text-xs text-gray-500">Documento di identità e patente</p>
                </header>

                <div class="p-6 space-y-8">
                    {{-- Documento identità --}}
                    <div class="space-y-4">
                        <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Documento di identità
                        </h4>

                        <livewire:shared.cargos-document-type-picker
                            wire:model="identity_document_type_code"
                            title="Tipo documento (CARGOS)"
                        />

                        <div>
                            <label class="text-xs">Numero documento</label>
                            <input wire:model.defer="doc_id_number"
                                   class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                        </div>

                        <livewire:shared.cargos-luogo-picker
                            wire:model="identity_document_place_code"
                            title="Luogo di rilascio"
                        />
                    </div>

                    {{-- Patente --}}
                    <div class="space-y-4">
                        <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                            Patente di guida
                        </h4>

                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-xs">Numero patente</label>
                                <input wire:model.defer="driver_license_number"
                                       class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                            </div>

                            <div>
                                <label class="text-xs">Scadenza</label>
                                <input type="date" wire:model.defer="driver_license_expires_at"
                                       class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                            </div>
                        </div>

                        <livewire:shared.cargos-luogo-picker
                            wire:model="driver_license_place_code"
                            title="Luogo di rilascio patente"
                        />
                    </div>
                </div>
            </section>

            {{-- ======================================================
                3. CONTATTI
            ======================================================= --}}
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <header class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-sm font-semibold">Contatti</h3>
                </header>

                <div class="p-6 grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-xs">Email</label>
                        <input wire:model.defer="email"
                               class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                    </div>

                    <div>
                        <label class="text-xs">Telefono</label>
                        <input wire:model.defer="phone"
                               class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                    </div>
                </div>
            </section>

            {{-- ======================================================
                4. INDIRIZZI
            ======================================================= --}}
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <header class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-sm font-semibold">Indirizzi</h3>
                </header>

                <div class="p-6 space-y-6">
                    <livewire:shared.cargos-luogo-picker
                        wire:model="police_place_code"
                        title="Residenza"
                    />

                    <div>
                        <label class="text-xs">Indirizzo</label>
                        <input wire:model.defer="address_line"
                               class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                    </div>

                    <div>
                        <label class="text-xs">CAP</label>
                        <input wire:model.defer="postal_code"
                               class="mt-1 w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm">
                    </div>
                </div>
            </section>

            {{-- ======================================================
                5. NOTE
            ======================================================= --}}
            <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <header class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-sm font-semibold">Note</h3>
                </header>

                <div class="p-6">
                    <textarea wire:model.defer="notes" rows="3"
                              class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm"></textarea>
                </div>
            </section>

            {{-- AZIONI --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('customers.index') }}" class="btn-secondary">Indietro</a>
                <button type="submit" class="btn-primary">Salva</button>
            </div>
        </form>
    </section>

    {{-- =========================
        TAB CONTRATTI
    ========================== --}}
    <section x-show="tab === 'contratti'" x-cloak>
        <livewire:customers.rentals-table :customer="$customer" />
    </section>
</div>
