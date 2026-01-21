{{-- resources/views/pages/rentals/partials/tab-data.blade.php --}}
{{-- Scheda "Dati" — Restyling tipografico e gerarchia visiva coerente con DaisyUI --}}
@php
    // Mappa classi badge per lo stato (non cambiamo i valori in DB)
    $statusBadgeClass = [
        'draft'       => 'badge-ghost',
        'reserved'    => 'badge-info',
        'checked_out' => 'badge-warning',
        'in_use'      => 'badge-warning',
        'checked_in'  => 'badge-accent',
        'closed'      => 'badge-success',
        'canceled'    => 'badge-error',
        'cancelled'   => 'badge-error',
        'no_show'     => 'badge-error',
    ][$rental->status] ?? 'badge-ghost';

    $statusLabel = [
        'draft'       => 'Bozza',
        'reserved'    => 'Prenotato',
        'in_use'      => 'In uso',
        'checked_in'  => 'Rientrato',
        'closed'      => 'Chiuso',
        'cancelled'   => 'Annullato',

        // ♻️ compat/legacy
        'canceled'    => 'Annullato',
        'no_show'     => 'Annullato',
        'checked_out' => 'In uso',
    ][$rental->status] ?? str_replace('_',' ', $rental->status);


    // Valori rapidi per la colonna destra (uguali a prima, solo ripuliti)
    $pickup   = $rental->checklists->firstWhere('type','pickup');
    $return   = $rental->checklists->firstWhere('type','return');
    $hasCtr   = method_exists($rental, 'getMedia') ? $rental->getMedia('contract')->isNotEmpty()    : false;
    $hasSign  = method_exists($rental, 'getMedia') ? $rental->getMedia('signatures')->isNotEmpty()  : false;
    $hasSignC = ($pickup && method_exists($pickup,'getMedia')) ? $pickup->getMedia('signatures')->isNotEmpty() : false;
    $photosPU = ($pickup && method_exists($pickup,'getMedia')) ? $pickup->getMedia('photos')->count() : 0;
    $photosRT = ($return && method_exists($return,'getMedia')) ? $return->getMedia('photos')->count() : 0;
    $dmgCount = $rental->damages->count();
    $dmgNoPic = $rental->damages->filter(fn($d)=>$d->getMedia('photos')->isEmpty())->count();
@endphp

