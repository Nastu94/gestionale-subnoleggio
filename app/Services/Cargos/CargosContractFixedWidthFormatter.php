<?php

namespace App\Services\Cargos;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Formatter del record CARGOS a larghezza fissa (tracciato record).
 *
 * Responsabilità:
 * - Prendere il payload "chiave => valore" prodotto dal builder
 * - Normalizzare i valori in base a tipo/set caratteri
 * - Applicare padding/troncamento per rispettare le lunghezze
 * - Restituire una stringa lunga esattamente 1505 caratteri
 *
 * NOTA:
 * - Qui NON chiamiamo l'API: generiamo solo la riga/record.
 * - Il builder è il "guardiano" della validazione; qui aggiungiamo solo controlli tecnici (len/charset).
 */
class CargosContractFixedWidthFormatter
{
    /**
     * Lunghezza totale record (ultimo campo termina a 1505).
     */
    public const TOTAL_LEN = 1505;

    /**
     * Specifica campi (ordine + lunghezza) conforme al tracciato p2.
     *
     * - key: chiave presente nel payload del builder
     * - len: lunghezza fissa del campo
     * - type: string|int|date10|date16
     * - set: 1..6 oppure null (se gestito dal type)
     * - align: left|right (padding con spazi)
     *
     * @var array<int, array{key:string,len:int,type:string,set:?int,align:string}>
     */
    protected const FIELDS = [
        // Contratto
        ['key' => 'contratto_id',                   'len' => 50,  'type' => 'string', 'set' => 4, 'align' => 'left'],
        ['key' => 'contratto_data',                 'len' => 16,  'type' => 'date16', 'set' => null, 'align' => 'left'],
        ['key' => 'contratto_tipop',                'len' => 1,   'type' => 'string', 'set' => 4, 'align' => 'left'],
        ['key' => 'contratto_checkout_data',        'len' => 16,  'type' => 'date16', 'set' => null, 'align' => 'left'],
        ['key' => 'CONTRATTO_CHECKOUT_LUOGO_COD',    'len' => 9,   'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONTRATTO_CHECKOUT_INDIRIZZO',    'len' => 150, 'type' => 'string', 'set' => 2, 'align' => 'left'],
        ['key' => 'CONTRATTO_CHECKIN_DATA',          'len' => 16,  'type' => 'date16', 'set' => null, 'align' => 'left'],
        ['key' => 'CONTRATTO_CHECKIN_LUOGO_COD',     'len' => 9,   'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONTRATTO_CHECKIN_INDIRIZZO',     'len' => 150, 'type' => 'string', 'set' => 2, 'align' => 'left'],

        // Operatore / Agenzia
        ['key' => 'OPERATORE_ID',                   'len' => 50,  'type' => 'string', 'set' => 4, 'align' => 'left'],
        ['key' => 'AGENZIA_ID',                     'len' => 30,  'type' => 'string', 'set' => 4, 'align' => 'left'],
        ['key' => 'AGENZIA_NOME',                   'len' => 70,  'type' => 'string', 'set' => 4, 'align' => 'left'],
        ['key' => 'AGENZIA_LUOGO_COD',              'len' => 9,   'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'AGENZIA_INDIRIZZO',              'len' => 150, 'type' => 'string', 'set' => 2, 'align' => 'left'],
        ['key' => 'AGENZIA_RECAPITO_TEL',           'len' => 20,  'type' => 'string', 'set' => 5, 'align' => 'left'],

        // Veicolo
        ['key' => 'VEICOLO_TIPO',                   'len' => 1,   'type' => 'string', 'set' => 4, 'align' => 'left'],
        ['key' => 'VEICOLO_MARCA',                  'len' => 50,  'type' => 'string', 'set' => 2, 'align' => 'left'],
        ['key' => 'VEICOLO_MODELLO',                'len' => 100, 'type' => 'string', 'set' => 2, 'align' => 'left'],
        ['key' => 'VEICOLO_TARGA',                  'len' => 15,  'type' => 'string', 'set' => 3, 'align' => 'left'],
        ['key' => 'VEICOLO_COLORE',                 'len' => 50,  'type' => 'string', 'set' => 1, 'align' => 'left'],
        ['key' => 'VEICOLO_GPS',                    'len' => 1,   'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'VEICOLO_BLOCCOM',                'len' => 1,   'type' => 'int',    'set' => null, 'align' => 'right'],

        // Contraente
        ['key' => 'CONDUCENTE_CONTRAENTE_COGNOME',            'len' => 50,  'type' => 'string', 'set' => 1, 'align' => 'left'],
        ['key' => 'CONDUCENTE_CONTRAENTE_NOME',               'len' => 30,  'type' => 'string', 'set' => 1, 'align' => 'left'],
        ['key' => 'CONDUCENTE_CONTRAENTE_NASCITA_DATA',       'len' => 10,  'type' => 'date10', 'set' => null, 'align' => 'left'],
        ['key' => 'CONDUCENTE_CONTRAENTE_NASCITA_LUOGO_COD',  'len' => 9,   'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONDUCENTE_CONTRAENTE_CITTADINANZA_COD',   'len' => 9,   'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONDUCENTE_CONTRAENTE_RESIDENZA_LUOGO_COD','len' => 9,   'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONDUCENTE_CONTRAENTE_RESIDENZA_INDIRIZZO','len' => 150, 'type' => 'string', 'set' => 2, 'align' => 'left'],
        ['key' => 'CONDUCENTE_CONTRAENTE_DOCIDE_TIPO_COD',    'len' => 5,   'type' => 'string', 'set' => 4, 'align' => 'left'],
        ['key' => 'CONDUCENTE_CONTRAENTE_DOCIDE_NUMERO',      'len' => 20,  'type' => 'string', 'set' => 3, 'align' => 'left'],
        ['key' => 'CONDUCENTE_CONTRAENTE_DOCIDE_LUOGORIL_COD', 'len' => 9,  'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONDUCENTE_CONTRAENTE_PATENTE_NUMERO',     'len' => 20,  'type' => 'string', 'set' => 3, 'align' => 'left'],
        ['key' => 'CONDUCENTE_CONTRAENTE_PATENTE_LUOGORIL_COD','len' => 9,  'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONDUCENTE_CONTRAENTE_RECAPITO',           'len' => 20,  'type' => 'string', 'set' => 6, 'align' => 'left'],

        // Secondo conducente
        ['key' => 'CONDUCENTE2_COGNOME',             'len' => 50,  'type' => 'string', 'set' => 1, 'align' => 'left'],
        ['key' => 'CONDUCENTE2_NOME',                'len' => 30,  'type' => 'string', 'set' => 1, 'align' => 'left'],
        ['key' => 'CONDUCENTE2_NASCITA_DATA',        'len' => 10,  'type' => 'date10', 'set' => null, 'align' => 'left'],
        ['key' => 'CONDUCENTE2_NASCITA_LUOGO_COD',   'len' => 9,   'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONDUCENTE2_CITTADINANZA_COD',    'len' => 9,   'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONDUCENTE2_DOCIDE_TIPO_COD',     'len' => 5,   'type' => 'string', 'set' => 4, 'align' => 'left'],
        ['key' => 'CONDUCENTE2_DOCIDE_NUMERO',       'len' => 20,  'type' => 'string', 'set' => 3, 'align' => 'left'],
        ['key' => 'CONDUCENTE2_DOCIDE_LUOGORIL_COD',  'len' => 9,  'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONDUCENTE2_PATENTE_NUMERO',      'len' => 20,  'type' => 'string', 'set' => 3, 'align' => 'left'],
        ['key' => 'CONDUCENTE2_PATENTE_LUOGORIL_COD', 'len' => 9,  'type' => 'int',    'set' => null, 'align' => 'right'],
        ['key' => 'CONDUCENTE2_RECAPITO',            'len' => 20,  'type' => 'string', 'set' => 6, 'align' => 'left'],
    ];

    /**
     * Format record a larghezza fissa.
     *
     * @param  array<string, mixed> $payload
     * @return array{ok:bool,record:string,length:int,errors:array<int,string>}
     */
    public function format(array $payload): array
    {
        $errors = [];
        $record = '';

        foreach (self::FIELDS as $def) {
            $key = $def['key'];
            $raw = $payload[$key] ?? '';

            $chunk = $this->formatField($raw, $def, $errors);
            $record .= $chunk;
        }

        $length = strlen($record);

        if ($length !== self::TOTAL_LEN) {
            $errors[] = "Record length non valido: atteso " . self::TOTAL_LEN . ", ottenuto {$length}.";
        }

        return [
            'ok' => count($errors) === 0,
            'record' => $record,
            'length' => $length,
            'errors' => $errors,
        ];
    }

    /**
     * Format singolo campo.
     *
     * @param  mixed $value
     * @param  array{key:string,len:int,type:string,set:?int,align:string} $def
     * @param  array<int,string> $errors
     * @return string
     */
    protected function formatField(mixed $value, array $def, array &$errors): string
    {
        $key   = $def['key'];
        $len   = $def['len'];
        $type  = $def['type'];
        $set   = $def['set'];
        $align = $def['align'];

        // 1) Normalizza per type (date/int/string)
        $s = $this->normalizeByType($value, $type);

        // 2) Normalizza per set caratteri (se richiesto)
        if (!is_null($set)) {
            $s = $this->normalizeBySet($s, $set);
        }

        // 3) Controllo overflow: se eccede, tronco ma segnalo (così non mandi roba “silenziosamente” diversa)
        if (strlen($s) > $len) {
            $errors[] = "[{$key}] Valore troppo lungo: " . strlen($s) . " > {$len} (verrà troncato).";
            $s = substr($s, 0, $len);
        }

        // 4) Padding a lunghezza fissa
        $padType = ($align === 'right') ? STR_PAD_LEFT : STR_PAD_RIGHT;

        return str_pad($s, $len, ' ', $padType);
    }

    /**
     * Normalizza in base al tipo campo.
     *
     * Nota cruciale:
     * - Se il builder ha già fornito date in formato CARGOS (dd/mm/YYYY o dd/mm/YYYY HH:ii),
     *   NON dobbiamo riparsarle con Carbon::parse() perché sono ambigue e possono invertirsi (mm/dd).
     */
    protected function normalizeByType(mixed $value, string $type): string
    {
        if ($type === 'int') {
            if (is_null($value)) {
                return '';
            }

            $s = trim((string) $value);
            if ($s === '') {
                return '';
            }

            return preg_replace('/\D+/', '', $s) ?? '';
        }

        if ($type === 'date16') {
            /**
             * Se è già una stringa dd/mm/YYYY HH:ii, usala così com’è.
             * Evita reinterpretazioni sbagliate.
             */
            if (is_string($value)) {
                $s = trim($value);

                if (preg_match('/^\d{2}\/\d{2}\/\d{4}\s\d{2}:\d{2}$/', $s)) {
                    return substr($s, 0, 16);
                }

                // Se è una date10 e manca l'orario, non inventiamo.
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
                    return '';
                }
            }

            // Se è Carbon o un valore parseabile senza ambiguità (es. ISO dal DB), formatta.
            try {
                $dt = $value instanceof Carbon ? $value : Carbon::parse($value);
                return $dt->format('d/m/Y H:i');
            } catch (\Throwable) {
                return '';
            }
        }

        if ($type === 'date10') {
            /**
             * Se è già una stringa dd/mm/YYYY (o dd/mm/YYYY HH:ii), prendi i primi 10 caratteri.
             */
            if (is_string($value)) {
                $s = trim($value);

                if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $s)) {
                    return substr($s, 0, 10);
                }
            }

            try {
                $dt = $value instanceof Carbon ? $value : Carbon::parse($value);
                return $dt->format('d/m/Y');
            } catch (\Throwable) {
                return '';
            }
        }

