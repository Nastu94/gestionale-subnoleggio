<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportPreset;
use App\Services\Reports\ReportRunner;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Controller admin per l'esecuzione dei preset report.
 *
 * Responsabilità:
 * - recuperare il preset richiesto;
 * - delegare l'esecuzione al ReportRunner;
 * - restituire una risposta JSON pulita per la UI admin.
 */
class ReportPresetRunController extends Controller
{
    /**
     * Esegue il preset report richiesto e restituisce i risultati.
     */
    public function __invoke(ReportPreset $reportPreset, ReportRunner $reportRunner): JsonResponse
    {
        /**
         * Esegue il preset tramite il servizio dedicato.
         *
         * Nota:
         * il servizio si occupa già della validazione della configurazione
         * e della costruzione della query corretta.
         */
        try {
            $rows = $reportRunner->run($reportPreset);

            return response()->json([
                'success' => true,
                'data' => [
                    'preset' => [
                        'id' => $reportPreset->id,
                        'name' => $reportPreset->name,
                        'description' => $reportPreset->description,
                        'report_type' => $reportPreset->report_type,
                        'metrics' => $reportPreset->metrics,
                        'dimensions' => $reportPreset->dimensions,
                        'filters' => $reportPreset->filters,
                        'chart_type' => $reportPreset->chart_type,
                    ],
                    'rows' => $rows,
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            /**
             * Errore di configurazione del preset:
             * restituiamo un 422 perché il preset non è valido
             * rispetto alle whitelist del motore report.
             */
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}