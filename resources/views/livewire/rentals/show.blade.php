{{-- resources/views/livewire/rentals/show.blade.php --}}
<div class="grid lg:grid-cols-12 gap-6">
    {{-- Colonna principale --}}
    <div class="lg:col-span-9 space-y-6">
        {{-- HEADER + TABS (sticky) — drop-in replacement --}}
        @php
            /**
            * Mappa colore badge per stato noleggio.
            * Non cambiamo il valore di $rental->status; mappiamo solo una classe visiva coerente con DaisyUI.
            */
            $statusBadgeClass = [
                'draft'       => 'badge-ghost',
                'reserved'    => 'badge-info',
                'checked_out' => 'badge-warning',
                'in_use'      => 'badge-warning',
                'checked_in'  => 'badge-accent',
                'closed'      => 'badge-success',
                'canceled'    => 'badge-error',
                'no_show'     => 'badge-error',
            ][$rental->status] ?? 'badge-ghost';

            /**
            * Contatori leggeri per micro-indicatori nelle tab.
            * Usiamo solo dati già presenti su $rental e relative relazioni.
            * NB: tutti gli "optional()" evitano errori se alcune relazioni non sono caricate.
            */
            $docCollections = ['documents','id_card','driver_license','privacy','other'];

            $attachmentsCount = $rental->media()->whereIn('collection_name', $docCollections)->count();
            $contractGenerated  = method_exists($rental,'getMedia') ? $rental->getMedia('contract')->count()  : 0;
            $contractSigned     = method_exists($rental,'getMedia') ? $rental->getMedia('signatures')->count(): 0;
            $damagesCount       = $rental->damages?->count() ?? 0;

            $pickupChecklist    = $rental->checklists?->firstWhere('type','pickup');
            $returnChecklist    = $rental->checklists?->firstWhere('type','return');

            $photosPickupCount  = ($pickupChecklist && method_exists($pickupChecklist,'getMedia'))
                ? $pickupChecklist->getMedia('photos')->count()
                : 0;
            $photosReturnCount  = ($returnChecklist && method_exists($returnChecklist,'getMedia'))
                ? $returnChecklist->getMedia('photos')->count()
                : 0;

            /**
            * Tabs originali: NON cambiamo le chiavi né il meccanismo wire:click="switch(...)"
            */
            $tabs = [
                'data'        => 'Dati',
                'contract'    => 'Contratto',
                'pickup'      => 'Checklist Pickup',
                'return'      => 'Checklist Return',
                'damages'     => 'Danni',
                'attachments' => 'Allegati',
                'timeline'    => 'Timeline',
            ];
        @endphp

        {{-- Wrapper sticky: resta in vista durante lo scroll.
            Adegua "top-0" se hai una navbar fissa (es. top-14). --}}
        <div class="sticky top-0 z-20 bg-base-100/90 backdrop-blur border-b -mx-4 px-4 py-3">
            {{-- Header: titolo + meta compatti e coerenti con il resto del gestionale --}}
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h1 class="text-xl md:text-2xl font-semibold flex items-center gap-2">
                        Noleggio {{ $rental->reference ?? ('#'.$rental->id) }}
                        <span class="badge {{ $statusBadgeClass }}">{{ str_replace('_',' ', $rental->status) }}</span>
                    </h1>
                    <p class="opacity-70 text-xs md:text-sm mt-1 flex flex-wrap gap-x-3 gap-y-1">
                        {{-- Date pianificate --}}
                        <span>
                            {{ optional($rental->planned_pickup_at)->format('d/m H:i') }}
                            →
                            {{ optional($rental->planned_return_at)->format('d/m H:i') }}
                        </span>
                        <span class="hidden md:inline">·</span>
                        {{-- Cliente --}}
                        <span>{{ optional($rental->customer)->name ?? '—' }}</span>
                    </p>
                </div>
            </div>

            {{-- Tabs lifted: più leggibili e coerenti con DaisyUI. --}}
            <div class="mt-3 tabs tabs-lifted no-scrollbar">
                @foreach($tabs as $key => $label)
                    @php
                        // Micro-badge per dare feedback “a colpo d’occhio” su contenuti rilevanti delle singole tab.
                        $micro = null;
                        if ($key === 'contract')     { $micro = $contractSigned ?: $contractGenerated; }
                        if ($key === 'attachments')  { $micro = $attachmentsCount; }
                        if ($key === 'damages')      { $micro = $damagesCount; }
                        if ($key === 'pickup')       { $micro = $photosPickupCount; }
                        if ($key === 'return')       { $micro = $photosReturnCount; }
                    @endphp

                    <button
                        class="rounded px-2 py-1 ring-1 ring-slate-300 tab {{ $tab === $key ? 'bg-slate-800 text-white' : 'bg-white text-slate-700' }} mr-2"
                        wire:click="switch('{{ $key }}')"
                        aria-current="{{ $tab === $key ? 'page' : 'false' }}"
                    >
                        <span class="whitespace-nowrap">{{ $label }}</span>
                        @if(!empty($micro))
                            {{-- badge XS non invasivo accanto all’etichetta --}}
                            <span class="ml-2 badge badge-ghost badge-xs">{{ $micro }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Tab panels (MVP placeholders da completare) --}}
        @switch($tab)
            @case('data')
                @include('pages.rentals.partials.tab-data', ['rental'=>$rental])
                @break

            @case('contract')
                @include('pages.rentals.partials.tab-contract', ['rental'=>$rental])
                @break

            @case('pickup')
                @include('pages.rentals.partials.tab-checklist', ['rental'=>$rental, 'type'=>'pickup'])
                @break

            @case('return')
                @include('pages.rentals.partials.tab-checklist', ['rental'=>$rental, 'type'=>'return'])
                @break

            @case('damages')
                @include('pages.rentals.partials.tab-damages', ['rental'=>$rental])
                @break

            @case('attachments')
                @include('pages.rentals.partials.tab-attachments', ['rental'=>$rental])
                @break

            @case('timeline')
                @include('pages.rentals.partials.tab-timeline', ['rental'=>$rental])
                @break
        @endswitch
    </div>

    {{-- Action Drawer (colonna destra) --}}
    <aside class="lg:col-span-3">
        <div class="card shadow sticky top-4">
            <div class="card-body space-y-3">
                <div class="card-title">Azioni</div>

                {{-- Pulsanti transizione: form POST verso RentalController --}}
                @include('pages.rentals.partials.actions', ['rental'=>$rental])

                <div class="divider">Upload rapidi</div>

                {{-- Upload CONTRATTO generato (PDF) --}}
                <x-media-uploader
                    label="Contratto (PDF)"
                    :action="route('rentals.media.contract.store', $rental)"
                    accept="application/pdf"
                />

                {{-- Upload CONTRATTO firmato: chiama il controller che duplica su Rental e Checklist(pickup) --}}
                <x-media-uploader
                    label="Contratto firmato (PDF/JPG/PNG)"
                    :action="route('rentals.media.contract.signed.store', $rental)"
                    accept="application/pdf,image/jpeg,image/png"
                />

                {{-- Upload DOCUMENTI (con select collection) --}}
                <x-media-uploader
                    label="Documento noleggio"
                    :action="route('rentals.media.documents.store', $rental)"
                    accept="application/pdf,image/*"
                >
                    {{-- SLOT: selezione tipo documento → salvato come collection_name in media --}}
                    <select name="collection"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm appearance-none pr-8 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="documents">Documenti vari (precontrattuali)</option>
                        <option value="id_card">Documento identità</option>
                        <option value="driver_license">Patente</option>
                        <option value="privacy">Consenso privacy</option>
                        <option value="other">Altro</option>
                    </select>
                </x-media-uploader>
            </div>
        </div>
    </aside>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('rental', {
                status: @js($rental->status),     // es. "draft" | "checked_out" | ... | "closed"
                get isClosed() { return this.status === 'closed'; },

                // opzionale: helper generico per “read-only”
                get isReadOnly() { return this.isClosed; },

                setStatus(s) { this.status = s; } // per sincronizzare dopo le azioni AJAX
            });
        });
    </script>

</div>
