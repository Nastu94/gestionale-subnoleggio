<?php

namespace App\Services\Cargos;

use App\Models\Customer;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/**
 * Builder del payload CARGOS (mappatura campi) + validazione.
 *
 * Responsabilità:
 * - Prendere il contesto prodotto da CargosRentalContextResolver
 * - Costruire l'array chiave=>valore con i campi CARGOS
 * - Validare presenza e coerenza dei dati (errori bloccanti vs opzionali)
 *
 * NOTA:
 * - Qui NON produciamo ancora la stringa a larghezza fissa del tracciato record.
 *   Step successivo: formatter fixed-width.
 */
class CargosContractPayloadBuilder
{
        /**
     * Metadati per errori “umani”.
     * - label: come chiamare il campo in modo leggibile
     * - hint: cosa fare per risolvere
     *
     * @var array<string, array{label:string, hint:string}>
     */
    protected const FIELD_META = [
        // Contratto
        'contratto_id' => [
            'label' => 'Identificativo contratto univoco',
            'hint'  => 'Verifica che rentals.number_id e rentals.actual_pickup_at siano presenti.',
        ],
        'contratto_data' => [
            'label' => 'Data contratto',
            'hint'  => 'Assicurati che actual_pickup_at sia valorizzato (checklist di uscita).',
        ],
        'contratto_checkout_data' => [
            'label' => 'Data consegna (checkout)',
            'hint'  => 'Assicurati che actual_pickup_at sia valorizzato (checklist di uscita).',
        ],
        'CONTRATTO_CHECKOUT_LUOGO_COD' => [
            'label' => 'Codice luogo consegna (checkout)',
            'hint'  => 'Compila locations.police_place_code sulla sede di consegna.',
        ],
        'CONTRATTO_CHECKOUT_INDIRIZZO' => [
            'label' => 'Indirizzo consegna (checkout)',
            'hint'  => 'Compila almeno address_line sulla sede (gli altri campi sono opzionali).',
        ],
        'CONTRATTO_CHECKIN_DATA' => [
            'label' => 'Data prevista rientro (check-in)',
            'hint'  => 'Compila rentals.planned_return_at (alla stipula non hai actual_return_at).',
        ],
        'CONTRATTO_CHECKIN_LUOGO_COD' => [
            'label' => 'Codice luogo rientro (check-in)',
            'hint'  => 'Compila locations.police_place_code sulla sede di rientro.',
        ],
        'CONTRATTO_CHECKIN_INDIRIZZO' => [
            'label' => 'Indirizzo rientro (check-in)',
            'hint'  => 'Compila almeno address_line sulla sede (gli altri campi sono opzionali).',
        ],

        // Operatore / Agenzia
        'OPERATORE_ID' => [
            'label' => 'Identificativo operatore',
            'hint'  => 'Verifica che esista un utente operatore (Auth o passato al service).',
        ],
        'AGENZIA_NOME' => [
            'label' => 'Nome agenzia (organizzazione che stipula)',
            'hint'  => 'Compila organizations.legal_name.',
        ],
        'AGENZIA_LUOGO_COD' => [
            'label' => 'Codice luogo agenzia (Questura/Comune)',
            'hint'  => 'Compila organizations.police_place_code nella scheda Organizzazione (sezione CARGOS).',
        ],
        'AGENZIA_INDIRIZZO' => [
            'label' => 'Indirizzo agenzia',
            'hint'  => 'Compila organizations.address_line (gli altri campi sono opzionali).',
        ],
        'AGENZIA_RECAPITO_TEL' => [
            'label' => 'Telefono agenzia',
            'hint'  => 'Compila organizations.phone (solo numeri; eventuali simboli verranno rimossi).',
        ],

        // Veicolo
        'VEICOLO_TIPO' => [
            'label' => 'Tipologia veicolo (codice CARGOS)',
            'hint'  => 'Compila vehicles.cargos_vehicle_type_code (1 carattere, es. "0").',
        ],
        'VEICOLO_MARCA' => [
            'label' => 'Marca veicolo',
            'hint'  => 'Compila vehicles.make.',
        ],
        'VEICOLO_MODELLO' => [
            'label' => 'Modello veicolo',
            'hint'  => 'Compila vehicles.model.',
        ],
        'VEICOLO_TARGA' => [
            'label' => 'Targa veicolo',
            'hint'  => 'Compila vehicles.plate.',
        ],

        // Contraente
        'CONDUCENTE_CONTRAENTE_COGNOME' => [
            'label' => 'Cognome contraente',
            'hint'  => 'Compila customers.last_name.',
        ],
        'CONDUCENTE_CONTRAENTE_NOME' => [
            'label' => 'Nome contraente',
            'hint'  => 'Compila customers.first_name.',
        ],
        'CONDUCENTE_CONTRAENTE_NASCITA_DATA' => [
            'label' => 'Data di nascita contraente',
            'hint'  => 'Compila customers.birthdate.',
        ],
        'CONDUCENTE_CONTRAENTE_NASCITA_LUOGO_COD' => [
            'label' => 'Codice luogo di nascita contraente',
            'hint'  => 'Compila customers.birth_place_code.',
        ],
        'CONDUCENTE_CONTRAENTE_CITTADINANZA_COD' => [
            'label' => 'Codice cittadinanza contraente',
            'hint'  => 'Compila customers.citizenship_cargos_code.',
        ],
        'CONDUCENTE_CONTRAENTE_RESIDENZA_LUOGO_COD' => [
            'label' => 'Codice luogo residenza contraente',
            'hint'  => 'Compila customers.police_place_code (come da tua regola, deve esserci sempre).',
        ],
        'CONDUCENTE_CONTRAENTE_RESIDENZA_INDIRIZZO' => [
            'label' => 'Indirizzo residenza contraente',
            'hint'  => 'Compila customers.address_line (se estero puoi mettere tutto lì).',
        ],
        'CONDUCENTE_CONTRAENTE_DOCIDE_TIPO_COD' => [
            'label' => 'Tipo documento identità (codice CARGOS)',
            'hint'  => 'Compila customers.identity_document_type_code.',
        ],
        'CONDUCENTE_CONTRAENTE_DOCIDE_NUMERO' => [
            'label' => 'Numero documento identità',
            'hint'  => 'Compila customers.doc_id_number.',
        ],
        'CONDUCENTE_CONTRAENTE_DOCIDE_LUOGORIL_COD' => [
            'label' => 'Codice luogo rilascio documento identità',
            'hint'  => 'Compila customers.identity_document_place_code.',
        ],
        'CONDUCENTE_CONTRAENTE_PATENTE_NUMERO' => [
            'label' => 'Numero patente',
            'hint'  => 'Compila customers.driver_license_number.',
        ],
        'CONDUCENTE_CONTRAENTE_PATENTE_LUOGORIL_COD' => [
            'label' => 'Codice luogo rilascio patente',
            'hint'  => 'Compila customers.driver_license_place_code.',
        ],
    ];

