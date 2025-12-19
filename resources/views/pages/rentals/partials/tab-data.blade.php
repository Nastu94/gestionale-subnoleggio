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
                    <dd class="font-medium">{{ $rental->reference ?? ('#'.$rental->id) }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Cliente</dt>

                    {{-- Nome cliente + azione rapida (Aggiungi / Cambia) --}}
                    <dd class="font-medium flex items-center justify-between gap-2">
                        <span>{{ optional($rental->customer)->name ?? '—' }}</span>


                        @if(in_array($rental->status, ['draft','reserved'], true))
                            <button
                                type="button"
                                class="btn btn-xs shadow-none
                                    !bg-neutral !text-neutral-content !border-neutral
                                    hover:brightness-95 focus-visible:outline-none focus-visible:ring focus-visible:ring-neutral/30
                                    disabled:opacity-50 disabled:cursor-not-allowed p-2"
                                wire:click="openCustomerModal(false)"
                            >
                                {{ $rental->customer ? 'Cambia Cliente' : 'Aggiungi Cliente' }}
                            </button>
                        @endif
                    </dd>
                </div>

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

            // Ghost (come X nel payment modal)
            $btnGhostXs = 'btn btn-ghost btn-xs';
        @endphp

        <div class="modal modal-open z-[96]">
            {{-- Click sul backdrop = chiudi --}}
            <div class="modal-backdrop" wire:click="closeCustomerModal"></div>

            <div class="modal-box max-w-5xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold">
                            {{ $rental->customer ? 'Cambia Cliente' : 'Aggiungi Cliente' }}
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

                    {{-- Colonna destra: form cliente --}}
                    <div class="space-y-3">
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

                        {{-- Usiamo submit per supportare Enter, ma non ricarichiamo la pagina --}}
                        <form wire:submit.prevent="createOrUpdateCustomer" class="space-y-3">
                            <label class="block">
                                <span class="block mb-1 text-sm">Nome completo *</span>
                                <input type="text" wire:model.defer="customerForm.name" class="{{ $input }}" />
                                @error('customerForm.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </label>

                            <div class="grid md:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block mb-1 text-sm">Email</span>
                                    <input type="email" wire:model.defer="customerForm.email" class="{{ $input }}" />
                                    @error('customerForm.email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </label>

                                <label class="block">
                                    <span class="block mb-1 text-sm">Telefono</span>
                                    <input type="text" wire:model.defer="customerForm.phone" class="{{ $input }}" />
                                    @error('customerForm.phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </label>
                            </div>

                            <div class="grid md:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block mb-1 text-sm">Tipo Documento d'identità *</span>
                                    <select wire:model.defer="customerForm.doc_id_type" class="{{ $input }}">
                                        <option value="">— Seleziona —</option>
                                        <option value="id">Carta d'identità</option>
                                        <option value="passport">Passaporto</option>
                                    </select>
                                    @error('customerForm.doc_id_type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </label>

                                <label class="block">
                                    <span class="block mb-1 text-sm">Numero documento d'identità *</span>
                                    <input type="text" wire:model.defer="customerForm.doc_id_number" class="{{ $input }}" />
                                    @error('customerForm.doc_id_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </label>
                            </div>

                            <div class="grid md:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block mb-1 text-sm">Numero Patente</span>
                                    <input type="text" wire:model.defer="customerForm.driver_license_number" class="{{ $input }}" />
                                    @error('customerForm.driver_license_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </label>

                                <label class="block">
                                    <span class="block mb-1 text-sm">Scadenza Patente</span>
                                    <input type="date" wire:model.defer="customerForm.driver_license_expires_at" class="{{ $input }}" />
                                    @error('customerForm.driver_license_expires_at') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </label>
                            </div>

                            <div class="grid md:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block mb-1 text-sm">Data di nascita</span>
                                    <input type="date" wire:model.defer="customerForm.birth_date" class="{{ $input }}" />
                                    @error('customerForm.birth_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </label>
                                <div></div>
                            </div>

                            <label class="block">
                                <span class="block mb-1 text-sm">Indirizzo</span>
                                <input type="text" wire:model.defer="customerForm.address" class="{{ $input }}" />
                            </label>

                            <div class="grid md:grid-cols-4 gap-3">
                                <label class="block md:col-span-2">
                                    <span class="block mb-1 text-sm">Città</span>
                                    <input type="text" wire:model.defer="customerForm.city" class="{{ $input }}" />
                                </label>

                                <label class="block">
                                    <span class="block mb-1 text-sm">Provincia</span>
                                    <input type="text" wire:model.defer="customerForm.province" class="{{ $input }}" />
                                </label>

                                <label class="block">
                                    <span class="block mb-1 text-sm">CAP</span>
                                    <input type="text" wire:model.defer="customerForm.zip" class="{{ $input }}" />
                                </label>
                            </div>

                            <label class="block md:w-40">
                                <span class="block mb-1 text-sm">Nazione (ISO-2)</span>
                                <input type="text" wire:model.defer="customerForm.country_code" class="{{ $input }}" placeholder="IT, FR, …" />
                            </label>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" class="{{ $btnNeutral }}" wire:click="closeCustomerModal">
                                    Annulla
                                </button>

                                <button type="submit" class="{{ $btnPrimary }}" wire:loading.attr="disabled">
                                    {{ $this->customerPopulated ? 'Aggiorna e associa' : 'Crea e associa' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
