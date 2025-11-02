{{-- resources/views/livewire/rentals/checklist-form.blade.php --}}
<div x-data="checklistTabs()" 
    x-init="bootstrapFromServer({ hasId: @js((bool) $checklistId), locked: @js((bool) $isLocked) })" 
    x-cloak class="w-full">

    {{-- ========== Barra Tab ==========
         4 tab: Base, Checklist, Danni, Media/Documenti
         Al momento abilitiamo solo "Base" (step 4A).
    --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
        <nav class="-mb-px flex gap-6" aria-label="Tabs">
            {{-- Base sempre cliccabile --}}
            <button type="button" @click="go('base')" :class="tabClass('base')">
                {{ __('Base') }}
            </button>

            {{-- Disabilita SOLO se non esiste ancora l’ID (no lock) --}}
            <button type="button" @click="go('checklist')" :disabled="!state.hasChecklistId" :class="tabClass('checklist')">
                {{ __('Checklist') }}
            </button>

            <button type="button" @click="go('damages')" :disabled="!state.hasChecklistId" :class="tabClass('damages')">
                {{ __('Danni') }}
            </button>

            <button type="button" @click="go('media')" :disabled="!state.hasChecklistId" :class="tabClass('media')">
                {{ __('Media & Documenti') }}
            </button>
        </nav>
    </div>

    {{-- ========== TAB 1: Base ========== --}}
    <section x-show="state.active === 'base'" class="max-w-full">
        <form wire:submit.prevent="saveBase" class="space-y-6">
            {{-- Tipo checklist (sola lettura, impostato da query) --}}
            <div>
                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Tipo checklist') }}
                </span>
                <span class="inline-flex items-center mt-1 rounded-md px-2 py-0.5 text-xs font-semibold
                             bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100">
                    {{ $type }}
                </span>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Chilometraggio (>= km attuali veicolo) --}}
            <div>
                <label for="mileage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Chilometraggio') }}
                </label>
                <input type="number" id="mileage" wire:model.lazy="mileage"
                       min="0" step="1"
                       :disabled="state.isLocked"
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Km attuali veicolo:') }}
                    <strong>
                        {{ $current_vehicle_mileage !== null ? number_format($current_vehicle_mileage, 0, ',', '.') : '—' }} km
                    </strong>
                </div>
                @error('mileage') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Carburante (slider 0..100) --}}
            <div>
                <label for="fuel_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Carburante (%)') }}
                </label>
                <input type="range" id="fuel_percent" wire:model.live="fuel_percent"
                        :disabled="state.isLocked"
                        min="0" max="100" step="1" class="mt-2 w-full" />
                <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    {{ __('Livello:') }} <strong>{{ $fuel_percent ?? 0 }}%</strong>
                </div>
                @error('fuel_percent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Pulizia --}}
            <div>
                <label for="cleanliness" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Pulizia') }}
                </label>
                <select id="cleanliness" wire:model="cleanliness"
                        :disabled="state.isLocked"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">{{ __('Seleziona...') }}</option>
                    <option value="poor">Scarsa</option>
                    <option value="fair">Discreta</option>
                    <option value="good">Buona</option>
                    <option value="excellent">Eccellente</option>
                </select>
                @error('cleanliness') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Firme (flag informativi) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" wire:model="signed_by_customer"
                           :disabled="state.isLocked"
                           class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Firmata dal cliente') }}</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="checkbox" wire:model="signed_by_operator"
                           :disabled="state.isLocked"
                           class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Firmata dall’operatore') }}</span>
                </label>
            </div>

            {{-- Submit Tab 1: salva base e crea/aggiorna la RentalChecklist.
                 Effetto atteso (step 4B): ritorna $checklistId e abilita gli altri tab.
            --}}
            <div class="pt-2">
                <button type="submit"
                        :disabled="state.isLocked"
                        class="btn btn-primary shadow-none px-4
                               !bg-primary !text-primary-content !border-primary
                               hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('Salva dati base') }}
                </button>
            </div>
        </form>
    </section>

    {{-- ========== TAB 2: Checklist ========== --}}
    <section x-show="state.active === 'checklist'" class="max-w-full">
        <form wire:submit.prevent="saveChecklist" class="space-y-8">

            {{-- DOCUMENTI --}}
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">
                    {{ __('Documenti') }}
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.documents.id_card"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Carta d’identità acquisita') }}</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.documents.driver_license"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Patente acquisita') }}</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.documents.contract_copy"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Copia contratto consegnata') }}</span>
                    </label>
                </div>
                @error('checklist.documents.id_card') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                @error('checklist.documents.driver_license') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                @error('checklist.documents.contract_copy') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- DOTAZIONI / SICUREZZA --}}
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">
                    {{ __('Dotazioni / Sicurezza') }}
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.equipment.spare_wheel"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Ruotino/ruota di scorta presente') }}</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.equipment.jack"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Cric presente') }}</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.equipment.triangle"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Triangolo presente') }}</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.equipment.vest"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Gilet alta visibilità presente') }}</span>
                    </label>
                </div>
                @error('checklist.equipment.*') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- CONDIZIONI VEICOLO --}}
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">
                    {{ __('Condizioni veicolo') }}
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.vehicle.lights_ok"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Luci funzionanti') }}</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.vehicle.horn_ok"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Clacson funzionante') }}</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.vehicle.brakes_ok"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Freni in ordine') }}</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.vehicle.tires_ok"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Pneumatici in ordine') }}</span>
                    </label>

                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="checklist.vehicle.windshield_ok"
                            :disabled="state.isLocked"
                            class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ __('Parabrezza integro') }}</span>
                    </label>
                </div>
                @error('checklist.vehicle.*') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- NOTE --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('Note') }}
                </label>
                <textarea rows="3" wire:model.lazy="checklist.notes"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800"
                        :disabled="state.isLocked"
                        placeholder="{{ __('Annotazioni aggiuntive…') }}"></textarea>
                @error('checklist.notes') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Submit --}}
            <div class="pt-2">
                <button type="submit"
                        :disabled="state.isLocked"
                        class="btn btn-primary shadow-none px-4
                            !bg-primary !text-primary-content !border-primary
                            hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                            disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('Salva checklist') }}
                </button>
            </div>
        </form>
    </section>

    {{-- ========== TAB 3: Danni ========== --}}
    <section x-show="state.active === 'damages'" class="max-w-full" x-data="{ damagesReadonly: @js($damagesReadonly) }">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                {{ __('Danni') }}
                <template x-if="state.isLocked">
                    <span class="ml-2 inline-flex items-center rounded bg-amber-100 text-amber-800 px-2 py-0.5 text-xs">
                        {{ __('BLOCCATA') }}
                    </span>
                </template>
                <template x-if="damagesReadonly">
                    <span class="ml-2 inline-flex items-center rounded bg-slate-100 text-slate-800 px-2 py-0.5 text-xs">
                        {{ __('SOLO LETTURA (pickup)') }}
                    </span>
                </template>
            </h3>

            {{-- Aggiungi danno disabilitato (sempre) --}}
            <button type="button"
                    :disabled="state.isLocked || !state.hasChecklistId"
                    x-show="!damagesReadonly"
                    class="btn btn-outline shadow-none px-3
                        border-gray-300 text-gray-400 cursor-not-allowed">
                + {{ __('Aggiungi danno') }}
            </button>
        </div>

        {{-- Lista blocchi danno --}}
        <div class="grid grid-cols-2 gap-4">
            @forelse ($damages as $i => $row)
                <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3" wire:key="damage-row-{{ $i }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        {{-- Area --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('Area') }}
                            </label>
                            <select wire:model.lazy="damages.{{ $i }}.area"
                                    :disabled="state.isLocked || damagesReadonly"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                <option value="">{{ __('—') }}</option>
                                <option value="front">Anteriore</option>
                                <option value="rear">Posteriore</option>
                                <option value="left">Sinistra</option>
                                <option value="right">Destra</option>
                                <option value="interior">Interno</option>
                                <option value="roof">Tetto</option>
                                <option value="windshield">Parabrezza</option>
                                <option value="wheel">Ruota</option>
                                <option value="other">Altro</option>
                            </select>
                            @error("damages.$i.area") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Gravità (low|medium|high) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('Gravità') }}
                            </label>
                            <select wire:model.lazy="damages.{{ $i }}.severity"
                                    :disabled="state.isLocked || damagesReadonly"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                <option value="">{{ __('—') }}</option>
                                <option value="low">Bassa</option>
                                <option value="medium">Media</option>
                                <option value="high">Alta</option>
                            </select>
                            @error("damages.$i.severity") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Source (solo display, read-only) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('Origine') }}
                            </label>
                            <input type="text" value="{{ $row['source'] ?? 'rental' }}" readonly
                                class="mt-1 block w-full rounded-md border-gray-200 dark:border-gray-700 dark:bg-gray-900/40 text-sm text-gray-600 dark:text-gray-300" />
                        </div>

                        {{-- Azioni riga: "Rimuovi" disabilitato --}}
                        <div class="flex items-end justify-end">
                            <button type="button"
                                    disabled
                                    class="btn btn-outline px-3 py-1.5 rounded-md border border-gray-300 text-gray-400 cursor-not-allowed">
                                {{ __('Rimuovi') }}
                            </button>
                        </div>
                    </div>

                    {{-- Descrizione --}}
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Descrizione') }}
                        </label>
                        <textarea rows="2"
                                wire:model.lazy="damages.{{ $i }}.description"
                                :disabled="state.isLocked || damagesReadonly"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800"
                                placeholder="{{ __('Dettagli del danno…') }}"></textarea>
                        @error("damages.$i.description") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @empty
                <div class="p-4 rounded-md bg-gray-50 dark:bg-gray-900/40 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Nessun danno pregresso aperto per questo veicolo.') }}
                </div>
            @endforelse
        </div>

        {{-- Salva danni: disabilitato in pickup --}}
        <div class="pt-4">
            <button type="button" @click="$wire.saveDamages()"
                    :disabled="state.isLocked || !state.hasChecklistId"
                    x-show="!damagesReadonly"
                    class="btn btn-primary shadow-none px-4
                        !bg-primary !text-primary-content !border-primary
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                        disabled:opacity-50 disabled:cursor-not-allowed">
                {{ __('Salva danni') }}
            </button>
        </div>
    </section>

    {{-- ========== TAB 4: Media & Documenti – FOTO CHECKLIST ========== --}}
    <section x-show="state.active === 'media'" class="max-w-full">
        {{-- ====== Pannello PDF Checklist ====== --}}
        <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                        {{ __('Checklist PDF') }}
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{-- Stato: LOCKED / Dirty / Aggiornato --}}
                        @if($isLocked)
                            {{ __('Checklist bloccata dalla firma: non è possibile rigenerare il PDF.') }}
                        @elseif($pdf_dirty)
                            {{ __('Sono presenti modifiche. Puoi generare un nuovo PDF.') }}
                        @else
                            {{ __('PDF allineato ai dati correnti.') }}
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Apri (ultimo PDF non firmato) --}}
                    @if($last_pdf_url)
                        <a href="{{ $last_pdf_url }}" target="_blank"
                        class="btn btn-outline shadow-none px-3
                                border-gray-300 text-gray-700 hover:bg-gray-50">
                            {{ __('Apri') }}
                        </a>
                    @endif

                    {{-- Genera PDF (disabilitato se locked o se non ci sono cambiamenti) --}}
                    <button type="button"
                            wire:click="generatePdf"
                            @disabled($isLocked || !$pdf_dirty)
                            class="btn btn-primary shadow-none px-3
                                !bg-primary !text-primary-content !border-primary
                                hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                                disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ __('Genera PDF Checklist') }}
                    </button>
                </div>
            </div>
        </div>

        <div x-data="checklistMedia({
            checklistId: @js($checklistId),
            rentalId:    @js($rental->id),
            locked:      @js($isLocked),
            initial:     @js($mediaChecklist),
            routes: {
                uploadChecklistPhoto: {{ Js::from(route('checklists.media.photos.store', $checklistId ?? 0)) }},
                deleteMedia:          {{ Js::from(route('media.destroy', 0)) }},
            },
        })">

            <template x-if="!state.checklistId">
                <div class="p-4 rounded-md bg-yellow-50 text-yellow-800 text-sm mb-4">
                    {{ __('Per caricare media, salva prima i dati base della checklist.') }}
                </div>
            </template>

            <div class="grid grid-cols-2 gap-6">
                {{-- Gruppo: Odometro --}}
                <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                            {{ __('Foto odometro') }}
                        </h3>

                        <div class="flex items-center gap-2">
                            <input type="file" x-ref="fileOdom" accept="image/jpeg,image/png"
                                :disabled="state.locked || !state.checklistId"
                                class="text-sm" />
                            <button type="button"
                                    @click="upload('odometer', $refs.fileOdom)"
                                    :disabled="state.locked || !state.checklistId"
                                    class="btn btn-outline shadow-none px-3
                                        border-gray-300 text-gray-700 hover:bg-gray-50
                                        disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ __('Carica') }}
                            </button>
                        </div>
                    </div>

                    {{-- mini-tabella --}}
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1 pr-2">ID</th>
                                    <th class="py-1 pr-2">{{ __('Nome') }}</th>
                                    <th class="py-1 pr-2">{{ __('Dimensione') }}</th>
                                    <th class="py-1 pr-2">{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="m in state.photos.odometer" :key="m.id">
                                    <tr class="border-t border-gray-200 dark:border-gray-700">
                                        <td class="py-1 pr-2" x-text="m.id"></td>
                                        <td class="py-1 pr-2" x-text="m.name"></td>
                                        <td class="py-1 pr-2">
                                            <span x-text="formatBytes(m.size)"></span>
                                        </td>
                                        <td class="py-1 pr-2">
                                            <a :href="m.url" target="_blank"
                                            class="underline text-primary mr-2">{{ __('Apri') }}</a>
                                            <button type="button"
                                                    @click="destroy(m.id, 'odometer')"
                                                    :disabled="state.locked"
                                                    class="text-rose-600 hover:underline disabled:opacity-50">
                                                {{ __('Elimina') }}
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="state.photos.odometer.length === 0">
                                    <td colspan="4" class="py-2 text-gray-500">{{ __('Nessuna foto caricata.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Gruppo: Carburante --}}
                <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                            {{ __('Foto indicatore carburante') }}
                        </h3>
                        <div class="flex items-center gap-2">
                            <input type="file" x-ref="fileFuel" accept="image/jpeg,image/png"
                                :disabled="state.locked || !state.checklistId"
                                class="text-sm" />
                            <button type="button"
                                    @click="upload('fuel', $refs.fileFuel)"
                                    :disabled="state.locked || !state.checklistId"
                                    class="btn btn-outline shadow-none px-3
                                        border-gray-300 text-gray-700 hover:bg-gray-50
                                        disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ __('Carica') }}
                            </button>
                        </div>
                    </div>

                    {{-- mini-tabella --}}
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1 pr-2">ID</th>
                                    <th class="py-1 pr-2">{{ __('Nome') }}</th>
                                    <th class="py-1 pr-2">{{ __('Dimensione') }}</th>
                                    <th class="py-1 pr-2">{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="m in state.photos.fuel" :key="m.id">
                                    <tr class="border-t border-gray-200 dark:border-gray-700">
                                        <td class="py-1 pr-2" x-text="m.id"></td>
                                        <td class="py-1 pr-2" x-text="m.name"></td>
                                        <td class="py-1 pr-2"><span x-text="formatBytes(m.size)"></span></td>
                                        <td class="py-1 pr-2">
                                            <a :href="m.url" target="_blank"
                                            class="underline text-primary mr-2">{{ __('Apri') }}</a>
                                            <button type="button"
                                                    @click="destroy(m.id, 'fuel')"
                                                    :disabled="state.locked"
                                                    class="text-rose-600 hover:underline disabled:opacity-50">
                                                {{ __('Elimina') }}
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="state.photos.fuel.length === 0">
                                    <td colspan="4" class="py-2 text-gray-500">{{ __('Nessuna foto caricata.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Gruppo: Esterni --}}
                <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                            {{ __('Foto esterni veicolo') }}
                        </h3>
                        <div class="flex items-center gap-2">
                            <input type="file" x-ref="fileExt" accept="image/jpeg,image/png"
                                :disabled="state.locked || !state.checklistId"
                                class="text-sm" />
                            <button type="button"
                                    @click="upload('exterior', $refs.fileExt)"
                                    :disabled="state.locked || !state.checklistId"
                                    class="btn btn-outline shadow-none px-3
                                        border-gray-300 text-gray-700 hover:bg-gray-50
                                        disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ __('Carica') }}
                            </button>
                        </div>
                    </div>

                    {{-- mini-tabella --}}
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1 pr-2">ID</th>
                                    <th class="py-1 pr-2">{{ __('Nome') }}</th>
                                    <th class="py-1 pr-2">{{ __('Dimensione') }}</th>
                                    <th class="py-1 pr-2">{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="m in state.photos.exterior" :key="m.id">
                                    <tr class="border-t border-gray-200 dark:border-gray-700">
                                        <td class="py-1 pr-2" x-text="m.id"></td>
                                        <td class="py-1 pr-2" x-text="m.name"></td>
                                        <td class="py-1 pr-2"><span x-text="formatBytes(m.size)"></span></td>
                                        <td class="py-1 pr-2">
                                            <a :href="m.url" target="_blank"
                                            class="underline text-primary mr-2">{{ __('Apri') }}</a>
                                            <button type="button"
                                                    @click="destroy(m.id, 'exterior')"
                                                    :disabled="state.locked"
                                                    class="text-rose-600 hover:underline disabled:opacity-50">
                                                {{ __('Elimina') }}
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="state.photos.exterior.length === 0">
                                    <td colspan="4" class="py-2 text-gray-500">{{ __('Nessuna foto caricata.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

{{-- ========== Script Alpine: stato tab ==========
     Semplice gestore locale; attiveremo gli altri tab dopo lo step 4B (salvataggio base).
--}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('checklistTabs', () => ({
        state: {
            active: 'base',       // tab iniziale
            hasChecklistId: false,
            isLocked: false,
        },

        init() {
            // Eventi Livewire → aggiornano lo stato runtime
            window.addEventListener('checklist-base-saved', (e) => {
                this.state.hasChecklistId = true;
                this.state.isLocked = !!e.detail.locked;
                // this.state.active = 'checklist'; // opzionale
            });

            window.addEventListener('checklist-locked', (e) => {
                this.state.hasChecklistId = true;
                this.state.isLocked = true;
            });
        },

        // Bootstrap iniziale da server (mount Livewire)
        bootstrapFromServer(payload = { hasId: false, locked: false }) {
            this.state.hasChecklistId = !!payload.hasId;
            this.state.isLocked = !!payload.locked;
        },

        go(name) { this.state.active = name; },

        tabClass(name) {
            const base = 'px-3 py-2 text-sm font-medium border-b-2';
            const isActive = this.state.active === name;
            return [
                base,
                isActive
                    ? 'border-primary text-primary'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ].join(' ');
        },
    }));
    Alpine.data('checklistMedia', (cfg) => ({
        state: {
            checklistId: cfg.checklistId,
            rentalId: cfg.rentalId,
            locked: cfg.locked,
            photos: {
                odometer: cfg.initial?.odometer || [],
                fuel:     cfg.initial?.fuel     || [],
                exterior: cfg.initial?.exterior || [],
            },
        },

        async upload(kind, fileInput) {
            if (this.state.locked || !this.state.checklistId) return;
            const file = fileInput?.files?.[0];
            if (!file) {
                this.toast('warning', '{{ __("Seleziona un file da caricare.") }}');
                return;
            }

            const form = new FormData();
            form.append('file', file);
            form.append('kind', kind);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            try {
                const res = await fetch(cfg.routes.uploadChecklistPhoto, {
                    method: 'POST',
                    body: form,
                    headers: { 'Accept': 'application/json' },
                });

                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Upload fallito');
                }

                const data = await res.json();
                // Push nella tabella del gruppo
                this.state.photos[kind].push({
                    id: data.media_id,
                    name: data.name ?? file.name,
                    url: data.url,
                    size: data.size ?? file.size,
                });

                // Reset input
                fileInput.value = '';
                this.toast('success', '{{ __("Foto caricata.") }}');
            } catch (e) {
                this.toast('error', e.message || 'Errore di rete');
            }
        },

        async destroy(mediaId, kind) {
            if (this.state.locked) return;

            const url = cfg.routes.deleteMedia.replace(/0$/, String(mediaId));
            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                });

                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Eliminazione fallita');
                }

                // Rimuovi dalla lista locale
                this.state.photos[kind] = this.state.photos[kind].filter(x => x.id !== mediaId);
                this.toast('success', '{{ __("Foto eliminata.") }}');
            } catch (e) {
                this.toast('error', e.message || 'Errore di rete');
            }
        },

        formatBytes(b) {
            if (!b && b !== 0) return '';
            const u = ['B','KB','MB','GB']; let i = 0; let n = b;
            while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
            return n.toFixed(1) + ' ' + u[i];
        },

        toast(type, message, duration = 3000) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { type, message, duration }}))
        },
    }));
})
</script>