{{-- resources/views/contracts/rental.blade.php --}}
{{-- Contratto di noleggio - Bozza PDF
     Nota: questo template NON genera il PDF; verrà usato dal service di generazione.
     I placeholders si aspettano array/DTO con chiavi come sotto descritto. --}}

@php
    /** @var array $org - dati organizzazione (name, vat, address, etc.) */
    /** @var array $rental - dati noleggio (id, planned_pickup_at, planned_return_at, locations) */
    /** @var array $customer - anagrafica cliente */
    /** @var array $vehicle - dati veicolo (make, model, plate, color, vin?) */
    /** @var array $pricing - listino/simulatore (base_total, days, km_daily_limit|null, included_km_total, extra_km_rate, extras[], deposit?) */
    /** @var array $coverages - flags coperture (kasko, furto_incendio, cristalli, assistenza) */
    /** @var array $franchigie - importi franchigie (kasko, furto_incendio, cristalli) */
    /** @var array $clauses - testi standard (responsabilita, utilizzo, coperture, riconsegna, penali, legge_foro) */
@endphp

<!doctype html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title>Contratto di noleggio #{{ $rental['id'] }}</title>
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
        </style>
    </head>
    <body>

        <h1>Contratto di noleggio veicolo</h1>
        <div class="muted">Numero contratto: <strong>#{{ $rental['id'] }}</strong> — Data: {{ $rental['issued_at'] ?? now()->format('d/m/Y') }}</div>

        <div class="row">
            <div class="col">
                <div class="box">
                    <h2>Noleggiante</h2>
                    <div><strong>{{ $org['name'] }}</strong></div>
                    <div>P.IVA/CF: {{ $org['vat'] ?? '—' }}</div>
                    <div>{{ $org['address'] ?? '' }} {{ $org['zip'] ?? '' }} {{ $org['city'] ?? '' }}</div>
                    <div class="small">{{ $org['phone'] ?? '' }} {{ $org['email'] ?? '' }}</div>
                </div>
            </div>
            <div class="col">
                <div class="box">
                    <h2>Cliente</h2>
                    <div><strong>{{ $customer['name'] }}</strong></div>
                    <div>Doc: {{ $customer['doc_id_type'] ?? 'ID' }} {{ $customer['doc_id_number'] ?? '' }}</div>
                    <div>{{ $customer['address'] ?? '' }} {{ $customer['zip'] ?? '' }} {{ $customer['city'] ?? '' }} {{ $customer['province'] ?? '' }}</div>
                    <div class="small">{{ $customer['phone'] ?? '' }} {{ $customer['email'] ?? '' }}</div>
                </div>
            </div>
        </div>

        <div class="box">
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

        <div class="box">
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
                    <td>{{ $pricing['base_total'] ?? '—' }} (IVA incl.)</td>
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
                @if(!empty($pricing['extras']))
                <tr>
                    <th>Extra</th>
                    <td colspan="3">
                        <ul style="margin:4px 0 0 16px; padding:0;">
                            @foreach($pricing['extras'] as $ex)
                                <li>{{ $ex['name'] }} ×{{ $ex['qty'] ?? 1 }} — {{ $ex['total'] ?? $ex['unit'] ?? '' }}</li>
                            @endforeach
                        </ul>
                    </td>
                </tr>
                @endif
            </table>
        </div>

        <div class="box">
            <h2>Coperture e franchigie</h2>
            <table class="table">
                <tr>
                    <th>RC base</th><td>Inclusa (obbligatoria) — Franchigia: € {{ number_format($franchigie['rca'], 2, ',', '.') }}</td>
                    <th>Kasko</th>
                    <td>
                        {{ !empty($coverages['kasko']) ? 'Inclusa' : 'Non inclusa' }}
                        @if(!empty($coverages['kasko']) && isset($franchigie['kasko'])) — Franchigia: € {{ number_format($franchigie['kasko'], 2, ',', '.') }} @endif
                    </td>
                </tr>
                <tr>
                    <th>Furto/Incendio</th>
                    <td>
                        {{ !empty($coverages['furto_incendio']) ? 'Inclusa' : 'Non inclusa' }}
                        @if(!empty($coverages['furto_incendio']) && isset($franchigie['furto_incendio'])) — Franchigia: € {{ number_format($franchigie['furto_incendio'], 2, ',', '.') }} @endif
                    </td>
                    <th>Cristalli</th>
                    <td>
                        {{ !empty($coverages['cristalli']) ? 'Inclusa' : 'Non inclusa' }}
                        @if(!empty($coverages['cristalli']) && isset($franchigie['cristalli'])) — Franchigia: € {{ number_format($franchigie['cristalli'], 2, ',', '.') }} @endif
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

        <div class="box">
            <h2>Clausole generali (sintesi)</h2>
            <div class="small">
                {!! nl2br(e($clauses['responsabilita'] ?? '')) !!}<br><br>
                {!! nl2br(e($clauses['utilizzo'] ?? '')) !!}<br><br>
                {!! nl2br(e($clauses['coperture'] ?? '')) !!}<br><br>
                {!! nl2br(e($clauses['riconsegna'] ?? '')) !!}<br><br>
                {{-- Chilometraggio overage esplicito --}}
                Qualora i km percorsi superino i km inclusi totali, l’eccedenza sarà addebitata al prezzo al km extra indicato nel presente contratto.<br><br>
                {!! nl2br(e($clauses['penali'] ?? '')) !!}
            </div>
        </div>

        <div class="row">
            <div class="col">
                <div class="box">
                    <h2>Firma cliente</h2>
                    <div class="small muted">Verrà acquisita digitalmente o manualmente in fase di ritiro.</div>
                    <div style="height:80px;border:1px dashed #bbb;margin-top:8px;"></div>
                    <div class="small">{{ $customer['name'] }}</div>
                </div>
            </div>
            <div class="col">
                <div class="box">
                    <h2>Firma noleggiante</h2>
                    <div class="small muted">Verrà acquisita digitalmente o manualmente in fase di ritiro.</div>
                    <div style="height:80px;border:1px dashed #bbb;margin-top:8px;"></div>
                    <div class="small">{{ $org['name'] }}</div>
                </div>
            </div>
        </div>

    </body>
</html>
