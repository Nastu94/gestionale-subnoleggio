<?php
// app/Http/Controllers/OrganizationController.php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

/**
 * Controller Resource: Organization (Gestione renter)
 *
 * - Protezione via Gate 'manage.renters' impostata a livello rotta.
 * - Vista: per ora stub Blade minime (vedi sotto).
 */
class OrganizationController extends Controller
{
    /** Elenco organizations (renter/admin) */
    public function index()
    {
        // 1) Boundaries del giorno corrente nell’app timezone (es. Europe/Rome)
        $startOfToday = now()->startOfDay();
        $endOfToday   = now()->endOfDay();

        /**
         * 2) Elenco renter con colonna calcolata "vehicles_count":
         *    numero di veicoli che risultano assegnati OGGI (overlap sui datetime).
         *
         *    Overlap condition:
         *      - va.start_at <= endOfToday
         *      - AND (va.end_at IS NULL OR va.end_at >= startOfToday)
         *
         *    COUNT(DISTINCT) a prova di doppie assegnazioni “sporche”.
         */
        $organizations = Organization::query()
            ->where('type', 'renter')
            ->select('organizations.*')
            ->selectSub(function ($q) use ($startOfToday, $endOfToday) {
                $q->from('vehicle_assignments as va')
                ->selectRaw('COUNT(DISTINCT va.vehicle_id)')
                ->whereColumn('va.renter_org_id', 'organizations.id')
                ->where('va.start_at', '<=', $endOfToday)
                ->where(function ($q2) use ($startOfToday) {
                    $q2->whereNull('va.end_at')
                        ->orWhere('va.end_at', '>=', $startOfToday);
                });
            }, 'vehicles_count')
            ->orderBy('id')
            ->paginate(15);

        return view('pages.organizations.index', compact('organizations'));
    }

    /** Form creazione */
    public function create()
    {
        return view('pages.organizations.create');
    }

    /** Salvataggio */
    public function store(Request $request)
    {
        // TODO validazione + create Organization
        return redirect()->route('organizations.index');
    }

    /** Dettaglio */
    public function show(Organization $organization)
    {
        return view('pages.organizations.show', compact('organization'));
    }

    /** Form modifica */
    public function edit(Organization $organization)
    {
        return view('pages.organizations.edit', compact('organization'));
    }

    /** Aggiornamento */
    public function update(Request $request, Organization $organization)
    {
        // TODO validazione + update
        return redirect()->route('organizations.show', $organization);
    }

    /** Cancellazione */
    public function destroy(Organization $organization)
    {
        // TODO soft/hard delete
        return redirect()->route('organizations.index');
    }
}
