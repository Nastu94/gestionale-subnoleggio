{{-- resources/views/livewire/rentals/create-wizard.blade.php --}}
@php
    // class helper per input "morbidi"
    $input = 'block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
            focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400
            dark:bg-gray-800 dark:border-gray-700';
    $btnIndigo = 'inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md
                text-xs font-semibold text-white uppercase hover:bg-indigo-500
                focus:outline-none focus:ring-2 focus:ring-indigo-300 transition';
    $btnSoft = 'inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold uppercase
            bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600';
    // true se è stato scelto un veicolo
    /** Best practice: evita notice se l'array è vuoto */
    $vehSelected = !empty($rentalData['vehicle_id']);
@endphp

<div class="space-y-6">
    {{-- Stepper header --}}
    <div class="flex items-center gap-3 text-xs font-semibold uppercase">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full {{ $step>=1 ? 'bg-indigo-600 text-white' : 'bg-gray-300' }}">1</span>
            <span>Dati noleggio</span>
        </div>
        <div class="h-px flex-1 bg-gray-300"></div>
        <div class="flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full {{ $step>=2 ? 'bg-indigo-600 text-white' : 'bg-gray-300' }}">2</span>
            <span>Cliente</span>
        </div>
        <div class="h-px flex-1 bg-gray-300"></div>
        <div class="flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full {{ $step>=3 ? 'bg-indigo-600 text-white' : 'bg-gray-300' }}">3</span>
            <span>Bozza</span>
        </div>
    </div>

    {{-- STEP 1: Dati noleggio --}}
    @if($step===1)
        @if(!$vehSelected)
            <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                Seleziona un veicolo per compilare i dettagli del noleggio.
            </div>
        @endif

        {{-- Vehicle (solo assegnati / o liberi per admin) --}}
        <label class="form-control">
            <span class="label-text mb-1 text-sm">Veicolo</span>
            <select wire:model.live="rentalData.vehicle_id" class="{{ $input }}">
                <option value="">— Seleziona veicolo —</option>
                @foreach($vehicles as $v)
                    <option value="{{ $v['id'] }}">{{ $v['label'] }}</option>
                @endforeach
            </select>
            @error('rentalData.vehicle_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        <fieldset @disabled(!$vehSelected) title="{{ $vehSelected ? '' : 'Seleziona prima un veicolo' }}">
            <div class="grid md:grid-cols-2 gap-4">
                    {{-- Sede ritiro (select) --}}
                    <label class="form-control">
                        <span class="label-text mb-1 text-sm">Sede ritiro</span>
                        <select wire:model.live="rentalData.pickup_location_id" class="{{ $input }}">
                            <option value="">— Seleziona sede —</option>
                            @foreach($locations as $l)
                                <option value="{{ $l['id'] }}">{{ $l['name'] }}</option>
                            @endforeach
                        </select>
                        @error('rentalData.pickup_location_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>

                    {{-- Sede riconsegna (select) --}}
                    <label class="form-control">
                        <span class="label-text mb-1 text-sm">Sede riconsegna</span>
                        <select wire:model.defer="rentalData.return_location_id" class="{{ $input }}">
                            <option value="">— Seleziona sede —</option>
                            @foreach($locations as $l)
                                <option value="{{ $l['id'] }}">{{ $l['name'] }}</option>
                            @endforeach
                        </select>
                        @error('rentalData.return_location_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>

                    {{-- Planned pickup --}}
                    <label class="form-control">
                        <span class="label-text mb-1 text-sm">Ritiro pianificato</span>
                        <input type="datetime-local" wire:model.defer="rentalData.planned_pickup_at" class="{{ $input }}" />
                        @error('rentalData.planned_pickup_at') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>

                    {{-- Planned return --}}
                    <label class="form-control">
                        <span class="label-text mb-1 text-sm">Riconsegna pianificata</span>
                        <input type="datetime-local" wire:model.defer="rentalData.planned_return_at" class="{{ $input }}" />
                        @error('rentalData.planned_return_at') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>

                    {{-- Notes --}}
                    <label class="form-control md:col-span-2">
                        <span class="label-text mb-1 text-sm">Note</span>
                        <textarea wire:model.defer="rentalData.notes" rows="3" class="{{ $input }}" placeholder="Note operative…"></textarea>
                        @error('rentalData.notes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>

                    {{-- Coperture e franchigie --}}
                    <div class="form-control md:col-span-2">
                        <span class="label-text mb-2 text-sm font-semibold">Coperture e franchigie</span>

                        <div class="grid md:grid-cols-2 gap-4">
                            {{-- RCA (obbligatoria): checkbox bloccata + info base --}}
                            <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="rounded" checked disabled>
                                    <span>RCA (Responsabilità Civile Auto)</span>
                                </label>

                                <div class="mt-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-600">Franchigia (€)</span>
                                        <span class="text-[11px] text-gray-500">
                                            Base:
                                            <strong>
                                                @php $base = $franchiseBase['rca'] ?? null; @endphp
                                                {{ is_numeric($base) ? '€ '.number_format($base, 2, ',', '.') : '—' }}
                                            </strong>
                                        </span>
                                    </div>
                                    <input
                                        type="number" step="0.01" min="0"
                                        class="{{ $input }} mt-1"
                                        wire:model.defer="franchise.rca"
                                        placeholder="Lascia vuoto per usare la base"
                                        title="Override opzionale: lascia vuoto per mantenere la franchigia base del veicolo"
                                    >
                                </div>
                                @error('franchise.rca') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Kasko --}}
                            <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="rounded" wire:model.defer="coverage.kasko">
                                    <span>Kasko</span>
                                </label>

                                <div class="mt-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-600">Franchigia (€)</span>
                                        <span class="text-[11px] text-gray-500">
                                            Base:
                                            <strong>
                                                @php $base = $franchiseBase['kasko'] ?? null; @endphp
                                                {{ is_numeric($base) ? '€ '.number_format($base, 2, ',', '.') : '—' }}
                                            </strong>
                                        </span>
                                    </div>
                                    <input
                                        type="number" step="0.01" min="0"
                                        class="{{ $input }} mt-1"
                                        wire:model.defer="franchise.kasko"
                                        placeholder="Lascia vuoto per usare la base"
                                        title="Override opzionale: lascia vuoto per mantenere la franchigia base del veicolo"
                                    >
                                </div>
                                @error('franchise.kasko') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Furto/Incendio --}}
                            <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="rounded" wire:model.defer="coverage.furto_incendio">
                                    <span>Furto / Incendio</span>
                                </label>

                                <div class="mt-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-600">Franchigia (€)</span>
                                        <span class="text-[11px] text-gray-500">
                                            Base:
                                            <strong>
                                                @php $base = $franchiseBase['furto_incendio'] ?? null; @endphp
                                                {{ is_numeric($base) ? '€ '.number_format($base, 2, ',', '.') : '—' }}
                                            </strong>
                                        </span>
                                    </div>
                                    <input
                                        type="number" step="0.01" min="0"
                                        class="{{ $input }} mt-1"
                                        wire:model.defer="franchise.furto_incendio"
                                        placeholder="Lascia vuoto per usare la base"
                                        title="Override opzionale: lascia vuoto per mantenere la franchigia base del veicolo"
                                    >
                                </div>
                                @error('franchise.furto_incendio') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Cristalli --}}
                            <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="rounded" wire:model.defer="coverage.cristalli">
                                    <span>Cristalli</span>
                                </label>

                                <div class="mt-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-600">Franchigia (€)</span>
                                        <span class="text-[11px] text-gray-500">
                                            Base:
                                            <strong>
                                                @php $base = $franchiseBase['cristalli'] ?? null; @endphp
                                                {{ is_numeric($base) ? '€ '.number_format($base, 2, ',', '.') : '—' }}
                                            </strong>
                                        </span>
                                    </div>
                                    <input
                                        type="number" step="0.01" min="0"
                                        class="{{ $input }} mt-1"
                                        wire:model.defer="franchise.cristalli"
                                        placeholder="Lascia vuoto per usare la base"
                                        title="Override opzionale: lascia vuoto per mantenere la franchigia base del veicolo"
                                    >
                                </div>
                                @error('franchise.cristalli') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Assistenza --}}
                            <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="rounded" wire:model.defer="coverage.assistenza">
                                    <span>Soccorso stradale</span>
                                </label>
                                <div class="text-xs text-gray-600 mt-1">
                                    Include carro attrezzi.
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
        </fieldset>
    @endif

    {{-- STEP 2: Cliente --}}
    @if($step===2)
        <fieldset @disabled(!$vehSelected) title="{{ $vehSelected ? '' : 'Seleziona prima un veicolo' }}">
            <div class="grid md:grid-cols-2 gap-6">
                {{-- =========================
                    COLONNA SX — Ricerca e selezione
                ========================== --}}
                <div class="space-y-3">
                    <div class="text-sm font-semibold">Cerca cliente esistente</div>

                    <input
                        type="text"
                        wire:model.live.debounce.300ms="customerQuery"
                        class="{{ $input }}"
                        placeholder="Nome o n. patente…"
                    />

                    <div class="text-xs opacity-60">Min. 2 caratteri</div>

                    <div class="divide-y rounded-md border border-gray-200 dark:border-gray-700">
                        @forelse($customers as $c)
                            <div class="flex items-center justify-between p-2">
                                <div class="text-sm">
                                    <div class="font-medium">{{ $c['name'] }}</div>
                                    <div class="opacity-70">Patente: {{ $c['driver_license_number'] }}</div>
                                </div>

                                <button
                                    type="button"
                                    wire:click="selectCustomer({{ $c['id'] }})"
                                    class="{{ $btnIndigo }}"
                                >
                                    Seleziona
                                </button>
                            </div>
                        @empty
                            <div class="p-3 text-sm opacity-70">Nessun risultato</div>
                        @endforelse
                    </div>
                </div>

                {{-- =========================
                    COLONNA DX — Form cliente (stile Customers/Show)
                    - Nome completo auto da first_name + last_name
                ========================== --}}
                <div
                    class="space-y-10"
                    x-data="{
                        first: @entangle('customerForm.first_name').live,
                        last:  @entangle('customerForm.last_name').live,
                        full:  '',

                        normalize(s){
                            return (s || '').toString().trim().replace(/\s+/g,' ');
                        },

                        sync(){
                            const f = this.normalize(this.first);
                            const l = this.normalize(this.last);
                            this.full = this.normalize((f + ' ' + l).trim());

                            // Compatibilità con logica attuale:
                            // customerForm.name resta il campo canonico per validazione/payload
                            this.$wire.set('customerForm.name', this.full);
                        },

                        init(){
                            this.sync();
                            this.$watch('first', () => this.sync());
                            this.$watch('last',  () => this.sync());
                        }
                    }"
                >
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold">
                            {{ $customerPopulated ? 'Cliente selezionato (modificabile)' : 'Crea nuovo cliente' }}
                        </div>

                        @if($customerPopulated)
                            <span class="text-xs rounded-full px-2 py-0.5 bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300">
                                ID #{{ $customer_id }}
                            </span>
                        @endif
                    </div>

                    {{-- Campo nascosto: mantiene customerForm.name popolato --}}
                    <input type="hidden" wire:model.defer="customerForm.name" />
                    @error('customerForm.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

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
                                    <label class="text-xs text-gray-600 dark:text-gray-300">Nome *</label>
                                    <input
                                        type="text"
                                        wire:model.defer="customerForm.first_name"
                                        class="mt-1 {{ $input }}"
                                    />
                                    @error('customerForm.first_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="text-xs text-gray-600 dark:text-gray-300">Cognome *</label>
                                    <input
                                        type="text"
                                        wire:model.defer="customerForm.last_name"
                                        class="mt-1 {{ $input }}"
                                    />
                                    @error('customerForm.last_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-xs text-gray-600 dark:text-gray-300">
                                        Nome completo (automatico)
                                    </label>
                                    <input
                                        type="text"
                                        x-bind:value="full"
                                        disabled
                                        class="mt-1 {{ $input }} opacity-70 cursor-not-allowed"
                                    />
                                </div>

                                <div>
                                    <label class="text-xs text-gray-600 dark:text-gray-300">Data di nascita</label>
                                    <input
                                        type="date"
                                        wire:model.defer="customerForm.birth_date"
                                        class="mt-1 {{ $input }}"
                                    />
                                    @error('customerForm.birth_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <livewire:shared.cargos-luogo-picker
                                wire:model="customerForm.birth_place_code"
                                title="Luogo di nascita"
                                hint="Comune italiano o nazione estera"
                                wire:key="wiz-birth-{{ $customer_id ?? 'new' }}"
                            />

                            <livewire:shared.cargos-luogo-picker
                                wire:model="customerForm.citizenship_place_code"
                                title="Cittadinanza"
                                hint="Seleziona solo la nazione"
                                mode="country-only"
                                wire:key="wiz-cit-{{ $customer_id ?? 'new' }}"
                            />
                        </div>
                    </section>

                    {{-- ======================================================
                        2. DOCUMENTI
                        - Documento identità solo lato CARGOS
                    ======================================================= --}}
                    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <header class="px-6 py-4 border-b dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Documenti
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Documento di identità e patente
                            </p>
                        </header>

                        <div class="p-6 space-y-8">
                            {{-- Documento identità --}}
                            <div class="space-y-4">
                                <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                                    Documento di identità
                                </h4>

                                <livewire:shared.cargos-document-type-picker
                                    wire:model="customerForm.identity_document_type_code"
                                    title="Tipo documento"
                                    wire:key="wiz-doc-type-{{ $customer_id ?? 'new' }}"
                                />
                                @error('customerForm.identity_document_type_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                                <div>
                                    <label class="text-xs text-gray-600 dark:text-gray-300">Numero documento</label>
                                    <input
                                        type="text"
                                        wire:model.defer="customerForm.doc_id_number"
                                        class="mt-1 {{ $input }}"
                                    />
                                    @error('customerForm.doc_id_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <livewire:shared.cargos-luogo-picker
                                    wire:model="customerForm.identity_document_place_code"
                                    title="Luogo di rilascio"
                                    hint="Comune italiano o nazione estera"
                                    wire:key="wiz-doc-place-{{ $customer_id ?? 'new' }}"
                                />
                                @error('customerForm.identity_document_place_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Patente --}}
                            <div class="space-y-4">
                                <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                                    Patente di guida
                                </h4>

                                <div class="grid md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="text-xs text-gray-600 dark:text-gray-300">Numero patente</label>
                                        <input
                                            type="text"
                                            wire:model.defer="customerForm.driver_license_number"
                                            class="mt-1 {{ $input }}"
                                        />
                                        @error('customerForm.driver_license_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="text-xs text-gray-600 dark:text-gray-300">Scadenza</label>
                                        <input
                                            type="date"
                                            wire:model.defer="customerForm.driver_license_expires_at"
                                            class="mt-1 {{ $input }}"
                                        />
                                        @error('customerForm.driver_license_expires_at') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <livewire:shared.cargos-luogo-picker
                                    wire:model="customerForm.driver_license_place_code"
                                    title="Luogo di rilascio patente"
                                    hint="Comune italiano o nazione estera"
                                    wire:key="wiz-dl-place-{{ $customer_id ?? 'new' }}"
                                />
                                @error('customerForm.driver_license_place_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Fiscale --}}
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-xs text-gray-600 dark:text-gray-300">Codice fiscale</label>
                                    <input type="text" wire:model.defer="customerForm.tax_code" class="mt-1 {{ $input }}" />
                                    @error('customerForm.tax_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="text-xs text-gray-600 dark:text-gray-300">Partita IVA</label>
                                    <input type="text" wire:model.defer="customerForm.vat" class="mt-1 {{ $input }}" />
                                    @error('customerForm.vat') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- ======================================================
                        3. CONTATTI
                    ======================================================= --}}
                    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <header class="px-6 py-4 border-b dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Contatti
                            </h3>
                        </header>

                        <div class="p-6 grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-xs text-gray-600 dark:text-gray-300">Email *</label>
                                <input type="email" wire:model.defer="customerForm.email" class="mt-1 {{ $input }}" />
                                @error('customerForm.email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="text-xs text-gray-600 dark:text-gray-300">Telefono *</label>
                                <input type="text" wire:model.defer="customerForm.phone" class="mt-1 {{ $input }}" />
                                @error('customerForm.phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </section>

                    {{-- ======================================================
                        4. INDIRIZZI
                        - Residenza solo lato CARGOS + indirizzo + CAP
                    ======================================================= --}}
                    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        <header class="px-6 py-4 border-b dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Indirizzi
                            </h3>
                        </header>

                        <div class="p-6 space-y-6">
                            <livewire:shared.cargos-luogo-picker
                                wire:model="customerForm.police_place_code"
                                title="Residenza"
                                hint="Comune italiano o nazione estera"
                                wire:key="wiz-res-{{ $customer_id ?? 'new' }}"
                            />
                            @error('customerForm.police_place_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                            <div>
                                <label class="text-xs text-gray-600 dark:text-gray-300">Indirizzo</label>
                                <input type="text" wire:model.defer="customerForm.address" class="mt-1 {{ $input }}" />
                                @error('customerForm.address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="md:w-40">
                                <label class="text-xs text-gray-600 dark:text-gray-300">CAP</label>
                                <input type="text" wire:model.defer="customerForm.zip" class="mt-1 {{ $input }}" />
                                @error('customerForm.zip') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </section>

                    {{-- AZIONE --}}
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="createOrUpdateCustomer" class="{{ $btnIndigo }}">
                            {{ $customerPopulated ? 'Aggiorna cliente' : 'Crea e associa' }}
                        </button>
                    </div>
                </div>
            </div>
        </fieldset>

        @if(!$vehSelected)
            <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mt-2">
                Seleziona un veicolo nello Step 1 per abilitare i dati cliente.
            </div>
        @endif
    @endif

    {{-- STEP 3: Bozza (generazione contratto + documenti) --}}
    @if($step===3)
        <fieldset @disabled(!$vehSelected) title="{{ $vehSelected ? '' : 'Seleziona prima un veicolo' }}">
            <div class="space-y-4">
                @if(!$rentalId)
                    <div class="rounded-md border border-amber-300 bg-amber-50 text-amber-900 px-3 py-2 text-sm">
                        Salva la bozza per ottenere l'ID noleggio e abilitare le azioni.
                    </div>
                @endif

                {{-- ✅ NEW — Prezzo finale (override) --}}
                @php
                    /** @var \App\Models\Rental|null $__rentalPrice */
                    $__rentalPrice = $rentalId ? \App\Models\Rental::find($rentalId) : null;

                    // Prezzo previsto da listino (denormalizzato su rentals.amount)
                    $predictedAmount = $__rentalPrice?->amount;

                    // Override attuale: preferisco lo state Livewire, fallback su DB
                    $overrideAmount = $rentalData['final_amount_override'] ?? $__rentalPrice?->final_amount_override ?? null;

                    $predictedLabel = is_numeric($predictedAmount)
                        ? '€ ' . number_format((float)$predictedAmount, 2, ',', '.')
                        : '—';

                    $overrideLabel = is_numeric($overrideAmount)
                        ? '€ ' . number_format((float)$overrideAmount, 2, ',', '.')
                        : '—';
                @endphp

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="text-base font-semibold">Prezzo noleggio</div>

                        <span class="text-xs rounded-full px-2 py-0.5 {{ is_numeric($overrideAmount) ? 'bg-indigo-100 text-indigo-800 ring-1 ring-indigo-300' : 'bg-gray-100 text-gray-700 ring-1 ring-gray-200' }}">
                            {{ is_numeric($overrideAmount) ? 'Override attivo' : 'Listino' }}
                        </span>
                    </div>

                    <div class="text-sm opacity-80">
                        <div>
                            Prezzo previsto da listino:
                            <span class="font-semibold">{{ $predictedLabel }}</span>
                        </div>

                        @if(is_numeric($overrideAmount))
                            <div class="mt-1">
                                Prezzo finale attuale (override):
                                <span class="font-semibold">{{ $overrideLabel }}</span>
                            </div>
                        @endif
                    </div>

                    <label class="form-control">
                        <span class="label-text mb-1 text-sm">Sovrascrivi prezzo finale (opzionale)</span>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model.defer="rentalData.final_amount_override"
                            class="{{ $input }}"
                            placeholder="Lascia vuoto per usare il prezzo da listino"
                        />

                        <div class="text-xs opacity-70 mt-1">
                            Se compili, il contratto userà questo importo al posto del listino.
                        </div>

                        @error('rentalData.final_amount_override')
                            <span class="text-red-500 text-xs">{{ $message }}</span>
                        @enderror
                    </label>
                    <div class="flex gap-2">
                        <button class="{{ $btnSoft }}"
                                wire:click="saveDraft"
                                @disabled(!$vehSelected)
                                title="{{ $vehSelected ? 'Salva bozza' : 'Seleziona prima un veicolo' }}">
                            Salva bozza
                        </button>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800 space-y-3">
                        <div class="text-base font-semibold">Contratto</div>
                        <p class="text-sm opacity-80">
                            Il contratto viene <strong>generato dal gestionale</strong>. Usa il pulsante per creare la versione PDF su <em>Rental → contract</em>.
                        </p>
                        @if($rentalId)
                            {{-- Azioni contratto --}}
                            <div class="flex items-center gap-2">
                                {{-- Genera contratto: disabilitato se esiste già un "current" --}}
                                <button type="button"
                                        wire:click="generateContract"
                                        wire:loading.attr="disabled"
                                        @disabled($currentContractMediaId)
                                        class="{{ $btnIndigo }}">
                                    <span wire:loading.remove>Genera contratto (PDF)</span>
                                    <span wire:loading>Generazione…</span>
                                </button>

                                {{-- Apri PDF se presente --}}
                                @if($currentContractUrl)
                                    <a href="{{ $currentContractUrl }}" target="_blank"
                                    class="inline-flex items-center px-3 py-1.5 bg-emerald-600 rounded-md
                                            text-xs font-semibold text-white uppercase hover:bg-emerald-500
                                            focus:outline-none focus:ring-2 focus:ring-emerald-300 transition">
                                        Apri
                                    </a>
                                    <span class="text-xs opacity-70">Ultima generazione aggiornata.</span>
                                @endif
                            </div>
                        @else
                            <button class="{{ $btnSoft }}" wire:click="saveDraft">Salva bozza per abilitare</button>
                        @endif
                    </div>

                    <div 
                        x-data="{
                            // stato locale per il bottone
                            loading: false,

                            // Submit via fetch() verso il controller: resta sulla pagina
                            async submit(e) {
                                this.loading = true;
                                const form = e.target;
                                const fd   = new FormData(form);

                                try {
                                    const res = await fetch('{{ route('rentals.media.documents.store', $rentalId ?? 0) }}', {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                        },
                                        body: fd
                                    });

                                    if (!res.ok) {
                                        let msg = 'Errore durante il caricamento.';
                                        try { const data = await res.json(); msg = data?.message ?? msg; } catch(_) {}
                                        window.dispatchEvent(new CustomEvent('toast', { detail: { type:'error', message: msg }}));
                                        return;
                                    }

                                    const data = await res.json();
                                    window.dispatchEvent(new CustomEvent('toast', {
                                        detail: { type:'success', message: data.msg || 'Documento caricato.' }
                                    }));

                                    // 🔁 REFRESH ROBUSTO del componente Livewire più vicino a questo form
                                    const host = this.$root.closest('[wire\\:id]');           // nodo radice del componente
                                    const id   = host ? host.getAttribute('wire:id') : null;  // id del componente Livewire

                                    if (id && window.Livewire && typeof window.Livewire.find === 'function') {
                                        window.Livewire.find(id).$refresh();                  // forza il render()
                                    } else if (window.$wire && typeof $wire.$refresh === 'function') {
                                        $wire.$refresh();                                     // fallback se $wire è globale
                                    }

                                    form.reset();
                                } catch (err) {
                                    window.dispatchEvent(new CustomEvent('toast', { detail: { type:'error', message: 'Errore di rete durante l’upload.' }}));
                                } finally {
                                    this.loading = false;
                                }
                            },

                            // Cancella un media lato server (DB + file) e aggiorna la lista
                            async destroy(mediaId, e) { 
                                if (!(e?.isTrusted) || e.currentTarget?.dataset.role !== 'delete-button') {
                                    return; // evita invocazioni spurie durante morph/unmount
                                }
                                if (!confirm('Eliminare questo documento?')) return;

                                try {
                                    const res = await fetch('{{ url('/media') }}/' + mediaId, {
                                        method: 'DELETE',
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',       // protezione CSRF
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                        },
                                    });

                                    if (!res.ok) {
                                        // Proviamo a leggere il messaggio dal JSON; fallback generico
                                        let msg = 'Errore durante la cancellazione.';
                                        try { const data = await res.json(); msg = data?.message ?? msg; } catch (_) {}
                                        window.dispatchEvent(new CustomEvent('toast', { detail: { type:'error', message: msg }}));
                                        return;
                                    }

                                    // Toast di conferma
                                    window.dispatchEvent(new CustomEvent('toast', {
                                        detail: { type:'success', message: 'Documento eliminato.' }
                                    }));

                                    // 🔁 Refresh robusto del componente Livewire corrente
                                    const host = this.$root.closest('[wire\\:id]');
                                    const id   = host ? host.getAttribute('wire:id') : null;

                                    if (id && window.Livewire && typeof window.Livewire.find === 'function') {
                                        window.Livewire.find(id).$refresh();
                                    } else if (window.$wire && typeof $wire.$refresh === 'function') {
                                        $wire.$refresh();
                                    }
                                } catch (err) {
                                    window.dispatchEvent(new CustomEvent('toast', {
                                        detail: { type:'error', message: 'Errore di rete durante la cancellazione.' }
                                    }));
                                }
                            }
                        }"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800 space-y-3"
                    >
                        <div class="text-base font-semibold">Documenti preliminari</div>
                        <p class="text-sm opacity-80">Termini & condizioni, privacy, preventivo, ecc. (opzionali).</p>

                        @if($rentalId)
                            {{-- Upload via fetch() per restare sulla stessa pagina --}}
                            <form
                                @submit.prevent="submit($event)"
                                class="mt-4 space-y-2"
                            >
                                @csrf
                                <div class="flex flex-wrap items-center gap-2">

                                    {{-- Scelta collection: stessi valori che già usi --}}
                                    <select name="collection"
                                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm appearance-none pr-8 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="documents">Documenti vari (precontrattuali)</option>
                                        <option value="id_card">Documento identità</option>
                                        <option value="driver_license">Patente</option>
                                        <option value="privacy">Consenso privacy</option>
                                        <option value="other">Altro</option>
                                    </select>

                                    {{-- File: il nome del campo DEVE essere "file" per il tuo controller --}}
                                    <input type="file" name="file" accept="application/pdf,image/*" class="file-input file-input-bordered file-input-sm" />

                                    <button type="submit"
                                            x-bind:disabled="loading"
                                            class="rounded bg-gray-800 px-3 py-1.5 text-white hover:bg-gray-900 disabled:opacity-50">
                                        <span x-show="!loading">Carica</span>
                                        <span x-show="loading">Caricamento…</span>
                                    </button>
                                </div>

                                <p class="text-xs opacity-70">Formati consentiti: PDF, JPG, PNG. Max 20MB.</p>
                            </form>

                            {{-- Elenco documenti: lettura diretta dal DB, si aggiorna con $wire.$refresh() --}}
                            @php
                                /** @var \App\Models\Rental|null $__rental */
                                $__rental = \App\Models\Rental::find($rentalId);
                                /** Elenco multi-collection (Spatie: uso la relazione media() per whereIn) */
                                $__docs = $__rental
                                    ? $__rental->media()
                                        ->whereIn('collection_name', ['documents','id_card','driver_license','privacy','other'])
                                        ->orderByDesc('id')
                                        ->get()
                                    : collect();
                            @endphp

                            <div class="mt-4">
                                <div class="text-sm font-semibold mb-2">Documenti salvati</div>
                                <ul class="divide-y rounded-md border border-gray-200 dark:border-gray-700">
                                    @forelse($__docs as $m)
                                        <li class="p-2 flex items-center justify-between">
                                            <div class="text-sm">
                                                <a href="{{ $m->getUrl() }}" target="_blank" class="font-medium hover:underline">
                                                    {{ $m->file_name }}
                                                </a>
                                                <div class="text-xs opacity-70">
                                                    {{ $m->collection_name }} · {{ number_format($m->size / 1024, 1) }} KB
                                                </div>
                                            </div>

                                            {{-- Azione elimina: usa la tua rotta DELETE esistente --}}
                                            <button
                                                type="button"
                                                x-on:click.prevent.stop="destroy({{ $m->id }}, $event)"
                                                data-role="delete-button"
                                                class="inline-flex items-center px-2 py-1 rounded text-xs bg-rose-600 text-white hover:bg-rose-500">
                                                Elimina
                                            </button>
                                        </li>
                                    @empty
                                        <li class="p-2 text-sm opacity-70">Nessun documento caricato.</li>
                                    @endforelse
                                </ul>
                            </div>
                        @else
                            <button class="{{ $btnSoft }}" wire:click="saveDraft">Salva bozza per abilitare</button>
                        @endif
                    </div>
                </div>

                {{-- Riepilogo minimo --}}
                <div class="text-sm opacity-80">
                    <div>Bozza ID: <span class="font-semibold">{{ $rentalId ?? '—' }}</span></div>
                    <div>Cliente: <span class="font-semibold">{{ $customer_id ? 'associato' : '—' }}</span></div>
                </div>
            </div>
        </fieldset>
        @if(!$vehSelected)
            <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mt-2">
                Seleziona un veicolo per generare contratto e caricare documenti.
            </div>
        @endif
    @endif

    {{-- Footer azioni --}}
    <div class="flex items-center justify-between">
        @if($step!==3)
        <div class="flex gap-2">
            <button class="{{ $btnSoft }}"
                    wire:click="saveDraft"
                    @disabled(!$vehSelected)
                    title="{{ $vehSelected ? 'Salva bozza' : 'Seleziona prima un veicolo' }}">
                Salva bozza
            </button>
        </div>
        @endif
        <div class="flex gap-2">
            @if($step > 1)
                <button class="{{ $btnSoft }}" wire:click="prev">Indietro</button>
            @endif

            @if($step < 3)
                <button class="{{ $btnIndigo }}"
                        wire:click="next"
                        @disabled(!$vehSelected)
                        title="{{ $vehSelected ? 'Prosegui' : 'Seleziona prima un veicolo' }}">
                    Avanti
                </button>
            @else
                <button class="{{ $btnIndigo }} {{ $rentalId ? '' : 'opacity-60 cursor-not-allowed' }}"
                        wire:click="finish"
                        @disabled(!$vehSelected || !$rentalId)
                        aria-disabled="{{ ($vehSelected && $rentalId) ? 'false' : 'true' }}"
                        title="{{ ($vehSelected && $rentalId) ? 'Vai al dettaglio' : 'Seleziona veicolo e salva bozza' }}">
                    Vai al dettaglio
                </button>
            @endif
        </div>
    </div>

</div>
