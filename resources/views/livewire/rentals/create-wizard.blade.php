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
            <span>Bozza (contratto + documenti)</span>
        </div>
    </div>

    {{-- STEP 1: Dati noleggio --}}
    @if($step===1)
    <div class="grid md:grid-cols-2 gap-4">
        {{-- Vehicle --}}
        <label class="form-control">
            <span class="label-text">Veicolo</span>
            <select wire:model.defer="rentalData.vehicle_id" class="select select-bordered">
                <option value="">—</option>
                @foreach($vehicles as $v)
                    <option value="{{ $v['id'] }}">{{ $v['plate'] ?? $v['make'] . ' ' . $v['model'] ?? ('#'.$v['id']) }}</option>
                @endforeach
            </select>
            @error('rentalData.vehicle_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        {{-- Pickup location --}}
        <label class="form-control">
            <span class="label-text">Sede ritiro</span>
            <input type="number" wire:model.defer="rentalData.pickup_location_id" class="input input-bordered" placeholder="ID sede" />
            @error('rentalData.pickup_location_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        {{-- Return location --}}
        <label class="form-control">
            <span class="label-text">Sede riconsegna</span>
            <input type="number" wire:model.defer="rentalData.return_location_id" class="input input-bordered" placeholder="ID sede" />
            @error('rentalData.return_location_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        {{-- Planned pickup --}}
        <label class="form-control">
            <span class="label-text">Ritiro pianificato</span>
            <input type="datetime-local" wire:model.defer="rentalData.planned_pickup_at" class="input input-bordered" />
            @error('rentalData.planned_pickup_at') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        {{-- Planned return --}}
        <label class="form-control">
            <span class="label-text">Riconsegna pianificata</span>
            <input type="datetime-local" wire:model.defer="rentalData.planned_return_at" class="input input-bordered" />
            @error('rentalData.planned_return_at') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>

        {{-- Notes --}}
        <label class="form-control md:col-span-2">
            <span class="label-text">Note</span>
            <textarea wire:model.defer="rentalData.notes" rows="3" class="textarea textarea-bordered" placeholder="Note operative…"></textarea>
            @error('rentalData.notes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </label>
    </div>
    @endif

    {{-- STEP 2: Cliente --}}
    @if($step===2)
    <div class="grid md:grid-cols-2 gap-6">
        {{-- Ricerca e associazione --}}
        <div class="space-y-3">
            <div class="text-sm font-semibold">Cerca cliente esistente</div>
            <input type="text" wire:model.live.debounce.300ms="customerQuery" class="input input-bordered w-full" placeholder="Nome o n. documento…" />
            <div class="text-xs opacity-60">Min. 2 caratteri</div>

            <div class="divide-y rounded border">
                @forelse($customers as $c)
                    <div class="flex items-center justify-between p-2">
                        <div class="text-sm">
                            <div class="font-medium">{{ $c['name'] }}</div>
                            <div class="opacity-70">Doc: {{ $c['doc_id_number'] }}</div>
                        </div>
                        <button wire:click="selectCustomer({{ $c['id'] }})" class="btn btn-xs btn-primary">Seleziona</button>
                    </div>
                @empty
                    <div class="p-3 text-sm opacity-70">Nessun risultato</div>
                @endforelse
            </div>
        </div>

        {{-- Creazione rapida --}}
        <div class="space-y-3">
            <div class="text-sm font-semibold">Crea nuovo cliente</div>
            <div class="grid grid-cols-1 gap-3">
                <label class="form-control">
                    <span class="label-text">Nome completo *</span>
                    <input type="text" wire:model.defer="customerForm.name" class="input input-bordered" />
                    @error('customerForm.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </label>
                <label class="form-control">
                    <span class="label-text">Email</span>
                    <input type="email" wire:model.defer="customerForm.email" class="input input-bordered" />
                    @error('customerForm.email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </label>
                <label class="form-control">
                    <span class="label-text">Telefono</span>
                    <input type="text" wire:model.defer="customerForm.phone" class="input input-bordered" />
                    @error('customerForm.phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="form-control">
                        <span class="label-text">Tipo documento</span>
                        <input type="text" wire:model.defer="customerForm.doc_id_type" class="input input-bordered" placeholder="es. ID, patente…" />
                        @error('customerForm.doc_id_type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                    <label class="form-control">
                        <span class="label-text">Numero documento *</span>
                        <input type="text" wire:model.defer="customerForm.doc_id_number" class="input input-bordered" />
                        @error('customerForm.doc_id_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </label>
                </div>
                <div class="flex justify-end">
                    <button wire:click="createCustomer" class="btn btn-success">Crea e associa</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- STEP 3: Bozza (contratto + documenti preliminari) --}}
    @if($step===3)
        <div class="space-y-4">
            @if(!$rentalId)
                <div class="alert alert-warning text-sm">
                    Salva la bozza per ottenere l'ID noleggio e abilitare gli upload.
                </div>
            @endif

            <div class="grid md:grid-cols-2 gap-4">
                <div class="card shadow">
                    <div class="card-body space-y-3">
                        <div class="card-title">Contratto (generato dal gestionale)</div>
                        <p class="text-sm opacity-80">
                            Carica il PDF del contratto generato (versioning abilitato). In fase di Checkout caricherai il **firmato**.
                        </p>

                        @if($rentalId)
                            <x-media-uploader
                                label="Contratto (PDF)"
                                :action="route('rentals.media.contract.store', $rentalId)"
                                accept="application/pdf"
                            />
                        @else
                            <button class="btn" wire:click="saveDraft">Salva bozza per abilitare</button>
                        @endif
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-body space-y-3">
                        <div class="card-title">Documenti preliminari</div>
                        <p class="text-sm opacity-80">Termini & condizioni, privacy, preventivo, ecc.</p>

                        @if($rentalId)
                            <x-media-uploader
                                label="Aggiungi documento"
                                :action="route('rentals.media.documents.store', $rentalId)"
                                accept="application/pdf,image/*"
                                multiple
                            />
                        @else
                            <button class="btn" wire:click="saveDraft">Salva bozza per abilitare</button>
                        @endif
                    </div>
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
            <button class="btn" wire:click="saveDraft">Salva bozza</button>
        </div>
        <div class="flex gap-2">
            @if($step > 1)
                <button class="btn btn-ghost" wire:click="prev">Indietro</button>
            @endif
            @if($step < 3)
                <button class="btn btn-primary" wire:click="next">Avanti</button>
            @else
                <button class="btn btn-success" wire:click="finish">
                    Vai al dettaglio
                </button>
            @endif
        </div>
    </div>
</div>
