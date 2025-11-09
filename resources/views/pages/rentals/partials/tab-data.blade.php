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
                <span class="badge {{ $statusBadgeClass }}">{{ str_replace('_',' ', $rental->status) }}</span>
            </div>

            {{-- Layout semantico con dl/dt/dd, tipografia chiara e spaziatura coerente --}}
            <dl class="grid md:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                <div>
                    <dt class="opacity-70">Riferimento</dt>
                    <dd class="font-medium">{{ $rental->reference ?? ('#'.$rental->id) }}</dd>
                </div>

                <div>
                    <dt class="opacity-70">Cliente</dt>
                    <dd class="font-medium">
                        {{ optional($rental->customer)->name ?? '—' }}
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
</div>