    /**
     * Crea un contesto leggibile per errori (es. “Organizzazione #1 ‘Sodano Consulting’”).
     *
     * @param  string $entityName
     * @param  int|string|null $id
     * @param  string|null $label
     * @return string
     */
    protected function ctx(string $entityName, int|string|null $id, ?string $label): string
    {
        $parts = [$entityName];

        if (!is_null($id)) {
            $parts[] = "#{$id}";
        }

        $label = is_string($label) ? trim($label) : '';
        if ($label !== '') {
            $parts[] = "“{$label}”";
        }

        return implode(' ', $parts);
    }

    /**
     * Costruisce il payload CARGOS e ritorna:
     * - ok: bool
     * - payload: array<string, mixed>
     * - errors: array<int, string> (errori bloccanti)
     *
     * @param  array<string,mixed> $ctx
     * @return array{ok:bool,payload:array<string,mixed>,errors:array<int,string>}
     */
    public function build(array $ctx): array
    {
        $errors = [];

        /** @var \App\Models\Rental $rental */
        $rental = $ctx['rental'];

        /** @var \App\Models\Vehicle $vehicle */
        $vehicle = $ctx['vehicle'];

        /** @var \App\Models\Customer $customer */
        $customer = $ctx['customer'];

        /** @var \App\Models\Customer|null $secondDriver */
        $secondDriver = $ctx['second_driver'] ?? null;

        /** @var \App\Models\Location $pickup */
        $pickup = $ctx['pickup_location'];

        /** @var \App\Models\Location $return */
        $return = $ctx['return_location'];

        /** @var \App\Models\Organization $agency */
        $agency = $ctx['agency'];

        /** @var \App\Models\User $operator */
        $operator = $ctx['operator'];

        /** @var array<string,string> $derived */
        $derived = $ctx['derived'];

        // -------------------------
        // Required: contratto / luoghi
        // -------------------------

        $contrattoId = $this->sanitizeCharset4((string) ($derived['contract_id_unique'] ?? ''), 50);
        $this->requireFilled($contrattoId, 'contratto_id', $errors);

        $contrattoData = $this->formatCargosDate16($rental->actual_pickup_at);
        $this->requireFilled($contrattoData, 'contratto_data', $errors);

        // Pagamento previsto (fisso)
        $contrattoTipoP = '0';

        $checkoutData = $this->formatCargosDate16($rental->actual_pickup_at);
        $this->requireFilled($checkoutData, 'contratto_checkout_data', $errors);

        $checkoutLuogoCod = $this->digitsOnly($pickup->police_place_code);
        $this->requireFilled(
            $checkoutLuogoCod,
            'CONTRATTO_CHECKOUT_LUOGO_COD',
            $errors,
            $this->ctx('Sede consegna', $pickup->id, $pickup->name)
        );

        $checkoutIndirizzo = $this->sanitizeCharset4((string) ($derived['checkout_address'] ?? ''), 200);
        $this->requireFilled($checkoutIndirizzo, 'CONTRATTO_CHECKOUT_INDIRIZZO', $errors);

        // Check-in: come da tua regola -> planned_return_at (alla stipula non hai actual_return_at)
        $checkinData = $this->formatCargosDate16($rental->planned_return_at);
        $this->requireFilled($checkinData, 'CONTRATTO_CHECKIN_DATA', $errors);

        $checkinLuogoCod = $this->digitsOnly($return->police_place_code);
        $this->requireFilled(
            $checkinLuogoCod,
            'CONTRATTO_CHECKIN_LUOGO_COD',
            $errors,
            $this->ctx('Sede rientro', $return->id, $return->name)
        );

        $checkinIndirizzo = $this->sanitizeCharset4((string) ($derived['checkin_address'] ?? ''), 200);
        $this->requireFilled($checkinIndirizzo, 'CONTRATTO_CHECKIN_INDIRIZZO', $errors);

        // -------------------------
        // Operatore
        // -------------------------

        $operatoreId = $this->sanitizeCharset4(((string) $operator->id) . '-' . ((string) $operator->name), 50);
        $this->requireFilled($operatoreId, 'OPERATORE_ID', $errors);

        // -------------------------
        // Agenzia
        // -------------------------

        // Placeholder fisso per ora
        $agenziaId = 'QU1E';

        $agenziaNome = $this->sanitizeCharset4((string) ($agency->legal_name ?? ''), 70);
        $this->requireFilled($agenziaNome, 'AGENZIA_NOME', $errors);

        $agenziaLuogoCod = $this->digitsOnly($agency->police_place_code);
        $this->requireFilled(
            $agenziaLuogoCod,
            'AGENZIA_LUOGO_COD',
            $errors,
            $this->ctx('Organizzazione', $agency->id, $agency->legal_name)
        );

        $agenziaIndirizzo = $this->sanitizeCharset4((string) ($derived['agency_address'] ?? ''), 200);
        $this->requireFilled($agenziaIndirizzo, 'AGENZIA_INDIRIZZO', $errors);

        $agenziaTel = $this->digitsOnly($agency->phone);
        $this->requireFilled($agenziaTel, 'AGENZIA_RECAPITO_TEL', $errors);

        // -------------------------
        // Veicolo
        // -------------------------

        $veicoloTipo = $this->sanitizeCharset4((string) ($vehicle->cargos_vehicle_type_code ?? ''), 1);
        $this->requireFilled($veicoloTipo, 'VEICOLO_TIPO', $errors);

        $veicoloMarca = $this->sanitizeCharset4((string) ($vehicle->make ?? ''), 50);
        $this->requireFilled($veicoloMarca, 'VEICOLO_MARCA', $errors);

        $veicoloModello = $this->sanitizeCharset4((string) ($vehicle->model ?? ''), 50);
        $this->requireFilled($veicoloModello, 'VEICOLO_MODELLO', $errors);

        $veicoloTarga = $this->sanitizeCharset4((string) ($vehicle->plate ?? ''), 20);
        $this->requireFilled($veicoloTarga, 'VEICOLO_TARGA', $errors);

        // Non obbligatorio
        $veicoloColore = $this->sanitizeCharset4((string) ($vehicle->color ?? ''), 30);

        // Non registrati -> lasciamo stringa vuota
        $veicoloGps = '';
        $veicoloBloccoM = '';

        // -------------------------
        // Conducente / Contraente (Customer)
        // -------------------------

        $this->requireFilled((string) $customer->last_name, 'CONDUCENTE_CONTRAENTE_COGNOME', $errors);
        $this->requireFilled((string) $customer->first_name, 'CONDUCENTE_CONTRAENTE_NOME', $errors);

        $nascitaData = $this->formatCargosDate16($customer->birthdate ? Carbon::parse($customer->birthdate)->startOfDay() : null);
        // Nota: CARGOS per la nascita spesso è solo data; qui restiamo coerenti con Date16, poi nel formatter adeguiamo se serve.
        $this->requireFilled($nascitaData, 'CONDUCENTE_CONTRAENTE_NASCITA_DATA', $errors);

        $nascitaLuogoCod = $this->digitsOnly($customer->birth_place_code);
        $this->requireFilled($nascitaLuogoCod, 'CONDUCENTE_CONTRAENTE_NASCITA_LUOGO_COD', $errors);

        $cittadinanzaCod = $this->digitsOnly($customer->citizenship_cargos_code);
        $this->requireFilled(
            $cittadinanzaCod,
            'CONDUCENTE_CONTRAENTE_CITTADINANZA_COD',
            $errors,
            $this->ctx('Cliente', $customer->id, $customer->name)
        );

        $resLuogoCod = $this->digitsOnly($customer->police_place_code);
        $this->requireFilled(
            $resLuogoCod,
            'CONDUCENTE_CONTRAENTE_RESIDENZA_LUOGO_COD',
            $errors,
            $this->ctx('Cliente', $customer->id, $customer->name)
        );

        $resIndirizzo = $this->sanitizeCharset4((string) ($derived['customer_residence_address'] ?? ''), 200);
        $this->requireFilled($resIndirizzo, 'CONDUCENTE_CONTRAENTE_RESIDENZA_INDIRIZZO', $errors);

        $docTipoCod = $this->sanitizeCharset4((string) ($customer->identity_document_type_code ?? ''), 10);
        $this->requireFilled($docTipoCod, 'CONDUCENTE_CONTRAENTE_DOCIDE_TIPO_COD', $errors);

        $docNumero = $this->sanitizeCharset4((string) ($customer->doc_id_number ?? ''), 30);
        $this->requireFilled($docNumero, 'CONDUCENTE_CONTRAENTE_DOCIDE_NUMERO', $errors);

        $docLuogoCod = $this->digitsOnly($customer->identity_document_place_code);
        $this->requireFilled(
            $docLuogoCod, 
            'CONDUCENTE_CONTRAENTE_DOCIDE_LUOGORIL_COD', 
            $errors,
            $this->ctx('Cliente', $customer->id, $customer->name)
        );

        $patNumero = $this->sanitizeCharset4((string) ($customer->driver_license_number ?? ''), 30);
        $this->requireFilled($patNumero, 'CONDUCENTE_CONTRAENTE_PATENTE_NUMERO', $errors);

        $patLuogoCod = $this->digitsOnly($customer->driver_license_place_code);
        $this->requireFilled(
            $patLuogoCod, 
            'CONDUCENTE_CONTRAENTE_PATENTE_LUOGORIL_COD', 
            $errors,
            $this->ctx('Cliente', $customer->id, $customer->name)
        );

        // Non obbligatorio
        $contraenteRecapito = $this->digitsOnly($customer->phone);

        // -------------------------
        // Secondo conducente (all-or-nothing)
        // -------------------------

        $second = $this->buildSecondDriverBlock($secondDriver, $errors);

        // -------------------------
        // Payload finale
        // -------------------------

        $payload = [
            // Contratto
            'contratto_id' => $contrattoId,
            'contratto_data' => $contrattoData,
            'contratto_tipop' => $contrattoTipoP,
            'contratto_checkout_data' => $checkoutData,
            'CONTRATTO_CHECKOUT_LUOGO_COD' => $checkoutLuogoCod,
            'CONTRATTO_CHECKOUT_INDIRIZZO' => $checkoutIndirizzo,
            'CONTRATTO_CHECKIN_DATA' => $checkinData,
            'CONTRATTO_CHECKIN_LUOGO_COD' => $checkinLuogoCod,
            'CONTRATTO_CHECKIN_INDIRIZZO' => $checkinIndirizzo,

            // Operatore / Agenzia
            'OPERATORE_ID' => $operatoreId,
            'AGENZIA_ID' => $agenziaId,
            'AGENZIA_NOME' => $agenziaNome,
            'AGENZIA_LUOGO_COD' => $agenziaLuogoCod,
            'AGENZIA_INDIRIZZO' => $agenziaIndirizzo,
            'AGENZIA_RECAPITO_TEL' => $agenziaTel,

            // Veicolo
            'VEICOLO_TIPO' => $veicoloTipo,
            'VEICOLO_MARCA' => $veicoloMarca,
            'VEICOLO_MODELLO' => $veicoloModello,
            'VEICOLO_TARGA' => $veicoloTarga,
            'VEICOLO_COLORE' => $veicoloColore,
            'VEICOLO_GPS' => $veicoloGps,
            'VEICOLO_BLOCCOM' => $veicoloBloccoM,

            // Conducente / Contraente
            'CONDUCENTE_CONTRAENTE_COGNOME' => $this->sanitizeCharset4((string) $customer->last_name, 50),
            'CONDUCENTE_CONTRAENTE_NOME' => $this->sanitizeCharset4((string) $customer->first_name, 50),
            'CONDUCENTE_CONTRAENTE_NASCITA_DATA' => $nascitaData,
            'CONDUCENTE_CONTRAENTE_NASCITA_LUOGO_COD' => $nascitaLuogoCod,
            'CONDUCENTE_CONTRAENTE_CITTADINANZA_COD' => $cittadinanzaCod,
            'CONDUCENTE_CONTRAENTE_RESIDENZA_LUOGO_COD' => $resLuogoCod,
            'CONDUCENTE_CONTRAENTE_RESIDENZA_INDIRIZZO' => $resIndirizzo,
            'CONDUCENTE_CONTRAENTE_DOCIDE_TIPO_COD' => $docTipoCod,
            'CONDUCENTE_CONTRAENTE_DOCIDE_NUMERO' => $docNumero,
            'CONDUCENTE_CONTRAENTE_DOCIDE_LUOGORIL_COD' => $docLuogoCod,
            'CONDUCENTE_CONTRAENTE_PATENTE_NUMERO' => $patNumero,
            'CONDUCENTE_CONTRAENTE_PATENTE_LUOGORIL_COD' => $patLuogoCod,
            'CONDUCENTE_CONTRAENTE_RECAPITO' => $contraenteRecapito,

            // Second driver (tutto vuoto se assente)
            ...$second,
        ];

        return [
            'ok' => count($errors) === 0,
            'payload' => $payload,
            'errors' => $errors,
        ];
    }

