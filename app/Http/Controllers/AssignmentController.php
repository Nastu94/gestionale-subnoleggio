<?php

namespace App\Http\Controllers;

use App\Models\VehicleAssignment;
use Illuminate\Http\Request;

/**
 * Controller Resource: VehicleAssignment (Assegnazioni)
 */
class AssignmentController extends Controller
{
    public function __construct()
    {
        // Parametro rotta: {assignment}
        $this->authorizeResource(VehicleAssignment::class, 'assignment');
    }

    public function index()   { return view('pages.assignments.index'); }
    public function create()  { return view('pages.assignments.create'); }
    public function show(VehicleAssignment $assignment) { return view('pages.assignments.show', compact('assignment')); }
    public function edit(VehicleAssignment $assignment) { return view('pages.assignments.edit', compact('assignment')); }

    public function store(Request $request)
    {
        // TODO: valida e crea assegnazione
        return redirect()->route('assignments.index');
    }

    public function update(Request $request, VehicleAssignment $assignment)
    {
        // TODO: valida e aggiorna assegnazione
        return redirect()->route('assignments.show', $assignment);
    }

    public function destroy(VehicleAssignment $assignment)
    {
        // TODO: elimina/termina assegnazione
        return redirect()->route('assignments.index');
    }
}
