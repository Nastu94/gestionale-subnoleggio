<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Controller: Audit (lettura sola)
 * Note: Permesso protetto a livello rotta: 'audit.view'
 */
class AuditController extends Controller
{
    /** Log eventi/azioni */
    public function index()
    {
        return view('audit.index');
    }

    /** Facoltativo: dettaglio evento */
    public function show(string $eventId)
    {
        return view('audit.show', compact('eventId'));
    }
}
