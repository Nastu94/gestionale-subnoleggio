<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Seeder: CargosPaymentTypesSeeder
 *
 * Importa la tabella "Tipo Pagamento" ufficiale CARGOS
 * da file CSV.
 *
 * NOTE:
 * - Il CSV ha header (prima riga)
 * - Seeder idempotente (updateOrInsert)
 * - Nessuna cancellazione dei record esistenti
 * - I codici rappresentano la modalità contrattuale,
 *   NON il mezzo tecnico di pagamento
 */
class CargosPaymentTypesSeeder extends Seeder
{
    /**
     * Percorso del file CSV (versionato).
     */
    protected string $csvPath = 'database/seeders/data/tipo_pagamento.csv';

    public function run(): void
    {
        if (!File::exists(base_path($this->csvPath))) {
            throw new \RuntimeException("File CSV non trovato: {$this->csvPath}");
        }

        $rows = array_map(
            fn ($line) => str_getcsv($line, ','),
            file(base_path($this->csvPath), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        );

        // Rimuoviamo l'header (presente e voluto)
        array_shift($rows);

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                /**
                 * Struttura CSV attesa:
                 * [0] => codice
                 * [1] => descrizione
                 */
                if (count($row) < 2) {
                    continue;
                }

                $code  = trim($row[0]);
                $label = trim($row[1]);

                if ($code === '' || $label === '') {
                    continue;
                }

                DB::table('cargos_payment_types')->updateOrInsert(
                    ['code' => $code],
                    [
                        'label'       => $label,
                        'is_active'   => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }
}
