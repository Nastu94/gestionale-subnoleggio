{{-- Timeline eventi — costruita dai campi del rental e dalla presenza media/checklist/danni --}}
@php
    $pickup   = $rental->checklists->firstWhere('type','pickup');
    $return   = $rental->checklists->firstWhere('type','return');
    $events = [];

    // Bozza
    $events[] = [
        'when'  => $rental->created_at,
        'label' => 'Bozza creata',
        'desc'  => 'Noleggio creato in stato draft',
        'icon'  => '📝',
    ];

    // Contratto
    foreach ($rental->getMedia('contract') as $m) {
        $events[] = [
            'when'  => $m->created_at,
            'label' => 'Contratto generato',
            'desc'  => $m->file_name,
            'icon'  => '📄',
            'url'   => $m->getUrl(),
        ];
    }
    foreach ($rental->getMedia('signatures') as $m) {
        $events[] = [
            'when'  => $m->created_at,
            'label' => 'Contratto firmato (Rental)',
            'desc'  => $m->file_name,
            'icon'  => '✍️',
            'url'   => $m->getUrl('preview') ?: $m->getUrl(),
        ];
    }
    if ($pickup) {
        foreach ($pickup->getMedia('signatures') as $m) {
            $events[] = [
                'when'  => $m->created_at,
                'label' => 'Contratto firmato (Checklist pickup)',
                'desc'  => $m->file_name,
                'icon'  => '✍️',
                'url'   => $m->getUrl('preview') ?: $m->getUrl(),
            ];
        }
    }

    // Checklist + foto
    if ($pickup) {
        $events[] = [
            'when'  => $pickup->created_at,
            'label' => 'Checklist pickup compilata',
            'desc'  => "Foto: ".$pickup->getMedia('photos')->count(),
            'icon'  => '✅',
        ];
    }
    if ($return) {
        $events[] = [
            'when'  => $return->created_at,
            'label' => 'Checklist return compilata',
            'desc'  => "Foto: ".$return->getMedia('photos')->count(),
            'icon'  => '✅',
        ];
    }

    // Timestamps operativi
    if ($rental->actual_pickup_at) {
        $events[] = [
            'when'  => $rental->actual_pickup_at,
            'label' => 'Veicolo consegnato (checked_out)',
            'desc'  => '',
            'icon'  => '🚗',
        ];
    }
    if ($rental->actual_return_at) {
        $events[] = [
            'when'  => $rental->actual_return_at,
            'label' => 'Veicolo rientrato (checked_in)',
            'desc'  => '',
            'icon'  => '🏁',
        ];
    }

    // Danni
    foreach ($rental->damages as $dmg) {
        $events[] = [
            'when'  => $dmg->created_at,
            'label' => "Danno registrato ({$dmg->phase})",
            'desc'  => trim(($dmg->area ? $dmg->area.' · ' : '').($dmg->description ?? '')),
            'icon'  => '⚠️',
        ];
    }

    // Chiusura
    if ($rental->status === 'closed') {
        $events[] = [
            'when'  => $rental->updated_at,
            'label' => 'Contratto chiuso',
            'desc'  => $rental->payment_recorded ? 'Pagamento registrato' : null,
            'icon'  => '🗂️',
        ];
    }

    // Ordina eventi cronologicamente
    usort($events, fn($a,$b) => optional($a['when'])->timestamp <=> optional($b['when'])->timestamp);
@endphp

<div class="card shadow">
    <div class="card-body">
        <div class="card-title">Timeline</div>

        @if(empty($events))
            <div class="opacity-70 text-sm">Nessun evento disponibile.</div>
        @else
            <ol class="relative border-s border-base-300 pl-6">
                @foreach($events as $ev)
                    <li class="mb-6 ms-4">
                        <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full bg-base-200 ring-2 ring-base-300 text-xs">
                            {{ $ev['icon'] ?? '•' }}
                        </span>
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold">{{ $ev['label'] }}</h3>
                            <div class="text-xs opacity-70">
                                {{ optional($ev['when'])->format('d/m/Y H:i') ?? '—' }}
                            </div>
                        </div>
                        @if(!empty($ev['desc']))
                            <p class="text-sm opacity-80 mt-1">{{ $ev['desc'] }}</p>
                        @endif
                        @if(!empty($ev['url']))
                            <a href="{{ $ev['url'] }}" class="link text-sm mt-1 inline-block" target="_blank">Apri</a>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