        // string
        if (is_null($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * Format data in modo robusto.
     */
    protected function formatDate(mixed $value, string $format, int $expectedLen): string
    {
        if (!$value) {
            return '';
        }

        try {
            $dt = $value instanceof Carbon ? $value : Carbon::parse($value);
            $s  = $dt->format($format);

            // Difesa: se arriva stringa già formattata male, almeno non esplodiamo
            if (strlen($s) !== $expectedLen) {
                return substr($s, 0, $expectedLen);
            }

            return $s;
        } catch (\Throwable) {
            $s = trim((string) $value);
            return (strlen($s) >= $expectedLen) ? substr($s, 0, $expectedLen) : '';
        }
    }

    /**
     * Normalizzazione per Set Caratteri CARGOS (p2).
     *
     * NOTA:
     * - Usiamo Str::ascii per stare in un sottoinsieme “safe”.
     * - Questo evita caratteri strani che spesso fanno rifiutare i tracciati.
     */
    protected function normalizeBySet(string $value, int $set): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = Str::ascii($value);

        return match ($set) {
            // Set 1: alfabetici + apostrofo + spazio (noi riduciamo ad ASCII)
            1 => $this->rxKeep($value, "/[^A-Za-z' ]+/"),

            // Set 2: alfanumerici + . , ' + spazio
            2 => $this->rxKeep($value, "/[^A-Za-z0-9\\.,' ]+/"),

            // Set 3: alfanumerici + - . + spazio
            3 => $this->rxKeep($value, "/[^A-Za-z0-9\\-\\. ]+/"),

            // Set 4: alfanumerici + - . , ' + spazio
            4 => $this->rxKeep($value, "/[^A-Za-z0-9\\-\\.,' ]+/"),

            // Set 5: solo cifre
            5 => (preg_replace('/\\D+/', '', $value) ?? ''),

            // Set 6: alfanumerici + - . +
            6 => $this->rxKeep($value, "/[^A-Za-z0-9\\-\\.\\+]+/"),

            default => $value,
        };
    }

    /**
     * Applica una regex "keep": sostituisce i non ammessi con spazio e collassa spazi.
     */
    protected function rxKeep(string $value, string $patternNotAllowed): string
    {
        $value = preg_replace($patternNotAllowed, ' ', $value) ?? $value;
        $value = preg_replace('/\\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
