{{-- resources/views/pdfs/checklist.blade.php --}}
@php
    $rental   = $checklist->rental;
    $vehicle  = optional($rental)->vehicle;
    $customer = optional($rental)->customer;

    /**
     * Helper per checkbox stampabili.
     *
     * @param mixed $v
     * @return string
     */
    $yes = fn ($v) => $v ? '☑' : '☐';

    // Etichette
    $cleanlinessLabels = ['poor'=>'Scarsa','fair'=>'Discreta','good'=>'Buona','excellent'=>'Eccellente'];
    $areaLabels = [
        'front'=>'Anteriore','rear'=>'Posteriore','left'=>'Sinistra','right'=>'Destra','interior'=>'Interno',
        'roof'=>'Tetto','windshield'=>'Parabrezza','wheel'=>'Ruota','other'=>'Altro'
    ];
    $severityLabels = ['low'=>'Bassa','medium'=>'Media','high'=>'Alta'];

    // Dati dal payload (già “congelati” in Livewire)
    $base      = $payload['base']     ?? [];
    $json      = $payload['json']     ?? [];
    $damages   = $payload['damages']  ?? [];
    $documents = $json['documents']   ?? [];
    $equipment = $json['equipment']   ?? [];
    $vehState  = $json['vehicle']     ?? [];
    $notes     = trim((string)($json['notes'] ?? ''));

    /**
     * Tipo checklist:
     * - per checklist reali: PICKUP | RETURN
     * - per checklist vuota: stringa vuota
     */
    $titleType = strtoupper(trim((string)($checklist->type ?? '')));

    /**
     * Titolo documento:
     * - "Checklist — PICKUP/RETURN" se il tipo è valorizzato
     * - "Checklist" se il tipo è vuoto
     */
    $titleText = $titleType !== '' ? 'Checklist — '.$titleType : 'Checklist';

    /**
     * Numero noleggio e ID checklist:
     * lasciamo vuoti i placeholder se il dato non esiste,
     * così la stampa manuale rimane più pulita.
     */
    $rentalLabel = trim((string)($rental->display_number_label ?? ''));
    $checklistId = trim((string)($checklist->id ?? ''));

    /**
     * Dati base:
     * - se vuoti/null, non mostriamo 0, %, km o trattini
     * - se valorizzati, manteniamo il formato originale
     */
    $mileage = $base['mileage'] ?? null;
    $fuelPercent = $base['fuel_percent'] ?? null;
    $clean = $base['cleanliness'] ?? null;

    $mileageText = ($mileage === '' || $mileage === null)
        ? ''
        : number_format((int) $mileage, 0, ',', '.') . ' km';

    $fuelPercentText = ($fuelPercent === '' || $fuelPercent === null)
        ? ''
        : (int) $fuelPercent . '%';

    $cleanlinessText = ($clean === '' || $clean === null)
        ? ''
        : ($cleanlinessLabels[$clean] ?? '');
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>{{ $titleText }}@if($checklistId !== '') #{{ $checklistId }} @endif</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 24px; }
        h1, h2, h3 { margin: 0 0 6px 0; }
        h1 { font-size: 20px; } h2 { font-size: 16px; } h3 { font-size: 13px; }
        .muted { color: #666; } .tiny { font-size: 10px; color: #666; }
        .mt-2 { margin-top: 8px; } .mt-4 { margin-top: 16px; } .mt-6 { margin-top: 24px; }
        .mb-2 { margin-bottom: 8px; } .mb-4 { margin-bottom: 16px; } .mb-6 { margin-bottom: 24px; }
        .grid-2 { display: table; width: 100%; table-layout: fixed; }
        .grid-2 > div { display: table-cell; vertical-align: top; width: 50%; }
        .box { border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; }
        th { background: #f5f5f5; text-align: left; }
        .right { text-align: right; } .center { text-align: center; }
        .strip { padding: 8px 10px; background: #eef; border: 1px solid #cdd; border-radius: 4px; }

        /* Firma box */
        .sign-block { border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
        .sign-area { border: 2px dashed #bbb; height: 90px; margin-top: 8px; }
        .sign-label { margin-top: 4px; font-size: 11px; color: #444; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="grid-2 mb-6">
        <div>
            <h1>{{ $titleText }}</h1>
            <div class="muted">
                @if($rentalLabel !== '')
                    Noleggio {{ $rentalLabel }}
                @endif

                @if($rentalLabel !== '' && $checklistId !== '')
                    ·
                @endif

                @if($checklistId !== '')
                    Checklist #{{ $checklistId }}
                @endif

                <br>
                Generata il {{ optional($generated_at)->format('d/m/Y H:i') }}
            </div>

            @if($checklist->replaces_checklist_id)
                <div class="mt-2 strip">
                    Questa checklist <strong>annulla e sostituisce</strong> la #{{ $checklist->replaces_checklist_id }}.
                </div>
            @endif

            @if($checklist->isLocked())
                <div class="mt-2 strip" style="background:#efe; border-color:#cfc;">
                    Stato: <strong>BLOCCATA</strong> (firma caricata).
                </div>
            @endif
        </div>

        <div class="right">
            <div class="box">
                <strong>Cliente</strong><br>
                {{ trim(($customer->name ?? '').' '.($customer->surname ?? '')) ?: ($customer->business_name ?? '—') }}<br>
                <span class="tiny">{{ $customer->tax_code ?? $customer->vat_number ?? '' }}</span>

                <div class="mt-2">
                    <strong>Veicolo</strong><br>
                    @php
                        /**
                        * Riga descrittiva veicolo.
                        * - Per checklist reali mostra marca + modello se presenti.
                        * - Per checklist vuote lascia il campo completamente vuoto.
                        */
                        $vehLine1 = trim(($vehicle->brand ?? '').' '.($vehicle->model ?? ''));

                        /**
                        * Riga targa.
                        * - Mostra "Targa XXX" solo se la targa è valorizzata.
                        * - Per checklist vuote non mostra nulla.
                        */
                        $vehLine2 = trim((string) ($vehicle->plate ?? '')) !== ''
                            ? 'Targa '.trim((string) $vehicle->plate)
                            : '';
                    @endphp

                    {{ $vehLine1 }}<br>
                    <span class="tiny">{{ $vehLine2 }}</span>
                </div>
            </div>
        </div>
    </div>

    <br>

    {{-- DATI BASE --}}
    <h2 class="mb-2">Dati base</h2>
    <table class="mb-6">
        <tr>
            <th style="width:30%">Chilometraggio</th>
            <td>{{ $mileageText }}</td>
        </tr>
        <tr>
            <th>Carburante</th>
            <td>{{ $fuelPercentText }}</td>
        </tr>
        <tr>
            <th>Pulizia</th>
            <td>{{ $cleanlinessText }}</td>
        </tr>
    </table>

    <br>

    {{-- DOCUMENTI --}}
    @if($checklist->type === 'pickup')
        <div class="strip mb-6">
            Nota: i documenti elencati si riferiscono a quelli acquisiti al momento del ritiro del veicolo.
        </div>
        <h2 class="mb-2">Documenti</h2>
        <table class="mb-6">
            <tr>
                <th style="width:60%">Carta d’identità acquisita</th>
                <td class="center" style="width:40%">{{ $yes($documents['id_card'] ?? false) }}</td>
            </tr>
            <tr>
                <th>Patente acquisita</th>
                <td class="center">{{ $yes($documents['driver_license'] ?? false) }}</td>
            </tr>
            <tr>
                <th>Copia contratto consegnata</th>
                <td class="center">{{ $yes($documents['contract_copy'] ?? false) }}</td>
            </tr>
        </table>
    @endif

    <br>

    {{-- DOTAZIONI / SICUREZZA --}}
    <h2 class="mb-2">Dotazioni / Sicurezza</h2>
    <table class="mb-6">
        <tr><th style="width:60%">Ruotino/ruota di scorta presente</th><td class="center" style="width:40%">{{ $yes($equipment['spare_wheel'] ?? false) }}</td></tr>
        <tr><th>Cric presente</th><td class="center">{{ $yes($equipment['jack'] ?? false) }}</td></tr>
        <tr><th>Triangolo presente</th><td class="center">{{ $yes($equipment['triangle'] ?? false) }}</td></tr>
        <tr><th>Gilet alta visibilità presente</th><td class="center">{{ $yes($equipment['vest'] ?? false) }}</td></tr>
    </table>

    <br>

    {{-- CONDIZIONI VEICOLO --}}
    <h2 class="mb-2">Condizioni veicolo</h2>
    <table class="mb-6">
        <tr><th style="width:60%">Luci funzionanti</th><td class="center" style="width:40%">{{ $yes($vehState['lights_ok'] ?? false) }}</td></tr>
        <tr><th>Clacson funzionante</th><td class="center">{{ $yes($vehState['horn_ok'] ?? false) }}</td></tr>
        <tr><th>Freni in ordine</th><td class="center">{{ $yes($vehState['brakes_ok'] ?? false) }}</td></tr>
        <tr><th>Pneumatici in ordine</th><td class="center">{{ $yes($vehState['tires_ok'] ?? false) }}</td></tr>
        <tr><th>Parabrezza integro</th><td class="center">{{ $yes($vehState['windshield_ok'] ?? false) }}</td></tr>
    </table>

    <br><br>

    {{-- DANNI (vehicle aperti + rental) --}}
    <h2 class="mb-2">Danni</h2>
    @if(empty($damages))
        <div class="box mb-6 muted">Nessun danno registrato.</div>
    @else
        <table class="mb-6">
            <thead>
                <tr>
                    <th style="width:20%">Area</th>
                    <th style="width:15%">Gravità</th>
                    <th>Descrizione</th>
                </tr>
            </thead>
            <tbody>
                @foreach($damages as $row)
                    @php
                        $a = $areaLabels[$row['area'] ?? ''] ?? ($row['area'] ?? '—');
                        $s = $severityLabels[$row['severity'] ?? ''] ?? ($row['severity'] ?? '—');
                        $desc = trim($row['description'] ?? '') ?: '—';
                    @endphp
                    <tr>
                        <td>{{ $a }}</td>
                        <td>{{ $s }}</td>
                        <td>{{ $desc }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <br>

    {{-- NOTE (mostra SOLO se presenti) --}}
    @if($notes !== '')
        <h2 class="mb-2">Note</h2>
        <div class="box mb-6" style="min-height: 60px;">
            {!! nl2br(e($notes)) !!}
        </div>
    @endif

    {{-- FIRME MANUALI --}}
    <div class="mt-6">
        <div class="sign-block mb-6">
            <h3>Firma cliente</h3>
            <div class="muted">Verrà acquisita digitalmente o manualmente in fase di ritiro.</div>
            <div class="sign-area" style="position:relative;">
                @if(!empty($signatures['customer']))
                    <img src="{{ $signatures['customer'] }}" style="height:80px; margin-top:4px;" alt="Firma cliente">
                @endif
            </div>
            <div class="sign-label">
                {{ trim(($customer->name ?? '').' '.($customer->surname ?? '')) ?: ($customer->business_name ?? '—') }}
            </div>
        </div>

        <div class="sign-block">
            <h3>Firma noleggiante</h3>
            <div class="muted">Verrà acquisita digitalmente o manualmente in fase di ritiro.</div>
            <div class="sign-area" style="position:relative;">
                @if(!empty($signatures['lessor']))
                    <img src="{{ $signatures['lessor'] }}" style="height:80px; margin-top:4px;" alt="Firma noleggiante">
                @endif
            </div>
            <div class="sign-label">
                {{ optional($rental->organization)->name ?? config('app.name', '—') }}
            </div>
        </div>
    </div>

    {{-- FOOTER --}}
    <div class="tiny mt-6">
        Documento generato dal gestionale il {{ optional($generated_at)->format('d/m/Y H:i') }}.
        In caso di difformità prevale la versione firmata digitalmente o analogicamente dal cliente.
    </div>

</body>
</html>