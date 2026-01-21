<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Seeder: CargosDocumentTypesSeeder
 *
 * Importa la tabella "Tipo Documento" ufficiale CARGOS
 * da file CSV.
 *
 * NOTE:
 * - Il seeder è idempotente (updateOrInsert)
 * - I record non presenti nel CSV NON vengono cancellati
 * - Il CSV è la fonte di verità
 */
class CargosDocumentTypesSeeder extends Seeder
{
    /**
     * Percorso del file CSV (versionato).
     */
    protected string $csvPath = 'database/seeders/data/tipo_documento.csv';

    public function run(): void
    {
        if (!File::exists(base_path($this->csvPath))) {
            throw new \RuntimeException("File CSV non trovato: {$this->csvPath}");
        }

        $rows = array_map(
            fn ($line) => str_getcsv($line, ','),
            file(base_path($this->csvPath))
        );

        // Rimuove l'header
        $header = array_shift($rows);

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

                DB::table('cargos_document_types')->updateOrInsert(
                    ['code' => trim($row[0])],
                    [
                        'label'      => trim($row[1]),
                        'is_active'  => true,
                        'updated_at'=> now(),
                        'created_at'=> now(),
                    ]
                );
            }
        });
    }
}
