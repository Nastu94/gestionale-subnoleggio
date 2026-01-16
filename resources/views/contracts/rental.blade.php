{{-- resources/views/contracts/rental.blade.php --}}
{{-- Contratto di noleggio - Bozza PDF --}}

@php
    /** @var array $org - dati organizzazione (name, vat, address, etc.) */
    /** @var array $rental - dati noleggio (id, planned_pickup_at, planned_return_at, locations) */
    /** @var array $customer - anagrafica cliente */
    /** @var array $vehicle - dati veicolo (make, model, plate, color, vin?) */
    /** @var array $pricing - listino/simulatore (base_total, days, km_daily_limit|null, included_km_total, extra_km_rate, extras[], deposit?) */
    /** @var array $coverages - flags coperture (kasko, furto_incendio, cristalli, assistenza) */
    /** @var array $franchigie - importi franchigie (kasko, furto_incendio, cristalli) */
    /** @var array $clauses - testi standard (responsabilita, utilizzo, coperture, riconsegna, penali, legge_foro) */

    /** @var string|null $vehicle_owner_name */
    /** @var \App\Models\Customer|null $second_driver */
    /** @var float|null $final_amount */
    /** @var bool|null $render_signatures */

    // ✅ Condizioni da config/rental.php (nuovo formato “sezioni”)
    // NB: se non esiste, rimane array vuoto e usiamo fallback su $clauses.
    $rentalConfigClauses = config('rental.clauses', []);
    $rentalClauseSections = $rentalConfigClauses['sections'] ?? [];
@endphp

