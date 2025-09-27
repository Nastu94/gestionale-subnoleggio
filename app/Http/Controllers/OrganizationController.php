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
        return view('pages.organizations.index');
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
