<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReportPresetRequest;
use App\Http\Requests\Admin\UpdateReportPresetRequest;
use App\Models\ReportPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller admin per la gestione dei preset report.
 *
 * Responsabilità:
 * - elencare i preset disponibili;
 * - mostrare il dettaglio di un preset;
 * - creare nuovi preset validati;
 * - aggiornare preset esistenti;
 *
 * Nota:
 * l'accesso è già protetto a livello di route tramite middleware.
 */
class ReportPresetController extends Controller
{
    /**
     * Elenca i preset report salvati.
     */
    public function index(Request $request): JsonResponse
    {
        $reportPresets = ReportPreset::query()
            ->with('creator:id,name')
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reportPresets,
        ]);
    }

    /**
     * Mostra il dettaglio di un preset report.
     */
    public function show(ReportPreset $reportPreset): JsonResponse
    {
        $reportPreset->load('creator:id,name');

        return response()->json([
            'success' => true,
            'data' => $reportPreset,
        ]);
    }

    /**
     * Salva un nuovo preset report.
     */
    public function store(StoreReportPresetRequest $request): JsonResponse
    {
        $reportPreset = ReportPreset::create([
            ...$request->presetData(),
            'created_by' => $request->user()->id,
        ]);

        $reportPreset->load('creator:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Preset report creato correttamente.',
            'data' => $reportPreset,
        ], 201);
    }

    /**
     * Aggiorna un preset report esistente.
     */
    public function update(UpdateReportPresetRequest $request, ReportPreset $reportPreset): JsonResponse
    {
        $reportPreset->update($request->presetData());

        $reportPreset->load('creator:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Preset report aggiornato correttamente.',
            'data' => $reportPreset,
        ]);
    }
}