    /**
     * Costruisce il blocco del secondo conducente.
     * Se presente, deve essere completo (all-or-nothing).
     *
     * @param  Customer|null $secondDriver
     * @param  array<int,string> $errors
     * @return array<string,string>
     */
    protected function buildSecondDriverBlock(?Customer $secondDriver, array &$errors): array
    {
        $empty = [
            'CONDUCENTE2_COGNOME' => '',
            'CONDUCENTE2_NOME' => '',
            'CONDUCENTE2_NASCITA_DATA' => '',
            'CONDUCENTE2_NASCITA_LUOGO_COD' => '',
            'CONDUCENTE2_CITTADINANZA_COD' => '',
            'CONDUCENTE2_DOCIDE_TIPO_COD' => '',
            'CONDUCENTE2_DOCIDE_NUMERO' => '',
            'CONDUCENTE2_DOCIDE_LUOGORIL_COD' => '',
            'CONDUCENTE2_PATENTE_NUMERO' => '',
            'CONDUCENTE2_PATENTE_LUOGORIL_COD' => '',
            'CONDUCENTE2_RECAPITO' => '',
        ];

        if (!$secondDriver) {
            return $empty;
        }

        // Required set per seconda guida
        $required = [
            'CONDUCENTE2_COGNOME' => $secondDriver->last_name,
            'CONDUCENTE2_NOME' => $secondDriver->first_name,
            'CONDUCENTE2_NASCITA_DATA' => $secondDriver->birthdate ? Carbon::parse($secondDriver->birthdate)->startOfDay() : null,
            'CONDUCENTE2_NASCITA_LUOGO_COD' => $secondDriver->birth_place_code,
            'CONDUCENTE2_CITTADINANZA_COD' => $secondDriver->citizenship_cargos_code,
            'CONDUCENTE2_DOCIDE_TIPO_COD' => $secondDriver->identity_document_type_code,
            'CONDUCENTE2_DOCIDE_NUMERO' => $secondDriver->doc_id_number,
            'CONDUCENTE2_DOCIDE_LUOGORIL_COD' => $secondDriver->identity_document_place_code,
            'CONDUCENTE2_PATENTE_NUMERO' => $secondDriver->driver_license_number,
            'CONDUCENTE2_PATENTE_LUOGORIL_COD' => $secondDriver->driver_license_place_code,
        ];

        // Validazione “all or nothing”
        foreach ($required as $field => $value) {
            $isMissing = is_null($value) || (is_string($value) && trim($value) === '');
            if ($isMissing) {
                $errors[] = "Secondo conducente presente ma campo mancante: {$field}.";
            }
        }

        // Se ci sono errori sul secondo driver, meglio lasciare blocco vuoto (così non invii dati parziali)
        // e l'utente corregge i dati prima di inviare.
        foreach ($errors as $err) {
            if (Str::contains($err, 'Secondo conducente presente')) {
                return $empty;
            }
        }

        return [
            'CONDUCENTE2_COGNOME' => $this->sanitizeCharset4((string) $secondDriver->last_name, 50),
            'CONDUCENTE2_NOME' => $this->sanitizeCharset4((string) $secondDriver->first_name, 50),
            'CONDUCENTE2_NASCITA_DATA' => $this->formatCargosDate16($required['CONDUCENTE2_NASCITA_DATA']),
            'CONDUCENTE2_NASCITA_LUOGO_COD' => $this->digitsOnly($required['CONDUCENTE2_NASCITA_LUOGO_COD']),
            'CONDUCENTE2_CITTADINANZA_COD' => $this->digitsOnly($required['CONDUCENTE2_CITTADINANZA_COD']),
            'CONDUCENTE2_DOCIDE_TIPO_COD' => $this->sanitizeCharset4((string) $required['CONDUCENTE2_DOCIDE_TIPO_COD'], 10),
            'CONDUCENTE2_DOCIDE_NUMERO' => $this->sanitizeCharset4((string) $required['CONDUCENTE2_DOCIDE_NUMERO'], 30),
            'CONDUCENTE2_DOCIDE_LUOGORIL_COD' => $this->digitsOnly($required['CONDUCENTE2_DOCIDE_LUOGORIL_COD']),
            'CONDUCENTE2_PATENTE_NUMERO' => $this->sanitizeCharset4((string) $required['CONDUCENTE2_PATENTE_NUMERO'], 30),
            'CONDUCENTE2_PATENTE_LUOGORIL_COD' => $this->digitsOnly($required['CONDUCENTE2_PATENTE_LUOGORIL_COD']),
            'CONDUCENTE2_RECAPITO' => $this->digitsOnly($secondDriver->phone),
        ];
    }

