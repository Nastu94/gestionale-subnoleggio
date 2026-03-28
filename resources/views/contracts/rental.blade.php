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

    /**
     * =========================================================
     * Helper di visualizzazione
     * =========================================================
     */

    /**
     * Nome noleggiante coerente tra:
     * - intestazione iniziale
     * - box "Noleggiante"
     * - firma noleggiante
     */
    $lessorName = trim((string)($org['name'] ?? ''));
    if ($lessorName === '') {
        $lessorName = trim((string)($vehicle_owner_name ?? ''));
    }

    /**
     * Numero contratto:
     * - per il contratto vuoto resta realmente vuoto
     * - per il contratto reale mostra il valore esistente
     */
    $contractNumber = trim((string)($rental['number_label'] ?? ''));

    /**
     * Data contratto.
     */
    $contractDate = trim((string)($rental['issued_at'] ?? now()->format('d/m/Y')));

    /**
     * Indirizzo noleggiante.
     */
    $orgAddressText = trim(
        (string)($org['address'] ?? '') . ' ' .
        (string)($org['zip'] ?? '') . ' ' .
        (string)($org['city'] ?? '')
    );

    /**
     * Dati AMD Point passati dalla view.
     */
    $pointOrgData = is_array($point_org ?? null) ? $point_org : null;

    /**
     * Indirizzo AMD Point.
     */
    $pointOrgAddressText = trim(
        (string)($pointOrgData['address'] ?? '') . ' ' .
        (string)($pointOrgData['zip'] ?? '') . ' ' .
        (string)($pointOrgData['city'] ?? '')
    );

    /**
     * Indirizzo cliente.
     */
    $customerAddressText = trim(
        (string)($customer['address'] ?? '') . ' ' .
        (string)($customer['zip'] ?? '') . ' ' .
        (string)($customer['city'] ?? '') . ' ' .
        (string)($customer['province'] ?? '')
    );

    /**
     * Ritiro e riconsegna:
     * mostriamo "data @ sede" solo se i pezzi sono effettivamente valorizzati.
     */
    $pickupAt = trim((string)($rental['pickup_at'] ?? ''));
    $pickupLocation = trim((string)($rental['pickup_location'] ?? ''));
    $pickupText = trim(
        $pickupAt .
        (($pickupAt !== '' && $pickupLocation !== '') ? ' @ ' : '') .
        $pickupLocation
    );

    $returnAt = trim((string)($rental['return_at'] ?? ''));
    $returnLocation = trim((string)($rental['return_location'] ?? ''));
    $returnText = trim(
        $returnAt .
        (($returnAt !== '' && $returnLocation !== '') ? ' @ ' : '') .
        $returnLocation
    );

    /**
     * Modalità "contratto vuoto di emergenza".
     *
     * La rileviamo dai principali campi lasciati intenzionalmente vuoti.
     * In questo modo la Blade continua a comportarsi bene anche per i contratti reali.
     */
    $isBlankEmergencyContract =
        $contractNumber === ''
        && trim((string)($customer['name'] ?? '')) === ''
        && trim((string)($vehicle['make'] ?? '')) === ''
        && trim((string)($pricing['days'] ?? '')) === '';

    /**
     * Tariffa:
     * - contratto vuoto => campo vuoto
     * - contratto reale => importo formattato
     */
    $tariffRaw = $pricing_totals['tariff_effective_cents'] ?? $pricing_totals['tariff_total_cents'] ?? null;
    $tariffText = '';

    if (!$isBlankEmergencyContract && $tariffRaw !== null && $tariffRaw !== '') {
        $tariffText = number_format(((int) $tariffRaw) / 100, 2, ',', '.') . ' (IVA incl.)';
    }

    /**
     * Giorni noleggio.
     */
    $daysText = trim((string)($pricing['days'] ?? ''));

    /**
     * Chilometraggio incluso:
     * - contratto vuoto => vuoto
     * - contratto reale => logica esistente
     */
    $includedKmText = '';

    if (!$isBlankEmergencyContract) {
        if (is_null($pricing['km_daily_limit'] ?? null)) {
            $includedKmText = 'Illimitato.';
        } else {
            $includedKmText =
                'Limite giornaliero: ' . ($pricing['km_daily_limit'] ?? '') . ' km × ' . ($pricing['days'] ?? '?') . ' giorno/i' .
                ' = ' . ($pricing['included_km_total'] ?? '') . ' km inclusi totali.' .
                ' Oltre tale soglia: ' . ($pricing['extra_km_rate'] ?? '') . ' €/km.';
        }
    }

    /**
     * Coperture e franchigie:
     * - contratto vuoto => celle completamente vuote
     * - contratto reale => logica esistente
     */
    $rcaText = '';
    $kaskoText = '';
    $furtoIncendioText = '';
    $cristalliText = '';
    $assistenzaText = '';
    $depositText = '';

    /**
     * Helper per stampare una riga compilabile manualmente.
     *
     * - Se il valore esiste, mostra il valore.
     * - Se siamo nel contratto vuoto, mostra una linea scrivibile.
     * - Negli altri casi lascia vuoto.
     */
    $writeLine = function ($value = null, string $width = '180px', string $minHeight = '18px') use ($isBlankEmergencyContract): string {
        $value = trim((string) $value);

        if ($value !== '') {
            return e($value);
        }

        if ($isBlankEmergencyContract) {
            return '<span class="write-line" style="width: '.$width.'; min-height: '.$minHeight.';"></span>';
        }

        return '';
    };

    /**
     * Helper per stampare un blocco compilabile manualmente.
     *
     * Utile nei box testuali come "Cliente".
     */
    $writeBlock = function ($value = null, string $minHeight = '20px') use ($isBlankEmergencyContract): string {
        $value = trim((string) $value);

        if ($value !== '') {
            return '<div class="write-block-text" style="min-height: '.$minHeight.';">'.e($value).'</div>';
        }

        if ($isBlankEmergencyContract) {
            return '<div class="write-block" style="min-height: '.$minHeight.';"></div>';
        }

        return '';
    };

    if (!$isBlankEmergencyContract) {
        $rcaText = 'Inclusa (obbligatoria)';
        if (isset($franchigie['rca']) && $franchigie['rca'] !== null && $franchigie['rca'] !== '') {
            $rcaText .= ' — Franchigia: € ' . number_format((float) $franchigie['rca'], 2, ',', '.');
        }

        $kaskoText = !empty($coverages['kasko']) ? 'Inclusa' : 'Non inclusa';
        if (!empty($coverages['kasko']) && isset($franchigie['kasko']) && $franchigie['kasko'] !== null && $franchigie['kasko'] !== '') {
            $kaskoText .= ' — Franchigia: € ' . number_format((float) $franchigie['kasko'], 2, ',', '.');
        }

        $furtoIncendioText = !empty($coverages['furto_incendio']) ? 'Inclusa' : 'Non inclusa';
        if (!empty($coverages['furto_incendio']) && isset($franchigie['furto_incendio']) && $franchigie['furto_incendio'] !== null && $franchigie['furto_incendio'] !== '') {
            $furtoIncendioText .= ' — Franchigia: € ' . number_format((float) $franchigie['furto_incendio'], 2, ',', '.');
        }

        $cristalliText = !empty($coverages['cristalli']) ? 'Inclusa' : 'Non inclusa';
        if (!empty($coverages['cristalli']) && isset($franchigie['cristalli']) && $franchigie['cristalli'] !== null && $franchigie['cristalli'] !== '') {
            $cristalliText .= ' — Franchigia: € ' . number_format((float) $franchigie['cristalli'], 2, ',', '.');
        }

        $assistenzaText = !empty($coverages['assistenza']) ? 'Inclusa' : 'Non inclusa';
        $depositText = trim((string)($pricing['deposit'] ?? ''));
    }
