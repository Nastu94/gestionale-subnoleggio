<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Controller: Report (lettura sola)
 * Note: Permesso protetto a livello rotta: 'reports.view'
 */
class ReportController extends Controller
{
    /** Indice/dispatcher dei report disponibili */
    public function index()
    {
        return view('reports.index');
    }

    /** Facoltativo: show per un report specifico */
    public function show(string $slug)
    {
        // TODO: renderizza il report $slug
        return view('reports.show', compact('slug'));
    }
}