<div class="grid lg:grid-cols-3 gap-6">
    {{-- Colonna 1-2: Dati contratto --}}
    <div class="card shadow lg:col-span-2">
        <div class="card-body space-y-5">
            <div class="flex items-center justify-between">
                <div class="card-title">Dati contratto</div>
                <span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
            </div>

            {{-- Layout semantico con dl/dt/dd, tipografia chiara e spaziatura coerente --}}
            <dl class="grid md:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                <div>
                    <dt class="opacity-70">Riferimento</dt>
                    <dd class="font-medium">{{ $rental->reference ?? $rental->display_number_label }}</dd>
                </div>

                <div class="col-span-2">
                    <dt class="opacity-70">Cliente</dt>
                    <dd class="font-medium flex items-center justify-between gap-2">
                        <span>{{ optional($rental->customer)->name ?? '—' }}</span>

                        @if(in_array($rental->status, ['draft','reserved'], true))
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="btn btn-xs shadow-none
                                            !bg-neutral !text-neutral-content !border-neutral
                                            hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30
                                            disabled:opacity-50 disabled:cursor-not-allowed p-2"
                                    wire:click="openCustomerModal('primary')"
                                >
                                    {{ empty($rental->customer_id) ? 'Aggiungi Cliente' : 'Modifica Cliente' }}
                                </button>

                                @if(!empty($rental->customer_id))
                                    <button
                                        type="button"
                                        class="btn btn-xs shadow-none
                                            !bg-rose-600 !text-white !border-rose-600
                                            hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-rose-300/40
                                            disabled:opacity-50 disabled:cursor-not-allowed p-2"
                                        onclick="confirm('Rimuovere il cliente dal noleggio?') || event.stopImmediatePropagation()"
                                        wire:click="detachPrimaryCustomer"
                                    >
                                        Rimuovi
                                    </button>
                                @endif
                            </div>
                        @endif
                    </dd>
                </div>

                @if(!empty($rental->customer_id))
                    <div class="col-span-2">
                        <dt class="opacity-70">Seconda guida</dt>
                        <dd class="font-medium flex items-center justify-between gap-2">
                            <span>{{ optional($rental->secondDriver)->name ?? '—' }}</span>

                            @if(in_array($rental->status, ['draft','reserved'], true))
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-xs shadow-none
                                                !bg-neutral !text-neutral-content !border-neutral
                                                hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30
                                                disabled:opacity-50 disabled:cursor-not-allowed p-2"
                                        wire:click="openCustomerModal('second')"
                                    >
                                        {{ optional($rental->secondDriver)->id ? 'Modifica seconda guida' : 'Aggiungi seconda guida' }}
                                    </button>

                                    @if(optional($rental->secondDriver)->id)
                                        <button
                                            type="button"
                                            class="btn btn-xs shadow-none
                                                !bg-rose-600 !text-white !border-rose-600
                                                hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-rose-300/40
                                                disabled:opacity-50 disabled:cursor-not-allowed p-2"
                                            onclick="confirm('Rimuovere la seconda guida dal noleggio?') || event.stopImmediatePropagation()"
                                            wire:click="detachSecondDriver"
                                        >
                                            Rimuovi
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </dd>
                    </div>
                @endif

                <div>
                    <dt class="opacity-70">Veicolo</dt>
                    <dd class="font-medium">
                        {{ optional($rental->vehicle)->plate ?? optional($rental->vehicle)->name ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="opacity-70">Organizzazione</dt>
                    <dd class="font-medium">{{ optional($rental->organization)->name ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Pickup (pianificato)</dt>
                    <dd class="font-medium">{{ optional($rental->planned_pickup_at)->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Return (pianificato)</dt>
                    <dd class="font-medium">{{ optional($rental->planned_return_at)->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Pickup (effettivo)</dt>
                    <dd class="font-medium">{{ optional($rental->actual_pickup_at)->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Return (effettivo)</dt>
                    <dd class="font-medium">{{ optional($rental->actual_return_at)->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Sede ritiro</dt>
                    <dd class="font-medium">{{ optional($rental->pickupLocation)->name ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Sede riconsegna</dt>
                    <dd class="font-medium">{{ optional($rental->returnLocation)->name ?? '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Copertura RCA</dt>
                    <dd class="font-medium">{{ $rental->coverage->rca ? 'Sì' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="opacity-70">Franchigia RCA</dt>
                    <dd class="font-medium">{{ $rental->coverage->rca ? ($rental->coverage->franchise_rca ?? $rental->vehicle->insurance_rca_cents/100) . ' €' : '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Copertura Kasko</dt>
                    <dd class="font-medium">{{ $rental->coverage->kasko ? 'Sì' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="opacity-70">Franchigia Kasko</dt>
                    <dd class="font-medium">{{ $rental->coverage->kasko ? ($rental->coverage->franchise_kasko ?? $rental->vehicle->insurance_kasko_cents/100) . ' €' : '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Copertura Furto e Incendio</dt>
                    <dd class="font-medium">{{ $rental->coverage->furto_incendio ? 'Sì' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="opacity-70">Franchigia Furto e Incendio</dt>
                    <dd class="font-medium">{{ $rental->coverage->furto_incendio ? ($rental->coverage->franchise_furto_incendio ?? $rental->vehicle->insurance_furto_cents/100) . ' €' : '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Copertura Cristalli</dt>
                    <dd class="font-medium">{{ $rental->coverage->cristalli ? 'Sì' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="opacity-70">Franchigia Cristalli</dt>
                    <dd class="font-medium">{{ $rental->coverage->cristalli ? ($rental->coverage->franchise_cristalli ?? $rental->vehicle->insurance_cristalli_cents/100) . ' €' : '—' }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Copertura Assistenza</dt>
                    <dd class="font-medium">{{ $rental->coverage->assistenza ? 'Sì' : 'No' }}</dd>
                </div>

            </dl>

            {{-- Note operative (tipografia migliorata) --}}
            @if(!empty($rental->notes))
                <div class="divider my-2"></div>
                <div>
                    <div class="opacity-70 text-sm mb-1">Note</div>
                    <div class="prose prose-sm max-w-none">{{ $rental->notes }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Colonna 3: Stato documentale (stile più leggibile, icone + badge coerenti) --}}
    <div class="card shadow">
        <div class="card-body space-y-4">
            <div class="card-title">Stato documentale</div>

            <ul class="space-y-2 text-sm">
                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span>📄</span> Contratto generato</span>
                    <span class="badge {{ $hasCtr ? 'badge-success' : 'badge-outline' }}">{{ $hasCtr ? 'Presente' : 'Mancante' }}</span>
                </li>

                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span>✍️</span> Contratto firmato (Rental → signatures)</span>
                    <span class="badge {{ $hasSign ? 'badge-success' : 'badge-outline' }}">{{ $hasSign ? 'Presente' : 'Mancante' }}</span>
                </li>

                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span>✍️</span> Contratto firmato (Checklist pickup → signatures)</span>
                    <span class="badge {{ $hasSignC ? 'badge-success' : 'badge-outline' }}">{{ $hasSignC ? 'Presente' : 'Mancante' }}</span>
                </li>

                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span>✅</span> Checklist pickup</span>
                    <span class="badge {{ $pickup ? 'badge-success' : 'badge-outline' }}">{{ $pickup ? 'OK' : 'Mancante' }}</span>
                </li>

                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span>🖼️</span> Foto pickup</span>
                    <span class="badge {{ $photosPU>0 ? 'badge-success' : 'badge-outline' }}">{{ $photosPU }} foto</span>
                </li>

                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span>✅</span> Checklist return</span>
                    <span class="badge {{ $return ? 'badge-success' : 'badge-outline' }}">{{ $return ? 'OK' : 'Mancante' }}</span>
                </li>

                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span>🖼️</span> Foto return</span>
                    <span class="badge {{ $photosRT>0 ? 'badge-success' : 'badge-outline' }}">{{ $photosRT }} foto</span>
                </li>

                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><span>⚠️</span> Danni registrati</span>
                    <span class="badge {{ $dmgCount>0 ? 'badge-warning' : 'badge-outline' }}">{{ $dmgCount }}</span>
                </li>

                @if($dmgCount>0)
                    <li class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span>📷</span> Danni senza foto</span>
                        <span class="badge {{ $dmgNoPic===0 ? 'badge-success' : 'badge-error' }}">{{ $dmgNoPic }}</span>
                    </li>
                @endif

                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <span>💳</span> Pagamento base registrato
                    </span>
                    <span class="badge {{ $rental->has_base_payment ? 'badge-success' : 'badge-outline' }}">
                        {{ $rental->base_payment_at ? $rental->base_payment_at->format('d/m/Y') : 'No' }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
    
    {{-- ===========================
     MODALE: Aggiungi / Cambia Cliente
     - Ricerca cliente esistente (prefill form)
     - Oppure crea nuovo e associa al rental
    =========================== --}}
    @if($this->customerModalOpen)
        @php
            // Classi UI locali (evitiamo dipendenze da variabili non definite in questa view)
            $input = 'block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                    focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400
                    dark:bg-gray-800 dark:border-gray-700';

            // Filled primary (come "Genera contratto")
            $btnPrimary = 'btn btn-primary btn-sm shadow-none
                        !bg-primary !text-primary-content !border-primary
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-primary/30
                        disabled:opacity-50 disabled:cursor-not-allowed p-2';

            // Filled neutral (come "Apri" in tab-contract)
            $btnNeutral = 'btn btn-sm shadow-none
                        !bg-neutral !text-neutral-content !border-neutral
                        hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30
                        disabled:opacity-50 disabled:cursor-not-allowed p-2';

            // Neutral XS per bottoni piccoli
            $btnNeutralXs = 'btn btn-xs shadow-none
                            !bg-neutral !text-neutral-content !border-neutral
                            hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30
                            disabled:opacity-50 disabled:cursor-not-allowed p-2';
                            
            $btnIndigo = 'btn btn-sm shadow-none
                !bg-indigo-600 !text-white !border-indigo-600
                hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-indigo-300/40
                disabled:opacity-50 disabled:cursor-not-allowed p-2';

            // Ghost (come X nel payment modal)
            $btnGhostXs = 'btn btn-ghost btn-xs';
        @endphp

        <div class="modal modal-open z-[96]">
            {{-- Click sul backdrop = chiudi --}}
            <div class="modal-backdrop" wire:click="closeCustomerModal"></div>

            <div class="modal-box max-w-5xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        @php
                            /**
                            * Titolo coerente con il contesto del modale:
                            * - primary: cliente principale
                            * - second : seconda guida
                            *
                            * Nota: $this->customerRole viene impostato in openCustomerModal().
                            */
                            $isSecond = ($this->customerRole ?? 'primary') === 'second';

                            // Determina se esiste già un record collegato al rental per il ruolo corrente.
                            $hasLinked = $isSecond
                                ? !empty($rental->secondDriver)
                                : !empty($rental->customer);

                            $title = $isSecond
                                ? ($hasLinked ? 'Modifica seconda guida' : 'Aggiungi seconda guida')
                                : ($hasLinked ? 'Modifica cliente' : 'Aggiungi cliente');
                        @endphp

                        <h3 class="text-lg font-semibold">
                            {{ $title }}
                        </h3>
                        <p class="text-sm opacity-70">
                            Seleziona un cliente esistente per precompilare i dati, oppure creane uno nuovo.
                        </p>
                    </div>

                    <button type="button" class="btn btn-ghost btn-sm" wire:click="closeCustomerModal">✕</button>
                </div>

                <div class="mt-5 grid md:grid-cols-2 gap-6">
                    {{-- Colonna sinistra: ricerca e selezione --}}
                    <div class="space-y-3">
                        <div class="text-sm font-semibold">Cerca cliente esistente</div>

                        <input
                            type="text"
                            wire:model.live.debounce.300ms="customerQuery"
                            class="{{ $input }}"
                            placeholder="Nome o n. documento…"
                        />

                        <div class="text-xs opacity-60">Min. 2 caratteri</div>

                        <div class="divide-y rounded-md border border-base-300">
                            @forelse($this->customerSearchResults as $c)
                                <div class="flex items-center justify-between p-2">
                                    <div class="text-sm">
                                        <div class="font-medium">{{ $c['name'] }}</div>
                                        <div class="opacity-70">Doc: {{ $c['doc_id_number'] }}</div>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="selectCustomer({{ $c['id'] }})"
                                        class="{{ $btnNeutralXs }}"
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
    COLONNA DX — Form cliente (stile Rentals/Wizard)
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

            // Campo canonico per backend/validazione
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
            {{ $this->customerPopulated ? 'Cliente selezionato (modificabile)' : 'Crea nuovo cliente' }}
        </div>

        @if($this->customerPopulated && $this->customer_id)
            <span class="text-xs rounded-full px-2 py-0.5 bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300">
                ID #{{ $this->customer_id }}
            </span>
        @endif
    </div>

    {{-- Campo nascosto: mantiene customerForm.name popolato --}}
    <input type="hidden" wire:model.defer="customerForm.name" />
    @error('customerForm.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

    {{-- Wrappo tutto in un form per supportare Enter --}}
    <form wire:submit.prevent="createOrUpdateCustomer" class="space-y-10">

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
                    wire:key="show-{{ $this->customerRole }}-birth-{{ $this->customer_id ?? 'new' }}"
                />
                @error('customerForm.birth_place_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                <livewire:shared.cargos-luogo-picker
                    wire:model="customerForm.citizenship_place_code"
                    title="Cittadinanza"
                    hint="Seleziona solo la nazione"
                    mode="country-only"
                    wire:key="show-{{ $this->customerRole }}-cit-{{ $this->customer_id ?? 'new' }}"
                />
                @error('customerForm.citizenship_place_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        </section>

        {{-- ======================================================
            2. DOCUMENTI
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
                        wire:key="show-{{ $this->customerRole }}-doc-type-{{ $this->customer_id ?? 'new' }}"
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
                        wire:key="show-{{ $this->customerRole }}-doc-place-{{ $this->customer_id ?? 'new' }}"
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
                        wire:key="show-{{ $this->customerRole }}-dl-place-{{ $this->customer_id ?? 'new' }}"
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
                    wire:key="show-{{ $this->customerRole }}-res-{{ $this->customer_id ?? 'new' }}"
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

        {{-- AZIONI --}}
        <div class="flex justify-end gap-2">
            <button type="button" class="{{ $btnNeutral }}" wire:click="closeCustomerModal">
                Annulla
            </button>

            <button type="submit" class="{{ $btnIndigo }}" wire:loading.attr="disabled">
                {{ $this->customerPopulated ? 'Aggiorna cliente' : 'Crea e associa' }}
            </button>
        </div>
    </form>
</div>
                </div>
            </div>
        </div>
    @endif

</div>