<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Contratto di noleggio {{ $rental['number_label'] }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
        h1, h2 { margin: 0 0 6px; }
        h1 { font-size: 18px; }
        h2 { font-size: 14px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        .muted { color: #666; }
        .row { display: flex; gap: 16px; }
        .col { flex: 1; }
        .box { border: 1px solid #e5e5e5; border-radius: 6px; padding: 10px; margin-bottom: 10px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #e5e5e5; padding: 6px; vertical-align: top; }
        .small { font-size: 11px; }
        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: avoid; }
        .table thead { display: table-header-group; }
        .table tr { page-break-inside: avoid; }
    </style>
</head>

<body>

    {{-- BOX LOGHI (DOMPDF safe) --}}
    <div style="position:absolute; top:20px; right:20px; width:180px; height:80px; text-align:right;">
        @if(!empty($logos['amd']))
            <div style="height:38px; margin-bottom:4px;">
                <img src="{{ $logos['amd'] }}" alt="Logo AMD"
                    style="max-width:180px; max-height:38px;">
            </div>
        @endif
    </div>

    <h1>Contratto di noleggio veicolo</h1>

    {{-- NEW — Nome noleggiatore descrittivo --}}
    <div class="muted">
        Nome Noleggiante: <strong>{{ $vehicle_owner_name }}</strong><br>
        Numero contratto: <strong>{{ $rental['number_label'] }}</strong> —
        Data: {{ $rental['issued_at'] ?? now()->format('d/m/Y') }}
    </div>

    <div class="row">
        <div class="col">
            <div class="box">
                <h2>Noleggiante</h2>
                <div><strong>{{ $org['name'] }}</strong></div>
                <div>P.IVA/CF: {{ $org['vat'] ?? '—' }}</div>
                <div>Indirizzo: {{ trim(($org['address'] ?? '') . ' ' . ($org['zip'] ?? '') . ' ' . ($org['city'] ?? '')) ?: '—' }}</div>
                <div class="small">{{ $org['phone'] ?? '' }} {{ $org['email'] ?? '' }}</div>
            </div>
        </div>
        <div class="col">
            <div class="box">
                <h2>Cliente</h2>
                <div><strong>{{ $customer['name'] }}</strong></div>

                <div>P.IVA/CF: {{ $customer['tax_id'] ?? '—' }}</div>
                <div>Patente n.: {{ $customer['driver_license_number'] ?? '—' }}</div>

                <div>Doc: {{ $customer['doc_id_type'] ?? 'ID' }} {{ $customer['doc_id_number'] ?? '—' }}</div>

                <div>
                    Indirizzo:
                    {{ trim(($customer['address'] ?? '') . ' ' . ($customer['zip'] ?? '') . ' ' . ($customer['city'] ?? '') . ' ' . ($customer['province'] ?? '')) ?: '—' }}
                </div>

                <div class="small">{{ $customer['phone'] ?? '' }} {{ $customer['email'] ?? '' }}</div>
            </div>
        </div>
    </div>

    {{-- NEW — SECONDA GUIDA --}}
    @if($second_driver)
        <div class="box">
            <h2>Seconda guida</h2>
            <table class="table">
                <tr>
                    <th>Nome</th>
                    <td>{{ $second_driver->name }}</td>
                </tr>
                <tr>
                    <th>Documento</th>
                    <td>{{ $second_driver->doc_id_type }} {{ $second_driver->doc_id_number }}</td>
                </tr>
                <tr>
                    <th>Supplemento</th>
                    <td>€ {{ number_format(($pricing_totals['second_driver_total'] ?? 0) / 100, 2, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    @endif

    <div class="box row avoid-break">
        <h2>Veicolo</h2>
        <table class="table">
            <tr>
                <th>Marca</th><td>{{ $vehicle['make'] ?? '—' }}</td>
                <th>Modello</th><td>{{ $vehicle['model'] ?? '—' }}</td>
            </tr>
            <tr>
                <th>Targa</th><td>{{ $vehicle['plate'] ?? '—' }}</td>
                <th>Colore</th><td>{{ $vehicle['color'] ?? '—' }}</td>
            </tr>
            @if(!empty($vehicle['vin']))
            <tr>
                <th>VIN</th><td colspan="3">{{ $vehicle['vin'] }}</td>
            </tr>
            @endif
        </table>
        <div class="small muted" style="margin-top:6px;">
            Condizione carburante alla consegna: <strong>Pieno</strong>.
            Rilevazioni dettagliate (km, stato, accessori, danni) saranno effettuate e firmate nelle Checklist Pickup/Return.
        </div>
    </div>

    <div class="box row avoid-break">
        <h2>Dettagli noleggio</h2>
        <table class="table">
            <tr>
                <th>Ritiro</th>
                <td>{{ $rental['pickup_at'] ?? '—' }} @ {{ $rental['pickup_location'] ?? '—' }}</td>
                <th>Riconsegna</th>
                <td>{{ $rental['return_at'] ?? '—' }} @ {{ $rental['return_location'] ?? '—' }}</td>
            </tr>
            <tr>
                <th>Giorni</th>
                <td>{{ $pricing['days'] ?? '—' }}</td>
                <th>Tariffa</th>
                <td>{{ number_format($pricing_totals['tariff_total_cents'] / 100, 2, ',', '.') }} (IVA incl.)</td>
            </tr>
            <tr>
                <th>Chilometraggio incluso</th>
                <td colspan="3">
                    @if(is_null($pricing['km_daily_limit'] ?? null))
                        Illimitato.
                    @else
                        Limite giornaliero: <strong>{{ $pricing['km_daily_limit'] }}</strong> km × {{ $pricing['days'] ?? '?' }} giorno/i
                        = <strong>{{ $pricing['included_km_total'] ?? '—' }}</strong> km inclusi totali.
                        Oltre tale soglia: <strong>{{ $pricing['extra_km_rate'] ?? '—' }} €/km</strong>.
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="box row avoid-break">
        <h2>Coperture e franchigie</h2>
        <table class="table">
            <tr>
                <th>RC base</th>
                <td>
                    Inclusa (obbligatoria)
                    @if(isset($franchigie['rca']))
                        — Franchigia: € {{ number_format($franchigie['rca'], 2, ',', '.') }}
                    @endif
                </td>

                <th>Kasko</th>
                <td>
                    {{ !empty($coverages['kasko']) ? 'Inclusa' : 'Non inclusa' }}
                    @if(!empty($coverages['kasko']) && isset($franchigie['kasko']))
                        — Franchigia: € {{ number_format($franchigie['kasko'], 2, ',', '.') }}
                    @endif
                </td>
            </tr>

            <tr>
                <th>Furto/Incendio</th>
                <td>
                    {{ !empty($coverages['furto_incendio']) ? 'Inclusa' : 'Non inclusa' }}
                    @if(!empty($coverages['furto_incendio']) && isset($franchigie['furto_incendio']))
                        — Franchigia: € {{ number_format($franchigie['furto_incendio'], 2, ',', '.') }}
                    @endif
                </td>

                <th>Cristalli</th>
                <td>
                    {{ !empty($coverages['cristalli']) ? 'Inclusa' : 'Non inclusa' }}
                    @if(!empty($coverages['cristalli']) && isset($franchigie['cristalli']))
                        — Franchigia: € {{ number_format($franchigie['cristalli'], 2, ',', '.') }}
                    @endif
                </td>
            </tr>

            <tr>
                <th>Assistenza</th>
                <td>{{ !empty($coverages['assistenza']) ? 'Inclusa' : 'Non inclusa' }}</td>

                <th>Deposito cauzionale</th>
                <td>{{ $pricing['deposit'] ?? '—' }}</td>
            </tr>
        </table>

        <div class="small muted" style="margin-top:6px;">
            Eventuali penali e addebiti saranno compensati sulla cauzione. Le condizioni dettagliate sono riportate nelle clausole generali.
        </div>
    </div>

    @if($second_driver && !empty($pricing_totals))
        @php
            $cur = $pricing_totals['currency'] ?? 'EUR';
            $money = function(int $cents) use ($cur) {
                return number_format($cents / 100, 2, ',', '.') . ' ' . $cur;
            };
        @endphp

        <div class="box row avoid-break">
            <h2>Riepilogo costi</h2>
            <table class="table">
                <tr>
                    <th>Tariffa noleggio</th>
                    <td>{{ $money((int) $pricing_totals['tariff_total_cents']) }}</td>
                </tr>
                <tr>
                    <th>Seconda guida</th>
                    <td>{{ $money((int) $pricing_totals['second_driver_cents']) }}</td>
                </tr>
                <tr>
                    <th><strong>Totale</strong></th>
                    <td><strong>{{ $money((int) $pricing_totals['computed_total_cents']) }}</strong></td>
                </tr>
            </table>
        </div>
    @endif

    {{-- NEW — PAGE BREAK PRIMA DELLE CLAUSOLE --}}
    <div class="page-break"></div>

    <div class="box">
        <h2>Condizioni generali di noleggio senza conducente</h2>

        {{-- ✅ NUOVO: stampa da config/rental.php (sezioni) --}}
        @if(!empty($rentalClauseSections) && is_array($rentalClauseSections))
            @foreach($rentalClauseSections as $s)
                <div style="margin-bottom: 14px;">
                    <strong>{{ $s['n'] ?? '' }}.</strong>
                    @if(!empty($s['title']))
                        <strong>{{ $s['title'] }}</strong><br>
                    @endif
                    {!! nl2br(e($s['body'] ?? '')) !!}
                </div>
            @endforeach
        @else
            {{-- ✅ FALLBACK: mantiene la tua struttura attuale (array semplice number => text) --}}
            @foreach($clauses as $number => $text)
                <div style="margin-bottom: 14px;">
                    <strong>{{ $number }}.</strong>
                    {!! nl2br(e($text)) !!}
                </div>
            @endforeach
        @endif
    </div>

    {{-- NEW — FIRME SOLO ALLA FINE --}} 
    <div class="row">
        <div class="col">
            <div class="box">
                <h2>Firma cliente</h2>
                <div class="small muted">La firma sottostante vale come accettazione integrale del contratto e delle condizioni generali.</div>

                @if(!empty($render_signatures) && !empty($signature_customer))
                    <div style="margin-top:8px; border:1px solid #e5e5e5; border-radius:6px; padding:8px; height:80px; display:flex; align-items:center; justify-content:center;">
                        <img
                            src="{{ $signature_customer }}"
                            alt="Firma cliente"
                            style="max-width:100%; max-height:64px; object-fit:contain;"
                        >
                    </div>
                @else
                    <div style="height:80px;border:1px dashed #bbb;margin-top:8px;"></div>
                @endif

                <div class="small">{{ $customer['name'] }}</div>
            </div>
        </div>

        <div class="col">
            <div class="box">
                <h2>Firma noleggiante</h2>
                <div class="small muted">La firma sottostante vale come accettazione integrale del contratto e delle condizioni generali.</div>

                @if(!empty($render_signatures) && !empty($signature_lessor))
                    <div style="margin-top:8px; border:1px solid #e5e5e5; border-radius:6px; padding:8px; height:80px; display:flex; align-items:center; justify-content:center;">
                        <img
                            src="{{ $signature_lessor }}"
                            alt="Firma noleggiante"
                            style="max-width:100%; max-height:64px; object-fit:contain;"
                        >
                    </div>
                @else
                    <div style="height:80px;border:1px dashed #bbb;margin-top:8px;"></div>
                @endif

                <div class="small">{{ $org['name'] }}</div>
            </div>
        </div>
    </div>
</body>
</html>