    /**
     * Validazione: campo obbligatorio non vuoto.
     * Genera un messaggio comprensibile con:
     * - label “umana”
     * - contesto (dove intervenire)
     * - hint (come risolvere)
     *
     * @param  mixed $value
     * @param  string $field
     * @param  array<int,string> $errors
     * @param  string|null $context
     * @param  string|null $customMessage
     * @return void
     */
    protected function requireFilled(
        mixed $value,
        string $field,
        array &$errors,
        ?string $context = null,
        ?string $customMessage = null
    ): void {
        $missing = is_null($value) || (is_string($value) && trim($value) === '');
        if (!$missing) {
            return;
        }

        $meta  = self::FIELD_META[$field] ?? null;
        $label = $meta['label'] ?? $field;
        $hint  = $meta['hint'] ?? 'Compila il campo richiesto nei dati anagrafici.';

        // Se il chiamante passa un messaggio custom, lo usiamo come “testo principale”
        $main = $customMessage ?: "{$label} mancante.";

        $msg = "[{$field}] {$main}";

        if ($context) {
            $msg .= " Dove: {$context}.";
        }

        $msg .= " Come risolvere: {$hint}";

        $errors[] = $msg;
    }

    /**
     * Formato data/ora CARGOS "Date 16": gg/mm/aaaa hh:mm
     *
     * @param  mixed $date
     * @return string
     */
    protected function formatCargosDate16(mixed $date): string
    {
        if (!$date) {
            return '';
        }

        try {
            $dt = $date instanceof Carbon ? $date : Carbon::parse($date);
            return $dt->format('d/m/Y H:i');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Normalizza per Set Caratteri 4:
     * - ASCII (rimuove accenti)
     * - Permessi: A-Z a-z 0-9 spazio - . , '
     * - Sostituisce tutto il resto con spazio, collassa spazi, tronca.
     *
     * @param  string $value
     * @param  int    $maxLen
     * @return string
     */
    protected function sanitizeCharset4(string $value, int $maxLen): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Rimuove accenti / caratteri non ASCII
        $value = Str::ascii($value);

        // Sostituisce caratteri non ammessi con spazio
        $value = preg_replace("/[^A-Za-z0-9\\-\\.,' ]+/", ' ', $value) ?? $value;

        // Collassa spazi multipli
        $value = preg_replace('/\\s+/', ' ', $value) ?? $value;

        $value = trim($value);

        // Tronca senza ellissi
        if (strlen($value) > $maxLen) {
            $value = substr($value, 0, $maxLen);
        }

        return $value;
    }

    /**
     * Solo cifre (Set Caratteri 5).
     * Se il valore è null/empty ritorna ''.
     *
     * @param  mixed $value
     * @return string
     */
    protected function digitsOnly(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        $s = trim((string) $value);
        if ($s === '') {
            return '';
        }

        $s = preg_replace('/\\D+/', '', $s) ?? $s;

        return $s;
    }
}
