<?php

namespace App\Http\Controllers;

use App\Models\VehicleDocument;
use Illuminate\Http\Request;

/**
 * Controller Resource: VehicleDocument (Documenti Veicolo)
 */
class VehicleDocumentController extends Controller
{
    public function __construct()
    {
        // Parametro rotta: {document}
        $this->authorizeResource(VehicleDocument::class, 'document');
    }

    public function index()   { return view('vehicle-documents.index'); }
    public function create()  { return view('vehicle-documents.create'); }
    public function show(VehicleDocument $document) { return view('vehicle-documents.show', compact('document')); }
    public function edit(VehicleDocument $document) { return view('vehicle-documents.edit', compact('document')); }

    public function store(Request $request)
    {
        // TODO: valida e crea documento (upload + metadata)
        return redirect()->route('vehicle-documents.index');
    }

    public function update(Request $request, VehicleDocument $document)
    {
        // TODO: valida e aggiorna documento
        return redirect()->route('vehicle-documents.show', $document);
    }

    public function destroy(VehicleDocument $document)
    {
        // TODO: elimina documento
        return redirect()->route('vehicle-documents.index');
    }
}
