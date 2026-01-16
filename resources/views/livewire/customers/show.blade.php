{{-- resources/views/livewire/customers/show.blade.php --}}

<div class="p-4"
     x-data="{
        tab: (window.location.hash ? window.location.hash.substring(1) : 'dati'),
        setTab(t){
            this.tab = t;
            history.replaceState(null, '', '#' + t);
        }
     }"
     x-init="
        // Se l'hash cambia (es. back/forward), sincronizza la tab
        window.addEventListener('hashchange', () => {
            const t = window.location.hash ? window.location.hash.substring(1) : 'dati';
            tab = (['dati','contratti'].includes(t) ? t : 'dati');
        });
     "
>
    {{-- Header tabs --}}
    <div class="border-b mb-4">
        <nav class="flex gap-6 text-sm" role="tablist" aria-label="Sezioni cliente">
            <button type="button"
                    @click="setTab('dati')"
                    :class="tab === 'dati' ? 'border-b-2 border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300'"
                    class="pb-2"
                    role="tab"
                    :aria-selected="(tab === 'dati').toString()"
                    aria-controls="tab-dati">
                Dati
            </button>

            <button type="button"
                    @click="setTab('contratti')"
                    :class="tab === 'contratti' ? 'border-b-2 border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-300'"
                    class="pb-2"
                    role="tab"
                    :aria-selected="(tab === 'contratti').toString()"
                    aria-controls="tab-contratti">
                Contratti
            </button>
        </nav>
    </div>

    {{-- TAB: Dati (identitari + contatti + residenza + note) --}}
    <section x-show="tab === 'dati'" x-cloak id="tab-dati" role="tabpanel" aria-labelledby="Dati">
        <form wire:submit.prevent="save" class="space-y-6">
            {{-- Sezione: Dati identitari --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Dati identitari</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Nome / Ragione sociale --}}
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Nome / Ragione sociale</label>
                        <input type="text" wire:model.defer="name"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Data di nascita --}}
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Data di nascita</label>
                        <input type="date" wire:model.defer="birthdate"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('birthdate') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Tipo documento d'identità (select limitata a id/passport) --}}
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Tipo documento d'identità</label>
                        <select wire:model.defer="doc_id_type"
                                class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                            {{-- Se a DB c'è un valore non previsto (license/other), lo mostriamo ma non selezionabile --}}
                            @if($doc_id_type && !in_array($doc_id_type, ['id','passport']))
                                <option value="{{ $doc_id_type }}" selected disabled>
                                    Valore attuale: {{ strtoupper($doc_id_type) }}
                                </option>
                            @endif

                            <option value="" @selected($doc_id_type === null) >—</option>
                            @foreach($docIdOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('doc_id_type') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Numero documento d'identità (label aggiornato) --}}
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Numero documento d'identità</label>
                        <input type="text" wire:model.defer="doc_id_number"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('doc_id_number') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    {{-- Codice fiscale --}}
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Codice fiscale</label>
                        <input type="text" wire:model.defer="tax_code"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('tax_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Partita IVA --}}
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Partita IVA</label>
                        <input type="text" wire:model.defer="vat"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('vat') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Numero patente (nuovo) --}}
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Numero patente</label>
                        <input type="text" wire:model.defer="driver_license_number"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('driver_license_number') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Scadenza patente (nuovo) --}}
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Scadenza patente</label>
                        <input type="date" wire:model.defer="driver_license_expires_at"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('driver_license_expires_at') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Sezione: Contatti --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Contatti</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" wire:model.defer="email"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Telefono</label>
                        <input type="text" wire:model.defer="phone"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Sezione: Residenza (unico indirizzo) --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Residenza</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Indirizzo</label>
                        <input type="text" wire:model.defer="address_line"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('address_line') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Città</label>
                        <input type="text" wire:model.defer="city"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('city') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Provincia</label>
                        <input type="text" wire:model.defer="province"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('province') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">CAP</label>
                        <input type="text" wire:model.defer="postal_code"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('postal_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Nazione (ISO-2)</label>
                        <input type="text" wire:model.defer="country_code" maxlength="2"
                            class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                    text-gray-900 dark:text-gray-100 w-full">
                        @error('country_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Sezione: Note --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Note</h3>
                <textarea wire:model.defer="notes" rows="3"
                        class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                text-gray-900 dark:text-gray-100 w-full"></textarea>
                @error('notes') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Azioni --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('customers.index') }}"
                class="inline-flex items-center px-3 py-1.5 rounded-md border text-xs font-semibold
                        uppercase hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i> Indietro
                </a>
                <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                            text-xs font-semibold text-white uppercase hover:bg-indigo-500
                            focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                    <i class="fas fa-save mr-1"></i> Salva modifiche
                </button>
            </div>
        </form>
    </section>

    {{-- TAB: Contratti --}}
    <section x-show="tab === 'contratti'" x-cloak id="tab-contratti" role="tabpanel" aria-labelledby="Contratti">
        <livewire:customers.rentals-table :customer="$customer" />
    </section>
</div>
