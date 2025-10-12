{{-- Scheda "Dati" — Riepilogo contratto e stato documentale/foto --}}
<div class="grid lg:grid-cols-3 gap-6">
    {{-- Colonna 1: Dati contratto --}}
    <div class="card shadow lg:col-span-2">
        <div class="card-body space-y-4">
            <div class="flex items-center justify-between">
                <div class="card-title">Dati contratto</div>
                <span class="badge">{{ $rental->status }}</span>
            </div>

            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="opacity-70">Riferimento</div>
                    <div class="font-medium">{{ $rental->reference ?? ('#'.$rental->id) }}</div>
                </div>
                <div>
                    <div class="opacity-70">Cliente</div>
                    <div class="font-medium">{{ optional($rental->customer)->full_name ?? '—' }}</div>
                </div>
                <div>
                    <div class="opacity-70">Veicolo</div>
                    <div class="font-medium">
                        {{ optional($rental->vehicle)->plate ?? optional($rental->vehicle)->name ?? '—' }}
                    </div>
                </div>
                <div>
                    <div class="opacity-70">Organizzazione</div>
                    <div class="font-medium">{{ optional($rental->organization)->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="opacity-70">Pickup (pianificato)</div>
                    <div class="font-medium">{{ optional($rental->planned_pickup_at)->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
                <div>
                    <div class="opacity-70">Return (pianificato)</div>
                    <div class="font-medium">{{ optional($rental->planned_return_at)->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
                <div>
                    <div class="opacity-70">Pickup (effettivo)</div>
                    <div class="font-medium">{{ optional($rental->actual_pickup_at)->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
                <div>
                    <div class="opacity-70">Return (effettivo)</div>
                    <div class="font-medium">{{ optional($rental->actual_return_at)->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
                <div>
                    <div class="opacity-70">Sede ritiro</div>
                    <div class="font-medium">{{ optional($rental->pickupLocation)->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="opacity-70">Sede riconsegna</div>
                    <div class="font-medium">{{ optional($rental->returnLocation)->name ?? '—' }}</div>
                </div>
            </div>

            {{-- Note operative --}}
            @if(!empty($rental->notes))
                <div class="divider my-2"></div>
                <div>
                    <div class="opacity-70 text-sm mb-1">Note</div>
                    <div class="prose max-w-none text-sm">{{ $rental->notes }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Colonna 2: Stato allegati / check / pagamenti --}}
    @php
        $pickup   = $rental->checklists->firstWhere('type','pickup');
        $return   = $rental->checklists->firstWhere('type','return');
        $hasCtr = method_exists($rental, 'getMedia') ? $rental->getMedia('contract')->isNotEmpty() : false;
        $hasSign  = method_exists($rental,'getMedia') ? $rental->getMedia('signatures')->isNotEmpty() : false;
        $hasSignC = ($pickup && method_exists($pickup,'getMedia')) ? $pickup->getMedia('signatures')->isNotEmpty() : false;
        $photosPU = ($pickup && method_exists($pickup,'getMedia')) ? $pickup->getMedia('photos')->count() : 0;
        $photosRT = ($return && method_exists($return,'getMedia')) ? $return->getMedia('photos')->count() : 0;
        $dmgCount = $rental->damages->count();
        $dmgNoPic = $rental->damages->filter(fn($d)=>$d->getMedia('photos')->isEmpty())->count();
    @endphp

    <div class="card shadow">
        <div class="card-body space-y-4">
            <div class="card-title">Stato documentale</div>

            <ul class="space-y-2 text-sm">
                <li class="flex items-center justify-between">
                    <span>Contratto generato</span>
                    <span class="badge {{ $hasCtr ? 'badge-success' : 'badge-outline' }}">
                        {{ $hasCtr ? 'Presente' : 'Mancante' }}
                    </span>
                </li>
                <li class="flex items-center justify-between">
                    <span>Contratto firmato (Rental → signatures)</span>
                    <span class="badge {{ $hasSign ? 'badge-success' : 'badge-outline' }}">
                        {{ $hasSign ? 'Presente' : 'Mancante' }}
                    </span>
                </li>
                <li class="flex items-center justify-between">
                    <span>Contratto firmato (Checklist pickup → signatures)</span>
                    <span class="badge {{ $hasSignC ? 'badge-success' : 'badge-outline' }}">
                        {{ $hasSignC ? 'Presente' : 'Mancante' }}
                    </span>
                </li>
                <li class="flex items-center justify-between">
                    <span>Checklist pickup</span>
                    <span class="badge {{ $pickup ? 'badge-success' : 'badge-outline' }}">
                        {{ $pickup ? 'OK' : 'Mancante' }}
                    </span>
                </li>
                <li class="flex items-center justify-between">
                    <span>Foto pickup</span>
                    <span class="badge {{ $photosPU>0 ? 'badge-success' : 'badge-outline' }}">
                        {{ $photosPU }} foto
                    </span>
                </li>
                <li class="flex items-center justify-between">
                    <span>Checklist return</span>
                    <span class="badge {{ $return ? 'badge-success' : 'badge-outline' }}">
                        {{ $return ? 'OK' : 'Mancante' }}
                    </span>
                </li>
                <li class="flex items-center justify-between">
                    <span>Foto return</span>
                    <span class="badge {{ $photosRT>0 ? 'badge-success' : 'badge-outline' }}">
                        {{ $photosRT }} foto
                    </span>
                </li>
                <li class="flex items-center justify-between">
                    <span>Danni registrati</span>
                    <span class="badge {{ $dmgCount>0 ? 'badge-warning' : 'badge-outline' }}">
                        {{ $dmgCount }}
                    </span>
                </li>
                @if($dmgCount>0)
                <li class="flex items-center justify-between">
                    <span>Danni senza foto</span>
                    <span class="badge {{ $dmgNoPic===0 ? 'badge-success' : 'badge-error' }}">
                        {{ $dmgNoPic }}
                    </span>
                </li>
                @endif
                <li class="flex items-center justify-between">
                    <span>Pagamento registrato</span>
                    <span class="badge {{ $rental->payment_recorded ? 'badge-success' : 'badge-outline' }}">
                        {{ $rental->payment_recorded ? optional($rental->payment_recorded_at)->format('d/m/Y H:i') : 'No' }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</div>
