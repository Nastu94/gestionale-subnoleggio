{{-- resources/views/livewire/locations/create.blade.php --}}

<div class="p-4">
    <form wire:submit.prevent="save" class="space-y-6">
        <div>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Dati sede</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-600 dark:text-gray-300 mb-1">Nome</label>
                    <input type="text" wire:model.defer="name"
                           class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                                  text-gray-900 dark:text-gray-100 w-full">
                    @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

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

        <div>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Note</h3>
            <textarea wire:model.defer="notes" rows="3"
                      class="px-3 py-2 rounded-md border bg-gray-50 dark:bg-gray-700 text-sm
                             text-gray-900 dark:text-gray-100 w-full"></textarea>
            @error('notes') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('locations.index') }}"
               class="inline-flex items-center px-3 py-1.5 rounded-md border text-xs font-semibold
                      uppercase hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-1"></i> Annulla
            </a>
            <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                           text-xs font-semibold text-white uppercase hover:bg-indigo-500
                           focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                <i class="fas fa-save mr-1"></i> Crea sede
            </button>
        </div>
    </form>
</div>
