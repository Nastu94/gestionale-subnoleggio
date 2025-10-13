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
    <div class="grid md:grid-cols-2 gap-4">
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
    </div>
    @endif

    {{-- STEP 2: Cliente --}}
    @if($step===2)
    <div class="grid md:grid-cols-2 gap-6">
        {{-- Ricerca e selezione --}}
        <div class="space-y-3">
            <div class="text-sm font-semibold">Cerca cliente esistente</div>
            <input type="text" wire:model.live.debounce.300ms="customerQuery" class="{{ $input }}" placeholder="Nome o n. documento…" />
            <div class="text-xs opacity-60">Min. 2 caratteri</div>

            <div class="divide-y rounded-md border border-gray-200 dark:border-gray-700">
                @forelse($customers as $c)
                    <div class="flex items-center justify-between p-2">
                        <div class="text-sm">
                            <div class="font-medium">{{ $c['name'] }}</div>
                            <div class="opacity-70">Doc: {{ $c['doc_id_number'] }}</div>
                        </div>
                        <button wire:click="selectCustomer({{ $c['id'] }})" class="{{ $btnIndigo }}">Seleziona</button>
                    </div>
                @empty
                    <div class="p-3 text-sm opacity-70">Nessun risultato</div>
                @endforelse
            </div>
        </div>

        {{-- Form cliente (popolato se selezionato; usabile anche per creare) --}}
        <div class="space-y-3">
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

            <div class="grid grid-cols-1 gap-3">
                <label>
                    <span class="block mb-1 text-sm">Nome completo *</span>
                    <input type="text" wire:model.defer="customerForm.name" class="{{ $input }}" />
                    @error('customerForm.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </label>

                <div class="grid md:grid-cols-2 gap-3">
                    <label>
                        <span class="block mb-1 text-sm">Email</span>
                        <input type="email" wire:model.defer="customerForm.email" class="{{ $input }}" />
                        @error('customerForm.email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        <span class="block mb-1 text-sm">Telefono</span>
                        <input type="text" wire:model.defer="customerForm.phone" class="{{ $input }}" />
                        @error('customerForm.phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="grid md:grid-cols-2 gap-3">
                    <label>
                        <span class="block mb-1 text-sm">Tipo documento</span>
                        <input type="text" wire:model.defer="customerForm.doc_id_type" class="{{ $input }}" placeholder="es. patente, CI…" />
                        @error('customerForm.doc_id_type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        <span class="block mb-1 text-sm">Numero documento *</span>
                        <input type="text" wire:model.defer="customerForm.doc_id_number" class="{{ $input }}" />
                        @error('customerForm.doc_id_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="grid md:grid-cols-2 gap-3">
                    <label>
                        <span class="block mb-1 text-sm">Data di nascita</span>
                        <input type="date" wire:model.defer="customerForm.birth_date" class="{{ $input }}" />
                        @error('customerForm.birth_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                    <div></div>
                </div>

                <label>
                    <span class="block mb-1 text-sm">Indirizzo</span>
                    <input type="text" wire:model.defer="customerForm.address" class="{{ $input }}" />
                </label>

                <div class="grid md:grid-cols-4 gap-3">
                    <label class="md:col-span-2">
                        <span class="block mb-1 text-sm">Città</span>
                        <input type="text" wire:model.defer="customerForm.city" class="{{ $input }}" />
                    </label>
                    <label>
                        <span class="block mb-1 text-sm">Provincia</span>
                        <input type="text" wire:model.defer="customerForm.province" class="{{ $input }}" />
                    </label>
                    <label>
                        <span class="block mb-1 text-sm">CAP</span>
                        <input type="text" wire:model.defer="customerForm.zip" class="{{ $input }}" />
                    </label>
                </div>

                <label class="md:w-40">
                    <span class="block mb-1 text-sm">Nazione (ISO-2)</span>
                    <input type="text" wire:model.defer="customerForm.country_code" class="{{ $input }}" placeholder="IT, FR, …" />
                </label>

                <div class="flex justify-end gap-2">
                    <button wire:click="createOrUpdateCustomer" class="{{ $btnIndigo }}">
                        {{ $customerPopulated ? 'Aggiorna cliente' : 'Crea e associa' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- STEP 3: Bozza (generazione contratto + documenti) --}}
    @if($step===3)
        <div class="space-y-4">
            @if(!$rentalId)
                <div class="rounded-md border border-amber-300 bg-amber-50 text-amber-900 px-3 py-2 text-sm">
                    Salva la bozza per ottenere l'ID noleggio e abilitare le azioni.
                </div>
            @endif

            <div class="grid md:grid-cols-2 gap-4">
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800 space-y-3">
                    <div class="text-base font-semibold">Contratto</div>
                    <p class="text-sm opacity-80">
                        Il contratto viene <strong>generato dal gestionale</strong>. Usa il pulsante per creare la versione PDF su <em>Rental → contract</em>.
                    </p>

                    @if($rentalId)
                        {{-- Per ora solo il pulsante. Collegheremo la rotta all’azione di generazione. --}}
                        <form method="POST" action="{{ route('rentals.contract.generate', $rentalId) }}">
                            @csrf
                            <button type="submit" class="{{ $btnIndigo }}">
                                Genera contratto (PDF)
                            </button>
                        </form>
                    @else
                        <button class="{{ $btnSoft }}" wire:click="saveDraft">Salva bozza per abilitare</button>
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800 space-y-3">
                    <div class="text-base font-semibold">Documenti preliminari</div>
                    <p class="text-sm opacity-80">Termini & condizioni, privacy, preventivo, ecc. (opzionali).</p>

                    @if($rentalId)
                        <x-media-uploader
                            label="Aggiungi documento"
                            :action="route('rentals.media.documents.store', $rentalId)"
                            accept="application/pdf,image/*"
                            multiple
                        />
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
    @endif

    {{-- Footer azioni --}}
    <div class="flex items-center justify-between">
        <div class="flex gap-2">
            <button class="{{ $btnSoft }}" wire:click="saveDraft">Salva bozza</button>
        </div>
        <div class="flex gap-2">
            @if($step > 1)
                <button class="{{ $btnSoft }}" wire:click="prev">Indietro</button>
            @endif
            @if($step < 3)
                <button class="{{ $btnIndigo }}" wire:click="next">Avanti</button>
            @else
                <button
                    class="{{ $btnIndigo }} {{ $rentalId ? '' : 'opacity-60 cursor-not-allowed' }}"
                    wire:click="finish"
                    @disabled(!$rentalId)
                    aria-disabled="{{ $rentalId ? 'false' : 'true' }}"
                    title="{{ $rentalId ? 'Vai al dettaglio' : 'Salva la bozza per abilitare' }}"
                >
                    Vai al dettaglio
                </button>
            @endif
        </div>
    </div>
</div>
