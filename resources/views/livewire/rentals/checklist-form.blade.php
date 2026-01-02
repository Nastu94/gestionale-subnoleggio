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

            <div class="grid grid-cols-2 gap-2">

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

                {{-- Carburante (gauge stile cruscotto + click migliorato ai limiti + animazioni fluide) --}}
                <div
                    x-data="{
                        /**
                        * Livewire <-> Alpine (stessa property).
                        */
                        fuel: @entangle('fuel_percent').live,

                        /**
                        * Valori consentiti (niente intermedi).
                        */
                        allowed: [0, 20, 40, 50, 60, 80, 100],

                        /**
                        * Stato drag.
                        */
                        dragging: false,

                        /**
                        * Magnet agli estremi: percentuale di larghezza usata come “zona facile”
                        * per scegliere 0 o 100 senza dover cliccare pixel-perfect.
                        */
                        edgeMagnet: 0.12, // 12% a sinistra e 12% a destra

                        /**
                        * Snap al valore consentito più vicino.
                        */
                        snapToAllowed(v) {
                            const n = Number(v ?? 0);
                            let best = this.allowed[0];
                            let bestDiff = Math.abs(n - best);

                            for (const a of this.allowed) {
                                const d = Math.abs(n - a);
                                if (d < bestDiff) {
                                    best = a;
                                    bestDiff = d;
                                }
                            }
                            return best;
                        },

                        /**
                        * Set centralizzato: applica clamp + snap e aggiorna la property.
                        */
                        setFuel(v) {
                            const clamped = Math.max(0, Math.min(100, Number(v ?? 0)));
                            const snapped = this.snapToAllowed(clamped);
                            if (Number(this.fuel ?? 0) !== snapped) {
                                this.fuel = snapped; // aggiorna Livewire via entangle
                            }
                        },

                        /**
                        * Converte la posizione del puntatore in 0..100 (con magnet ai bordi).
                        * Nota: mappo linearmente sull'asse X del gauge (più intuitivo per il click).
                        */
                        valueFromPointer(evt) {
                            const rect = this.$refs.gauge.getBoundingClientRect();
                            const x = Math.min(Math.max(evt.clientX - rect.left, 0), rect.width);
                            const ratio = rect.width > 0 ? (x / rect.width) : 0;

                            // Zone magnet ai limiti
                            if (ratio <= this.edgeMagnet) return 0;
                            if (ratio >= (1 - this.edgeMagnet)) return 100;

                            // Rimappo la parte centrale (senza magnet) a 0..100
                            const norm = (ratio - this.edgeMagnet) / (1 - (2 * this.edgeMagnet));
                            return norm * 100;
                        },

                        /**
                        * Aggiorna da evento puntatore.
                        */
                        updateFromPointer(evt) {
                            // Se bloccato, non consentire interazioni
                            if (typeof state !== 'undefined' && state.isLocked) return;

                            const v = this.valueFromPointer(evt);
                            this.setFuel(v);
                        },

                        /**
                        * Angolo lancetta -90..+90
                        */
                        get needleAngle() {
                            const clamped = Math.max(0, Math.min(100, Number(this.fuel ?? 0)));
                            return -90 + (clamped * 180 / 100);
                        },

                        /**
                        * Valore visualizzato sempre “pulito”.
                        */
                        get displayFuel() {
                            return this.snapToAllowed(this.fuel);
                        },

                        /**
                        * Init: se arriva un valore non ammesso, lo riallineo.
                        */
                        init() {
                            this.setFuel(this.fuel ?? 0);
                        }
                    }"
                    x-init="init()"
                    class="flex flex-col items-center"
                >
                    <label for="fuel_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Carburante (%)') }}
                    </label>

                    <div class="relative mt-2 w-full max-w-sm">
                        <div class="rounded-2xl border border-gray-200/70 dark:border-gray-700/70 bg-white/80 dark:bg-gray-900/60 shadow-sm px-4 pt-4 pb-3">
                            {{-- Area interattiva: click/drag sul gauge --}}
                            <div
                                x-ref="gauge"
                                class="relative cursor-pointer"
                                :class="(typeof state !== 'undefined' && state.isLocked) ? 'opacity-60 cursor-not-allowed' : ''"
                                @pointerdown.prevent="
                                    if (typeof state !== 'undefined' && state.isLocked) return;
                                    dragging = true;
                                    updateFromPointer($event);
                                "
                                @pointermove.prevent="
                                    if (!dragging) return;
                                    updateFromPointer($event);
                                "
                                @pointerup.window="dragging = false"
                                @pointercancel.window="dragging = false"
                            >
                                <svg viewBox="0 0 200 120" class="w-full h-auto">
                                    {{-- Cornice esterna --}}
                                    <path
                                        d="M16,100 A84,84 0 0,1 184,100"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="14"
                                        class="text-gray-200 dark:text-gray-700"
                                        stroke-linecap="round"
                                        opacity="0.9"
                                    />

                                    {{-- Arco base --}}
                                    <path
                                        d="M20,100 A80,80 0 0,1 180,100"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="10"
                                        class="text-gray-300 dark:text-gray-600"
                                        stroke-linecap="round"
                                    />

                                    {{-- Riempimento progressivo (fluido) --}}
                                    <path
                                        d="M20,100 A80,80 0 0,1 180,100"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="10"
                                        class="text-emerald-500"
                                        stroke-linecap="round"
                                        pathLength="100"
                                        :stroke-dasharray="displayFuel === 100
                                            ? '100 0'
                                            : `${displayFuel} ${100 - displayFuel + 0.6}`"
                                        style="transition: stroke-dasharray 220ms ease-out, opacity 120ms ease-out;"
                                        :opacity="displayFuel === 0 ? 0 : 0.95"
                                    />

                                    {{-- Zona riserva evidenziata (0..20) - endpoint corretto al 20% --}}
                                    <path
                                        d="M20,100 A80,80 0 0,1 35.28,52.98"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="10"
                                        class="text-red-500"
                                        stroke-linecap="round"
                                        opacity="0.95"
                                    />

                                    {{-- Tacche SOLO sui valori consentiti --}}
                                    <g class="text-gray-700 dark:text-gray-300">
                                        @php $allowed = [0,20,40,50,60,80,100]; @endphp
                                        @foreach ($allowed as $val)
                                            @php
                                                $angle = -90 + ($val * 180 / 100);
                                                $isMajor = in_array($val, [0,20,40,60,80,100], true);
                                                $y1 = $isMajor ? 18 : 22;
                                                $y2 = $isMajor ? 38 : 34;
                                                $w  = $isMajor ? 3 : 2;
                                                $op = $isMajor ? 0.85 : 0.65;
                                            @endphp
                                            <line
                                                x1="100" y1="{{ $y1 }}"
                                                x2="100" y2="{{ $y2 }}"
                                                stroke="currentColor"
                                                stroke-width="{{ $w }}"
                                                transform="rotate({{ $angle }} 100 100)"
                                                opacity="{{ $op }}"
                                            />
                                        @endforeach
                                    </g>

                                    {{-- Etichette --}}
                                    <text x="22" y="112" font-size="13" class="fill-gray-700 dark:fill-gray-300">E</text>
                                    <text x="172" y="112" font-size="13" class="fill-gray-700 dark:fill-gray-300">F</text>

                                    {{-- Iconcina --}}
                                    <text x="96" y="62" font-size="14" class="fill-gray-700 dark:fill-gray-300">⛽</text>

                                    {{-- Lancetta (fluida via transition) --}}
                                    <g
                                        :transform="`rotate(${needleAngle} 100 100)`"
                                        style="transition: transform 180ms cubic-bezier(.2,.8,.2,1);"
                                    >
                                        <line
                                            x1="100" y1="100"
                                            x2="100" y2="34"
                                            stroke="currentColor"
                                            stroke-width="4"
                                            class="text-red-600"
                                            stroke-linecap="round"
                                        />
                                    </g>

                                    {{-- Perno --}}
                                    <circle cx="100" cy="100" r="8" class="fill-gray-800 dark:fill-gray-200" />
                                    <circle cx="100" cy="100" r="3" class="fill-gray-200 dark:fill-gray-800" />
                                </svg>

                                {{-- “Hot zones” cliccabili grandi per 0 e 100 --}}
                                <button
                                    type="button"
                                    class="absolute left-0 bottom-0 -translate-x-2 translate-y-2 w-12 h-12 rounded-full"
                                    title="{{ __('Imposta riserva (0%)') }}"
                                    @click.prevent="setFuel(0)"
                                    :disabled="(typeof state !== 'undefined' && state.isLocked)"
                                    :class="(typeof state !== 'undefined' && state.isLocked) ? 'opacity-0 pointer-events-none' : 'opacity-0'"
                                ></button>

                                <button
                                    type="button"
                                    class="absolute right-0 bottom-0 translate-x-2 translate-y-2 w-12 h-12 rounded-full"
                                    title="{{ __('Imposta pieno (100%)') }}"
                                    @click.prevent="setFuel(100)"
                                    :disabled="(typeof state !== 'undefined' && state.isLocked)"
                                    :class="(typeof state !== 'undefined' && state.isLocked) ? 'opacity-0 pointer-events-none' : 'opacity-0'"
                                ></button>

                                {{-- Input “vero” per accessibilità/validazione: nascosto, ma resta wire:model.live --}}
                                <input
                                    type="range"
                                    id="fuel_percent"
                                    wire:model.live="fuel_percent"
                                    min="0"
                                    max="100"
                                    step="1"
                                    class="sr-only"
                                />
                            </div>

                            <div class="mt-2 flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
                                <div>
                                    {{ __('Livello:') }}
                                    <strong x-text="`${displayFuel}%`"></strong>
                                </div>

                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    0 • 20 • 40 • 50 • 60 • 80 • 100
                                </div>
                            </div>
                        </div>
                    </div>

                    @error('fuel_percent')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
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
            @csrf

            {{-- DOCUMENTI: mostrali solo in pickup (in return non servono) --}}
            @if($type === 'pickup')
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">
                        {{ __('Documenti') }}
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="inline-flex items-center">
                            <input type="checkbox" wire:model="checklist.documents.id_card"
                                :disabled="state.isLocked"
                                class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                {{ __('Carta d’identità acquisita') }}
                            </span>
                        </label>

                        <label class="inline-flex items-center">
                            <input type="checkbox" wire:model="checklist.documents.driver_license"
                                :disabled="state.isLocked"
                                class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                {{ __('Patente acquisita') }}
                            </span>
                        </label>

                        <label class="inline-flex items-center">
                            <input type="checkbox" wire:model="checklist.documents.contract_copy"
                                :disabled="state.isLocked"
                                class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                {{ __('Copia contratto consegnata') }}
                            </span>
                        </label>
                    </div>

                    @error('checklist.documents.id_card') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    @error('checklist.documents.driver_license') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    @error('checklist.documents.contract_copy') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

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

            {{-- Aggiungi danno: attivo solo in return, con checklist esistente e non locked --}}
            <button type="button"
                    x-show="!damagesReadonly"
                    :disabled="state.isLocked || !state.hasChecklistId"
                    @click="$wire.addDamageRow()"
                    class="btn shadow-none px-3"
                    :class="(state.isLocked || !state.hasChecklistId)
                        ? 'btn-outline border-gray-300 text-gray-400 cursor-not-allowed'
                        : 'btn-primary !bg-primary !text-primary-content !border-primary hover:brightness-95'">
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

                        {{-- Azioni riga: "Rimuovi" attivo solo in return, non locked --}}
                        <div class="flex items-end justify-end">
                            <button type="button"
                                    :disabled="state.isLocked || damagesReadonly"
                                    @click="$wire.removeDamageRow({{ $i }})"
                                    class="btn px-3 py-1.5 rounded-md"
                                    :class="(state.isLocked || damagesReadonly)
                                        ? 'btn-outline border-gray-300 text-gray-400 cursor-not-allowed'
                                        : 'btn-outline border-rose-300 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20'">
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
                <div x-show="damagesReadonly" class="p-4 rounded-md bg-gray-50 dark:bg-gray-900/40 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Nessun danno pregresso aperto per questo veicolo.') }}
                </div>
                <div x-show="!damagesReadonly" class="p-4 rounded-md bg-gray-50 dark:bg-gray-900/40 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Nessun danno segnalato. Usa il pulsante "Aggiungi danno" per crearne uno.') }}
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
                damages:     @js($damages),
                routes: {
                    uploadChecklistPhoto: {{ Js::from(route('checklists.media.photos.store', $checklistId ?? 0)) }},
                    deleteMedia:          {{ Js::from(route('media.destroy', 0)) }},
                    uploadDamagePhoto:    {{ Js::from(route('damages.media.photos.store', 0)) }},
                    indexDamagePhotos:    {{ Js::from(route('damages.media.photos.index', 0)) }},
                },
            })"
            wire:ignore
            wire:key="media-block-{{ $checklistId ?? 'new' }}">

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

                {{-- Gruppo: Foto Danni (solo in return) --}}
                @if($type === 'return')
                    <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4 mb-6">
                        <div x-show="(state.damages?.length || 0) > 0" class="flex grid items-center justify-between">
                            <div class="flex grid gap-1">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                    {{ __('Foto danni') }}
                                </h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ __('Seleziona un danno e carica una o più foto. Il contatore si aggiorna automaticamente.') }}
                                </p>
                            </div>

                            {{-- Selettore danno + upload --}}
                            <div class="flex items-center gap-2">
                                {{-- Select danno: etichetta “Area · Gravità” oppure “Danno #ID” --}}
                                <select x-model.number="state.selectedDamageId" 
                                        :disabled="state.locked || !state.checklistId || (state.damages?.length || 0) === 0"
                                        class="mt-1 block rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
                                    <option value="">{{ __('Seleziona danno…') }}</option>
                                    <template x-for="d in state.damages" :key="d.id">
                                        <option :value="d.id" x-text="d.label"></option>
                                    </template>
                                </select>

                                <input type="file" x-ref="fileDamage" accept="image/jpeg,image/png"
                                    :disabled="state.locked || !state.checklistId || !state.selectedDamageId"
                                    class="text-sm" />

                                <button type="button"
                                        @click="uploadDamage($refs.fileDamage)"
                                        :disabled="state.locked || !state.checklistId || !state.selectedDamageId"
                                        class="btn btn-outline shadow-none px-3
                                            border-gray-300 text-gray-700 hover:bg-gray-50
                                            disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ __('Carica') }}
                                </button>
                            </div>
                            {{-- mini-tabella foto del danno selezionato --}}
                            <div class="mt-3 overflow-x-auto" 
                                x-show="Number.isInteger(state.selectedDamageId) && state.selectedDamageId > 0">
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
                                        <template x-for="m in state.damagePhotos" :key="m.id">
                                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                                <td class="py-1 pr-2" x-text="m.id"></td>
                                                <td class="py-1 pr-2" x-text="m.name"></td>
                                                <td class="py-1 pr-2"><span x-text="formatBytes(m.size)"></span></td>
                                                <td class="py-1 pr-2">
                                                    <a :href="m.url" target="_blank" class="underline text-primary mr-2">{{ __('Apri') }}</a>
                                                    <button type="button"
                                                            @click="destroyDamagePhoto(m.id)"
                                                            :disabled="state.locked"
                                                            class="text-rose-600 hover:underline disabled:opacity-50">
                                                        {{ __('Elimina') }}
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="state.damagePhotos.length === 0">
                                            <td colspan="4" class="py-2 text-gray-500">{{ __('Nessuna foto caricata per questo danno.') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Nota quando non ci sono danni (fallback puramente informativo) --}}
                        <div x-show="(state.damages?.length || 0) === 0"
                            class="mt-3 p-3 rounded bg-yellow-50 text-yellow-800 text-sm">
                            {{ __('Aggiungi prima almeno un danno nella tab “Danni” per caricare le foto.') }}
                        </div>
                    </div>
                @endif
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

    
        /**
        * Traduce area e gravità del danno per la UI.
        * Fallback: se non trova la chiave, mostra il valore originale.
        */
        const DAMAGE_AREA_LABELS = {
            // Inglese -> Italiano
            front: 'Anteriore',
            rear: 'Posteriore',
            left: 'Sinistra',
            right: 'Destra',
            interior: 'Interno',
            roof: 'Tetto',
            windshield: 'Parabrezza',
            wheel: 'Ruota',
            other: 'Altro',

            // Italiano già salvato -> Italiano (per robustezza)
            anteriore: 'Anteriore',
            posteriore: 'Posteriore',
            sinistra: 'Sinistra',
            destra: 'Destra',
            interno: 'Interno',
            tetto: 'Tetto',
            parabrezza: 'Parabrezza',
            ruota: 'Ruota',
            altro: 'Altro',
        };

        const DAMAGE_SEVERITY_LABELS = {
            low: 'Bassa',
            medium: 'Media',
            high: 'Alta',

            // Se per caso arrivano già in italiano
            bassa: 'Bassa',
            media: 'Media',
            alta: 'Alta',
        };

        /**
        * Normalizza chiavi per lookup.
        */
        const damageKey = (v) => String(v ?? '').trim().toLowerCase();

        /**
        * Traduzioni con fallback al valore originale.
        */
        const tDamageArea = (area) => DAMAGE_AREA_LABELS[damageKey(area)] ?? area;
        const tDamageSeverity = (severity) => DAMAGE_SEVERITY_LABELS[damageKey(severity)] ?? severity;

    Alpine.data('checklistMedia', (cfg) => ({
        // ===========================
        // Stato locale del pannello
        // ===========================

        state: {
            checklistId: cfg.checklistId,
            rentalId:    cfg.rentalId,
            locked:      cfg.locked,

            // Foto checklist (gruppi)
            photos: {
                odometer: cfg.initial?.odometer || [],
                fuel:     cfg.initial?.fuel     || [],
                exterior: cfg.initial?.exterior || [],
            },

            // Danni per la select (solo return): { id, label }
            // Danni per la select (solo return): { id, label }
            damages: (Array.isArray(cfg.damages) ? cfg.damages : [])
                .filter(d => Number.isInteger(d?.id))
                .map(d => ({
                    id: d.id,
                    // Etichetta "Area · Gravità" (tradotte) o fallback "Danno #ID"
                    label: [
                    tDamageArea(d.area) || null,
                    tDamageSeverity(d.severity) || null
                    ].filter(Boolean).join(' · ')
                    || `Danno #${d.id}`,
            })),

            // Danno attualmente selezionato (per upload/lista foto danni)
            selectedDamageId: null,

            // Lista foto del danno selezionato (mini-tabella)
            damagePhotos: [],
        },

        // ===========================
        // Lifecycle / watchers
        // ===========================
        init() {
            // Quando cambio danno carico la lista foto relativa
            this.$watch('state.selectedDamageId', (id) => {
                if (Number.isInteger(id) && id > 0) {
                    this.fetchDamagePhotos(id);
                } else {
                    this.state.damagePhotos = [];
                }
            });

            // quando Livewire salva la base, propaga l'ID alla sezione media
            window.addEventListener('checklist-base-saved', (e) => {
                const id = Number(e?.detail?.checklistId) || null;
                if (id) this.state.checklistId = id;
                this.state.locked = !!e?.detail?.locked;
            });

            // se la checklist viene bloccata (firma), riflettilo anche qui
            window.addEventListener('checklist-locked', (e) => {
                const id = Number(e?.detail?.checklistId) || null;
                if (id) this.state.checklistId = id;
                this.state.locked = true;
            });

            window.addEventListener('damages-updated', (e) => {
                const list = Array.isArray(e?.detail?.items) ? e.detail.items : [];
                const currentIds = new Set(list.map(d => Number(d.id)));

                // Ricostruisci le opzioni della select
                this.state.damages = list.map(d => ({
                    id: Number(d.id),
                    label: [d.area || null, d.severity || null].filter(Boolean).join(' · ') || `Danno #${d.id}`,
                }));

                // Se il danno selezionato non esiste più, resetta selezione e tabella
                if (!currentIds.has(Number(this.state.selectedDamageId))) {
                    this.state.selectedDamageId = null;
                    this.state.damagePhotos = [];
                }
            });
        },

        // ===========================
        // Helper URL dinamici
        // ===========================
        /** URL upload foto checklist (già in cfg.routes.uploadChecklistPhoto) */
        checklistUploadUrl() {
            let base = String(cfg.routes?.uploadChecklistPhoto || '');
            const id = Number(this.state.checklistId) || 0;
            if (base.endsWith('/0')) return base.slice(0, -2) + '/' + id;
            return base.replace(/\/0(\b|\/|$)/, '/' + id + '$1');
        },

        /** URL upload foto Danno: sostituisce '/0' con l'ID */
        damageUploadUrl(damageId) {
            let base = String(cfg.routes?.uploadDamagePhoto || '');
            if (base.endsWith('/0')) return base.slice(0, -2) + '/' + damageId;
            return base.replace(/\/0(\b|\/|$)/, '/' + damageId + '$1');
        },

        /** URL index foto Danno: sostituisce '/0' con l'ID */
        damageIndexUrl(damageId) {
            let base = String(cfg.routes?.indexDamagePhotos || '');
            if (base.endsWith('/0')) return base.slice(0, -2) + '/' + damageId;
            return base.replace(/\/0(\b|\/|$)/, '/' + damageId + '$1');
        },

        /** URL delete media generico: sostituisce '/0' con il mediaId */
        deleteUrl(mediaId) {
            let base = String(cfg.routes?.deleteMedia || '');
            if (base.endsWith('/0')) return base.slice(0, -2) + '/' + mediaId;
            return base.replace(/\/0(\b|\/|$)/, '/' + mediaId + '$1');
        },

        // ===========================
        // Upload foto CHECKLIST (odometer|fuel|exterior)
        // ===========================
        async upload(kind, fileInput) {
            // Blocca se locked o senza checklist
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
                const res = await fetch(this.checklistUploadUrl(), {
                    method: 'POST',
                    body: form,
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Upload fallito');
                }

                const data = await res.json();

                // Garantisce esistenza dell'array gruppo
                if (!this.state.photos) this.state.photos = {};
                if (!Array.isArray(this.state.photos[kind])) this.state.photos[kind] = [];

                // Inserisce nuovo record includendo delete_url calcolato
                this.state.photos[kind].push({
                    id:   data.media_id,
                    name: data.name ?? file.name,
                    url:  data.url,
                    size: data.size ?? file.size,
                    delete_url: this.deleteUrl(data.media_id),
                });

                // Reset input + toast
                fileInput.value = '';
                this.toast('success', '{{ __("Foto caricata.") }}');
            } catch (e) {
                this.toast('error', e.message || 'Errore di rete');
            }
        },

        // ===========================
        // Delete foto CHECKLIST
        // ===========================
        async destroy(mediaId, kind) {
            if (this.state.locked) return;

            // Guard robusto sugli array
            const list = (this.state.photos && Array.isArray(this.state.photos[kind]))
                ? this.state.photos[kind]
                : null;

            if (!list) {
                return;
            }

            const item = list.find(x => Number(x.id) === Number(mediaId));
            const del  = item?.delete_url ?? this.deleteUrl(mediaId);
            if (!del) {
                this.toast('error', 'URL eliminazione non disponibile.');
                return;
            }

            try {
                const res = await fetch(del, {
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

                // Rimuovi localmente
                const arr = this.state.photos[kind] || [];
                const idx = arr.findIndex(x => Number(x.id) === Number(mediaId));
                if (idx > -1) {
                    arr.splice(idx, 1);
                    this.state.photos = { ...this.state.photos, 
                        [kind]: [...arr],
                    };
                }
                this.toast('success', '{{ __("Foto eliminata.") }}');
            } catch (e) {
                this.toast('error', e.message || 'Errore di rete');
            }
        },

        // ===========================
        // Upload / Lista / Delete foto DANNI
        // ===========================
        async uploadDamage(fileInput) {
            if (this.state.locked || !this.state.checklistId) return;

            const damageId = Number(this.state.selectedDamageId);
            if (!Number.isInteger(damageId) || damageId <= 0) {
                this.toast('warning', '{{ __("Seleziona un danno.") }}');
                return;
            }

            const file = fileInput?.files?.[0];
            if (!file) {
                this.toast('warning', '{{ __("Seleziona un file da caricare.") }}');
                return;
            }

            const form = new FormData();
            form.append('file', file);

            try {
                const res = await fetch(this.damageUploadUrl(damageId), {
                    method: 'POST',
                    body: form,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Upload fallito');
                }

                // Aggiorna UI
                fileInput.value = '';
                this.toast('success', '{{ __("Foto caricata al danno.") }}');

                // Ricarica lista foto del danno selezionato
                await this.fetchDamagePhotos(damageId);
            } catch (e) {
                this.toast('error', e.message || 'Errore di rete');
            }
        },

        async fetchDamagePhotos(damageId) {
            try {
                const res = await fetch(this.damageIndexUrl(damageId), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error('Caricamento elenco foto fallito');
                const data = await res.json();
                this.state.damagePhotos = Array.isArray(data.items) ? data.items : [];
            } catch (e) {
                this.state.damagePhotos = [];
                this.toast('error', e.message || 'Errore nel recupero foto danno');
            }
        },

        async destroyDamagePhoto(mediaId) {
            if (this.state.locked) return;

            const rows = Array.isArray(this.state.damagePhotos) ? this.state.damagePhotos : [];
            const row  = rows.find(x => Number(x.id) === Number(mediaId));
            if (!row || !row.delete_url) {
                this.toast('error', 'URL eliminazione non disponibile.');
                return;
            }

            try {
                const res = await fetch(row.delete_url, {
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

                this.state.damagePhotos = rows.filter(x => Number(x.id) !== Number(mediaId));
                this.toast('success', '{{ __("Foto eliminata.") }}');
            } catch (e) {
                this.toast('error', e.message || 'Errore di rete');
            }
        },

        // ===========================
        // Utility UI
        // ===========================
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