@php
    /**
     * Formattazione importi in EUR (cents -> €).
     */
    $fmt = fn(int $cents) => number_format($cents / 100, 2, ',', '.') . ' €';

    /**
     * Formattazione importi con segno, utile per sconti/arrotondamenti.
     */
    $fmtSigned = function (int $cents) use ($fmt) {
        if ($cents === 0) return $fmt(0);
        $sign = $cents > 0 ? '+' : '−';
        return $sign . ' ' . $fmt(abs($cents));
    };

    // Identificazione veicolo “robusta”
    $plate = $vehicle->plate ?? $vehicle->license_plate ?? $vehicle->targa ?? null;
    $title = trim(($plate ? $plate.' — ' : '') . ($vehicle->name ?? $vehicle->model ?? 'Veicolo'));

    $days = (int) ($quote['days'] ?? 0);

    $dailyTotal = (int) ($quote['daily_total'] ?? 0);
    $kmExtra    = (int) ($quote['km_extra'] ?? 0);

    $subtotalDaysBeforeTier = (int) ($quote['subtotal_days_before_tier'] ?? $dailyTotal);
    $subtotalDaysAfterTier  = (int) ($quote['subtotal_days_after_tier'] ?? $subtotalDaysBeforeTier);
    $tierAdj               = (int) ($quote['tier_adjustment_cents'] ?? 0);

    $includedKm = (int) ($quote['km_included_total'] ?? 0);
    $excessKm   = (int) ($quote['km_excess'] ?? 0);
    $extraKmPer = (int) ($quote['extra_km_cents_per_km'] ?? 0);

    $roundingDelta = (int) ($quote['rounding_delta_cents'] ?? 0);

    $total   = (int) ($quote['total'] ?? 0);
    $deposit = (int) ($quote['deposit'] ?? 0);

    $tier = $quote['tier'] ?? null;

    // Etichetta tier (solo descrittiva)
    $tierLabel = null;
    if (is_array($tier)) {
        if (!empty($tier['override_daily_cents'])) {
            $tierLabel = 'Override tariffa giornaliera';
        } elseif (!empty($tier['discount_pct'])) {
            $tierLabel = 'Sconto durata ' . (int) $tier['discount_pct'] . '%';
        } else {
            $tierLabel = 'Tier durata';
        }
        if (!empty($tier['name'])) {
            $tierLabel .= ' — ' . $tier['name'];
        }
    }
@endphp

