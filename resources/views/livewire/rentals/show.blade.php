<div class="grid lg:grid-cols-12 gap-6">
    {{-- Colonna principale --}}
    <div class="lg:col-span-9 space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <div class="text-2xl font-semibold">
                    Noleggio {{ $rental->reference ?? ('#'.$rental->id) }}
                    <span class="badge ml-2">{{ $rental->status }}</span>
                </div>
                <div class="opacity-70 text-sm">
                    {{ optional($rental->planned_pickup_at)->format('d/m H:i') }} → {{ optional($rental->planned_return_at)->format('d/m H:i') }}
                    · {{ optional($rental->customer)->name }}
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="tabs tabs-boxed">
            @php $tabs = [
                'data'        => 'Dati',
                'contract'    => 'Contratto',
                'pickup'      => 'Checklist Pickup',
                'return'      => 'Checklist Return',
                'damages'     => 'Danni',
                'attachments' => 'Allegati',
                'timeline'    => 'Timeline',
            ]; @endphp
            @foreach($tabs as $key => $label)
                <button class="tab {{ $tab===$key?'tab-active':'' }}" wire:click="switch('{{ $key }}')">{{ $label }}</button>
            @endforeach
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

                {{-- Upload documenti vari --}}
                <x-media-uploader
                    label="Documento noleggio"
                    :action="route('rentals.media.documents.store', $rental)"
                    accept="application/pdf,image/*"
                />
            </div>
        </div>
    </aside>
</div>
