<?php

namespace App\Http\Controllers;

use App\Models\VehicleBlock;
use Illuminate\Http\Request;

/**
 * Controller Resource: VehicleBlock (Blocchi)
 */
class BlockController extends Controller
{
    public function __construct()
    {
        // Parametro rotta: {block}
        $this->authorizeResource(VehicleBlock::class, 'block');
    }

    public function index()   { return view('blocks.index'); }
    public function create()  { return view('blocks.create'); }
    public function show(VehicleBlock $block) { return view('blocks.show', compact('block')); }
    public function edit(VehicleBlock $block) { return view('blocks.edit', compact('block')); }

    public function store(Request $request)
    {
        // TODO: valida e crea blocco
        return redirect()->route('blocks.index');
    }

    public function update(Request $request, VehicleBlock $block)
    {
        // TODO: valida e aggiorna blocco
        return redirect()->route('blocks.show', $block);
    }

    public function destroy(VehicleBlock $block)
    {
        // TODO: elimina/chiude blocco
        return redirect()->route('blocks.index');
    }

    /**
     * Azione non-REST: override blocco (permesso: blocks.override su rotta)
     * Qui non usiamo authorizeResource; se vuoi, puoi aggiungere una policy ability dedicata.
     */
    public function override(Request $request, VehicleBlock $block)
    {
        // TODO: logica di override
        return redirect()->route('blocks.show', $block);
    }
}