<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Preventivo</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .muted { color: #666; }
        .box { border: 1px solid #ddd; border-radius: 6px; padding: 12px; }
        .h1 { font-size: 18px; font-weight: 700; margin: 0 0 6px 0; }
        .small { font-size: 10px; }
        .section-title { font-size: 13px; font-weight: 700; margin: 14px 0 6px; }
        .row { width: 100%; }
        .row td { vertical-align: top; padding: 4px 0; }
        table.kv { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.kv th { text-align: left; font-weight: 700; padding: 6px 0; border-bottom: 1px solid #ddd; }
        table.kv td { padding: 6px 0; border-bottom: 1px solid #eee; }
        .right { text-align: right; }
        .center { text-align: center; }
        .total { font-size: 16px; font-weight: 700; }
        .note { margin-top: 10px; line-height: 1.4; }
        .pill { display: inline-block; padding: 2px 6px; border: 1px solid #ddd; border-radius: 10px; font-size: 10px; }
        /* Tabella leggibile in Dompdf */
        .tbl {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* fondamentale: stabilizza le colonne */
            margin-top: 6px;
        }

        .tbl th, .tbl td {
            padding: 7px 6px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        /* Header più compatto e leggibile */
        .tbl thead th {
            font-weight: 700;
            font-size: 10px;
            line-height: 1.15;
            border-bottom: 1px solid #ddd;
        }

        /* Corpo tabella */
        .tbl tbody td {
            font-size: 11px;
            line-height: 1.25;
        }

        /* Numeri: mai a capo */
        .tbl .num {
            text-align: right;
            white-space: nowrap;
        }

        /* Centra senza andare a capo */
        .tbl .center {
            text-align: center;
            white-space: nowrap;
        }

        .tbl .muted {
            color: #666;
        }

        .tbl .small {
            font-size: 10px;
        }

        /* Larghezze colonne (somma 100%) */
        .w-season { width: 30%; }
        .w-type   { width: 9%; }
        .w-days   { width: 6%; }
        .w-comp   { width: 8%; }   /* 4 colonne = 32% */
        .w-rate   { width: 10%; }
        .w-total  { width: 13%; }

        /* righe alternate per lettura */
        .tbl tbody tr:nth-child(even) { background: #fafafa; }

        /* riga subtotal evidenziata */
        .tbl .subtotal td {
            border-top: 1px solid #ddd;
            font-weight: 700;
            background: #f5f5f5;
        }

    </style>
</head>
<body>
<div class="box">
    <div class="h1">Preventivo noleggio</div>
    <div class="muted small">
        Generato il {{ $generatedAt->format('d/m/Y H:i') }}
    </div>

    <table class="row" cellspacing="0" cellpadding="0">
        <tr>
            <td style="width: 60%;">
                <div style="margin-top: 10px;">
                    <strong>Veicolo</strong><br>
                    {{ $title }}<br>
                    <span class="muted">
                        Listino: {{ $pricelist->name ?? '—' }}
                    </span>
                </div>
            </td>
            <td style="width: 40%;">
                <div style="margin-top: 10px;">
                    <strong>Periodo</strong><br>
                    Ritiro: {{ \Carbon\CarbonImmutable::instance($pickupAt)->format('d/m/Y H:i') }}<br>
                    Riconsegna: {{ \Carbon\CarbonImmutable::instance($dropoffAt)->format('d/m/Y H:i') }}<br>
                    Giorni: {{ $days }}<br>
                    Km previsti: {{ number_format((int) $expectedKm, 0, ',', '.') }}
                </div>
            </td>
        </tr>
    </table>

    {{-- ===================== DETTAGLIO QUOTA GIORNI ===================== --}}
    <div class="section-title">Dettaglio quota giorni</div>
    <div class="muted small">
        La tariffa giornaliera è calcolata in modo <strong>additivo sulla base</strong>:
        Base + Weekend listino + Stagione + Weekend stagione (se applicabile).
    </div>

<table class="tbl" cellspacing="0" cellpadding="0">
    <thead>
        <tr>
            <th class="w-season">Stagione</th>
            <th class="center w-type">Tipo</th>
            <th class="center w-days">Giorni</th>

            {{-- Componenti €/giorno (etichette corte per non comprimere) --}}
            <th class="num w-comp">Base</th>
            <th class="num w-comp">+W</th>
            <th class="num w-comp">+Stag</th>
            <th class="num w-comp">+W stag</th>

            <th class="num w-rate">€/g</th>
            <th class="num w-total">Totale</th>
        </tr>
    </thead>

        <tbody>
            @php $sumGroups = 0; @endphp

            @forelse(($quote['day_groups'] ?? []) as $g)
                @php
                    $sumGroups += (int) ($g['total_cents'] ?? 0);
                    $isWe = (bool) ($g['is_weekend'] ?? false);
                    $typeLabel = $isWe ? 'Weekend' : 'Feriale';

                    $seasonPct = (int) ($g['season_pct'] ?? 0);
                    $seasonWk  = $g['season_weekend_pct'] ?? null;
                    $baseWkPct = (int) ($g['weekend_pct'] ?? 0);
                @endphp

                <tr>
                    <td class="col-season">
                        <strong>{{ $g['season_name'] ?? 'Base' }}</strong><br>
                        <span class="muted small">
                            @if($seasonPct !== 0)
                                Stagione {{ $seasonPct }}%
                            @endif

                            @if($isWe && !is_null($seasonWk))
                                @if($seasonPct !== 0) · @endif
                                W stag. {{ (int) $seasonWk }}%
                            @endif

                            @if($isWe && $baseWkPct !== 0)
                                @if($seasonPct !== 0 || (!is_null($seasonWk))) · @endif
                                W base {{ $baseWkPct }}%
                            @endif
                        </span>
                    </td>

                    <td class="center col-type">{{ $typeLabel }}</td>
                    <td class="center col-days">{{ (int) ($g['days'] ?? 0) }}</td>

                    <td class="num col-part">{{ $fmt((int) ($g['base_daily_cents'] ?? 0)) }}</td>
                    <td class="num col-part">{{ $fmt((int) ($g['add_weekend_base'] ?? 0)) }}</td>
                    <td class="num col-part">{{ $fmt((int) ($g['add_season'] ?? 0)) }}</td>
                    <td class="num col-part">{{ $fmt((int) ($g['add_season_weekend'] ?? 0)) }}</td>

                    <td class="num col-rate">{{ $fmt((int) ($g['daily_cents'] ?? 0)) }}</td>
                    <td class="num col-total">{{ $fmt((int) ($g['total_cents'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="muted">Nessun dettaglio disponibile.</td>
                </tr>
            @endforelse

            <tr class="subtotal">
                <td colspan="8" class="num">
                    Totale quota giorni (prima del tier)
                </td>
                <td class="num">{{ $fmt($dailyTotal) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ===================== RIEPILOGO CALCOLO ===================== --}}
    <div class="section-title">Riepilogo calcolo</div>

    <table class="kv" cellspacing="0" cellpadding="0">
        <tr>
            <th>Voce</th>
            <th class="right">Importo</th>
        </tr>

        <tr>
            <td>Quota giorni (prima del tier)</td>
            <td class="right">{{ $fmt($subtotalDaysBeforeTier) }}</td>
        </tr>

        <tr>
            <td>
                Tier durata
                @if($tierLabel)
                    <span class="muted">({{ $tierLabel }})</span>
                @endif
            </td>
            <td class="right">
                {{ $tierAdj === 0 ? '—' : $fmtSigned($tierAdj) }}
            </td>
        </tr>

        <tr>
            <td><strong>Quota giorni (dopo tier)</strong></td>
            <td class="right"><strong>{{ $fmt($subtotalDaysAfterTier) }}</strong></td>
        </tr>

        <tr>
            <td>
                Km extra
                <div class="muted small">
                    Inclusi: {{ number_format($includedKm, 0, ',', '.') }} km —
                    Eccedenza: {{ number_format($excessKm, 0, ',', '.') }} km —
                    Costo: {{ $fmt($extraKmPer) }}/km
                </div>
                <div class="muted small">
                    Nota: i km extra vengono sommati <strong>dopo</strong> l’applicazione del tier.
                </div>
            </td>
            <td class="right">{{ $kmExtra > 0 ? $fmt($kmExtra) : '—' }}</td>
        </tr>

        <tr>
            <td>Arrotondamento</td>
            <td class="right">{{ $roundingDelta === 0 ? '—' : $fmtSigned($roundingDelta) }}</td>
        </tr>

        <tr>
            <td class="total">Totale noleggio</td>
            <td class="right total">{{ $fmt($total) }}</td>
        </tr>

        <tr>
            <td>Cauzione (deposito)</td>
            <td class="right">{{ $fmt($deposit) }}</td>
        </tr>
    </table>

    <div class="muted small note">
        Il totale noleggio non include la cauzione. La cauzione è un deposito rimborsabile secondo i termini contrattuali.
        Il preventivo è indicativo e può variare in base a disponibilità, condizioni e conferma finale.
    </div>
</div>
</body>
</html>