@endphp

<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Contratto di noleggio{{ $contractNumber !== '' ? ' '.$contractNumber : '' }}</title>
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
        .write-line {
            display: inline-block;
            vertical-align: bottom;
            border-bottom: 1px solid #999;
        }

        .write-block {
            display: block;
            width: 100%;
            border-bottom: 1px solid #999;
        }

        .write-block-text {
            display: block;
            width: 100%;
            min-height: 18px;
        }

        .blank-field {
            margin-bottom: 8px;
        }

        .blank-field-label {
            display: block;
            margin-bottom: 2px;
            font-weight: 700;
        }
        .manual-form-table {
            table-layout: fixed;
            width: 100%;
        }

        .manual-form-table th,
        .manual-form-table td {
            padding: 6px 8px;
            vertical-align: middle;
        }

        .manual-form-table th {
            white-space: nowrap;
        }

        .manual-blank-cell {
            color: transparent;
        }
        .split-box {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .split-box td {
            vertical-align: top;
            padding: 0 8px;
        }

        .split-box td:first-child {
            padding-left: 0;
            border-right: 1px solid #e5e5e5;
        }

        .split-box td:last-child {
            padding-right: 0;
        }

        .split-box-title {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
        }
    </style>
</head>

<body>

    {{-- BOX LOGHI (DOMPDF safe) --}}
    <div style="position:absolute; top:20px; right:20px; width:260px; height:38px; text-align:right;">
        {{-- DOMPDF: layout affiancato più affidabile con table --}}
        <table style="width:100%; height:38px; border-collapse:collapse; table-layout:fixed;">
            <tr>
                <td style="width:50%; text-align:right; vertical-align:middle; padding-right:6px;">
                    @if(!empty($logos['amd']))
                        <img
                            src="{{ $logos['amd'] }}"
                            alt="Logo AMD"
                            style="max-width:120px; max-height:38px; display:inline-block;">
                    @endif
                </td>

                <td style="width:50%; text-align:right; vertical-align:middle;">
                    @if(!empty($logos['era']))
                        <img
                            src="{{ $logos['era'] }}"
                            alt="Logo Era Rent"
                            style="max-width:120px; max-height:38px; display:inline-block;">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <h1>Noleggiante: <strong>{{ $lessorName }}</strong><br></h1>

    <h1>Contratto di noleggio veicolo</h1>

    <div class="muted">
        @if($isBlankEmergencyContract)
            Numero contratto: {!! $writeLine(null, '180px', '18px') !!}
            &nbsp;&nbsp;&nbsp;
            Data: {!! $writeLine(null, '120px', '18px') !!}
        @else
            Numero contratto: <strong>{{ $contractNumber }}</strong>
            @if($contractDate !== '')
                — Data: {{ $contractDate }}
            @endif
        @endif
    </div>

    <div class="row">
        <div class="col">
            <div class="box">
                <h2>Noleggiante</h2>

                @if(!empty($show_dual_lessor_box))
                    <table class="split-box">
                        <tr>
                            {{-- Licenza noleggiante effettivo --}}
                            <td style="width:50%;">
                                <div class="split-box-title">Licenza Noleggiante</div>
                                <div><strong>{{ $lessorName }}</strong></div>
                                <div>P.IVA/CF: {{ $org['vat'] ?? '' }}</div>
                                <div>Indirizzo: {{ $orgAddressText }}</div>
                                <div class="small">{{ $org['phone'] ?? '' }} {{ $org['email'] ?? '' }}</div>
                            </td>

                            {{-- AMD Point --}}
                            <td style="width:50%;">
                                <div class="split-box-title">AMD Point</div>
                                <div><strong>{{ $pointOrgData['name'] ?? '' }}</strong></div>
                                <div>P.IVA/CF: {{ $pointOrgData['vat'] ?? '' }}</div>
                                <div>Indirizzo: {{ $pointOrgAddressText }}</div>
                                <div class="small">{{ $pointOrgData['phone'] ?? '' }} {{ $pointOrgData['email'] ?? '' }}</div>
                            </td>
                        </tr>
                    </table>
                @else
                    <div><strong>{{ $lessorName }}</strong></div>
                    <div>P.IVA/CF: {{ $org['vat'] ?? '' }}</div>
                    <div>Indirizzo: {{ $orgAddressText }}</div>
                    <div class="small">{{ $org['phone'] ?? '' }} {{ $org['email'] ?? '' }}</div>
                @endif
            </div>
        </div>
        <div class="col">
            <div class="box">
                <h2>Cliente</h2>

                @if($isBlankEmergencyContract)
                    <div class="blank-field">
                        <span class="blank-field-label">Nome e cognome / Ragione sociale</span>
                        {!! $writeBlock($customer['name'] ?? '', '22px') !!}
                    </div>

                    <div class="blank-field">
                        <span class="blank-field-label">P.IVA / CF</span>
                        {!! $writeBlock($customer['tax_id'] ?? '', '22px') !!}
                    </div>

                    <div class="blank-field">
                        <span class="blank-field-label">Patente n.</span>
                        {!! $writeBlock($customer['driver_license_number'] ?? '', '22px') !!}
                    </div>

                    <div class="blank-field">
                        <span class="blank-field-label">Documento</span>
                        {!! $writeBlock(trim((string)($customer['doc_id_type'] ?? '').' '.(string)($customer['doc_id_number'] ?? '')), '22px') !!}
                    </div>

                    <div class="blank-field">
                        <span class="blank-field-label">Indirizzo</span>
                        {!! $writeBlock($customerAddressText, '22px') !!}
                    </div>

                    <div class="blank-field">
                        <span class="blank-field-label">Telefono / Email</span>
                        {!! $writeBlock(trim((string)($customer['phone'] ?? '').' '.(string)($customer['email'] ?? '')), '22px') !!}
                    </div>
                @else
                    <div><strong>{{ $customer['name'] ?? '' }}</strong></div>

                    <div>P.IVA/CF: {{ $customer['tax_id'] ?? '' }}</div>
                    <div>Patente n.: {{ $customer['driver_license_number'] ?? '' }}</div>

                    <div>Doc: {{ ($customer['doc_id_type'] ?? '') }} {{ ($customer['doc_id_number'] ?? '') }}</div>

                    <div>
                        Indirizzo:
                        {{ $customerAddressText }}
                    </div>

                    <div class="small">{{ $customer['phone'] ?? '' }} {{ $customer['email'] ?? '' }}</div>
                @endif
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
                    <td>€ {{ number_format(($pricing_totals['second_driver_cents'] ?? 0) / 100, 2, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    @endif

    <div class="box row avoid-break">
        <h2>Veicolo</h2>

        <table class="table {{ $isBlankEmergencyContract ? 'manual-form-table' : '' }}">
            @if($isBlankEmergencyContract)
                {{-- Colonne strette per le etichette, larghe per i valori --}}
                <colgroup>
                    <col style="width: 14%;">
                    <col style="width: 36%;">
                    <col style="width: 14%;">
                    <col style="width: 36%;">
                </colgroup>
            @endif

            <tr>
                <th>Marca</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $vehicle['make'] ?? '' }}
                    @endif
                </td>

                <th>Modello</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $vehicle['model'] ?? '' }}
                    @endif
                </td>
            </tr>

            <tr>
                <th>Targa</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $vehicle['plate'] ?? '' }}
                    @endif
                </td>

                <th>Colore</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $vehicle['color'] ?? '' }}
                    @endif
                </td>
            </tr>

            @if(!$isBlankEmergencyContract && !empty($vehicle['vin']))
                <tr>
                    <th>VIN</th>
                    <td colspan="3">{{ $vehicle['vin'] }}</td>
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

        <table class="table {{ $isBlankEmergencyContract ? 'manual-form-table' : '' }}">
            @if($isBlankEmergencyContract)
                {{-- Stessa logica: etichette strette, campi larghi --}}
                <colgroup>
                    <col style="width: 16%;">
                    <col style="width: 34%;">
                    <col style="width: 16%;">
                    <col style="width: 34%;">
                </colgroup>
            @endif

            <tr>
                <th>Ritiro</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $pickupText }}
                    @endif
                </td>

                <th>Riconsegna</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $returnText }}
                    @endif
                </td>
            </tr>

            <tr>
                <th>Giorni</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $daysText }}
                    @endif
                </td>

                <th>Tariffa</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $tariffText }}
                    @endif
                </td>
            </tr>

            <tr>
                <th>Chilometraggio incluso</th>
                <td colspan="3">
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $includedKmText }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="box row avoid-break">
        <h2>Coperture e franchigie</h2>

        <table class="table {{ $isBlankEmergencyContract ? 'manual-form-table' : '' }}">
            @if($isBlankEmergencyContract)
                {{-- Anche qui privilegiamo lo spazio di scrittura --}}
                <colgroup>
                    <col style="width: 18%;">
                    <col style="width: 32%;">
                    <col style="width: 18%;">
                    <col style="width: 32%;">
                </colgroup>
            @endif

            <tr>
                <th>RC base</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $rcaText }}
                    @endif
                </td>

                <th>Kasko</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $kaskoText }}
                    @endif
                </td>
            </tr>

            <tr>
                <th>Furto/Incendio</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $furtoIncendioText }}
                    @endif
                </td>

                <th>Cristalli</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $cristalliText }}
                    @endif
                </td>
            </tr>

            <tr>
                <th>Assistenza</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $assistenzaText }}
                    @endif
                </td>

                <th>Deposito cauzionale</th>
                <td>
                    @if($isBlankEmergencyContract)
                        <span class="manual-blank-cell">&nbsp;</span>
                    @else
                        {{ $depositText }}
                    @endif
                </td>
            </tr>
        </table>

        <div class="small muted" style="margin-top:6px;">
            Eventuali penali e addebiti saranno compensati sulla cauzione. Le condizioni dettagliate sono riportate nelle clausole generali.
        </div>
    </div>

    @if($second_driver && !empty($pricing_totals))
        @php
            $cur = $pricing_totals['currency'] ?? 'EUR';
            $money = function (int $cents) use ($cur) {
                return number_format($cents / 100, 2, ',', '.') . ' ' . $cur;
            };
        @endphp

        <div class="box row avoid-break">
            <h2>Riepilogo costi</h2>
            <table class="table">
                <tr>
                    <th>Tariffa noleggio</th>
                    <td>{{ $money((int) ($pricing_totals['tariff_effective_cents'] ?? $pricing_totals['tariff_total_cents'] ?? 0)) }}</td>
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

                <div class="small">{{ $customer['name'] ?? '' }}</div>
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

                <div class="small">{{ $lessorName }}</div>
            </div>
        </div>
    </div>
</body>
</html>