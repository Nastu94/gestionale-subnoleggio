<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;


/**
 * Command: ImportCargosLuoghi
 *
 * Importa la tabella LUOGHI ufficiale CARGOS da CSV.
 *
 * Caratteristiche:
 * - Idempotente (updateOrInsert)
 * - Non cancella mai record esistenti
 * - Gestisce comuni italiani e stati esteri
 * - Conserva il payload originale per audit/debug
 *
 * USO:
 * php artisan cargos:import-luoghi
 */
class ImportCargosLuoghi extends Command
{
    /**
     * Signature del comando.
     */
    protected $signature = 'cargos:import-luoghi';

    /**
     * Descrizione del comando.
     */
    protected $description = 'Importa la tabella LUOGHI ufficiale CARGOS da file CSV';

    /**
     * Percorso del file CSV.
     */
    protected string $csvPath = 'database/seeders/data/luoghi.csv';

    public function handle(): int
    {
        if (!File::exists(base_path($this->csvPath))) {
            $this->error("File CSV non trovato: {$this->csvPath}");
            return self::FAILURE;
        }

        $lines = file(
            base_path($this->csvPath),
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if (count($lines) <= 1) {
            $this->error('Il CSV non contiene dati.');
            return self::FAILURE;
        }

        // Estraiamo header
        $header = str_getcsv(array_shift($lines), ',');

        // Mappiamo gli indici (difensivo: non assumiamo l'ordine)
        $indexes = array_flip($header);

        /**
         * Colonne MINIME che ci aspettiamo dal CSV CARGOS.
         * Se i nomi differiscono, li adattiamo QUI (non nel DB).
         */
        $required = [
            'Codice',        // codice luogo
            'Descrizione',   // nome luogo
        ];

        foreach ($required as $col) {
            if (!array_key_exists($col, $indexes)) {
                $this->error("Colonna mancante nel CSV: {$col}");
                return self::FAILURE;
            }
        }

        $this->info('Import LUOGHI in corso…');
        DB::transaction(function () use ($lines, $indexes) {
            $today = Carbon::today();

            foreach ($lines as $line) {
                $row = str_getcsv($line, ',');

                $code = trim($row[$indexes['Codice']] ?? '');
                $name = trim($row[$indexes['Descrizione']] ?? '');

                if ($code === '' || $name === '') {
                    continue;
                }

                /**
                 * Colonne opzionali
                 */
                $provinceIdx = $indexes['Provincia'] ?? null;

                $provinceValue = $provinceIdx !== null
                    ? trim($row[$provinceIdx] ?? '')
                    : null;

                /**
                 * Regola CARGOS:
                 * - Provincia != 'ES' → luogo italiano
                 * - Provincia == 'ES' → luogo estero
                 */
                $isItalian = ($provinceValue !== 'ES' || $name === 'ITALIA');

                $endDateIdx  = $indexes['DataFineVal'] ?? null;

                /**
                 * Valutazione validità luogo
                 * - Se DATA_FINE_VALIDITA è NULL → attivo
                 * - Se valorizzata e < oggi → NON attivo
                 */
                $isActive = true;

                if ($endDateIdx !== null) {
                    $rawEndDate = trim($row[$endDateIdx] ?? '');

                    if ($rawEndDate !== '') {
                        try {
                            $endDate = Carbon::parse($rawEndDate);

                            if ($endDate->lt($today)) {
                                $isActive = false;
                            }
                        } catch (\Throwable $e) {
                            /**
                             * Parsing fallito:
                             * - NON blocchiamo l'import
                             * - Manteniamo il luogo attivo
                             * - Il raw_payload ci permette audit successivo
                             */
                            $isActive = true;
                        }
                    }
                }

                DB::table('cargos_luoghi')->updateOrInsert(
                    ['code' => (int) $code],
                    [
                        'name'          => $name,
                        'province_code' => $provinceValue ?: null,
                        'country_code'  => $isItalian && $name !== 'ITALIA' ? 'IT' : null,
                        'is_italian'    => $isItalian,
                        'is_active'     => $isActive,
                        'raw_payload'   => json_encode($row),
                        'updated_at'    => now(),
                        'created_at'    => now(),
                    ]
                );
            }
        });

        $this->info('Import LUOGHI completato con successo.');

        return self::SUCCESS;
    }
}
