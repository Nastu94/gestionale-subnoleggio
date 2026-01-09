<div>
    @if($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            {{-- Overlay --}}
            <div class="absolute inset-0 bg-black opacity-75" wire:click="closeModal"></div>

            {{-- Box --}}
            <div class="relative z-10 w-full max-w-3xl bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden max-h-[90vh] flex flex-col">
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Anagrafica renter
                        </h3>
                        <p class="text-xs text-gray-600 dark:text-gray-300">
                            Dati anagrafici e licenza di noleggio
                        </p>
                    </div>

                    <button type="button"
                            wire:click="closeModal"
                            class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- Body --}}
                <form wire:submit.prevent="save" class="px-6 py-4 overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Ragione sociale --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Ragione sociale</label>
                            <input type="text"
                                   wire:model.defer="state.legal_name"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.legal_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- P.IVA --}}
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Partita IVA</label>
                            <input type="text"
                                   wire:model.defer="state.vat"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.vat') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Email aziendale --}}
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Email</label>
                            <input type="email"
                                   wire:model.defer="state.email"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Telefono --}}
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Telefono</label>
                            <input type="text"
                                   wire:model.defer="state.phone"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Indirizzo --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Indirizzo</label>
                            <input type="text"
                                   wire:model.defer="state.address_line"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.address_line') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Città --}}
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Città</label>
                            <input type="text"
                                   wire:model.defer="state.city"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.city') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Provincia --}}
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Provincia</label>
                            <input type="text"
                                   wire:model.defer="state.province"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.province') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- CAP --}}
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">CAP</label>
                            <input type="text"
                                   wire:model.defer="state.postal_code"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.postal_code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Paese --}}
                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Paese (codice)</label>
                            <input type="text"
                                   wire:model.defer="state.country_code"
                                   placeholder="IT"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.country_code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- LICENZA --}}
                        <div class="sm:col-span-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" wire:model.defer="state.rental_license" id="rental_license">
                                <label for="rental_license" class="text-sm text-gray-900 dark:text-gray-100">
                                    Licenza di noleggio presente
                                </label>
                            </div>
                            @error('state.rental_license') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Numero licenza</label>
                            <input type="text"
                                   wire:model.defer="state.rental_license_number"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.rental_license_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Scadenza licenza</label>
                            <input type="date"
                                   wire:model.defer="state.rental_license_expires_at"
                                   class="w-full px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                            @error('state.rental_license_expires_at') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="mt-6 flex items-center justify-end gap-2">
                        <button type="button"
                                wire:click="closeModal"
                                class="px-3 py-2 rounded-md border text-xs font-semibold uppercase
                                       text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            Annulla
                        </button>

                        <button type="submit"
                                class="px-3 py-2 rounded-md bg-indigo-600 text-white text-xs font-semibold uppercase
                                       hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                            Salva
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
