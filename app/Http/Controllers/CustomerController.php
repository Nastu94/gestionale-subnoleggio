<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * Controller Resource: Customer
 *
 * - Autorizzazione: delegata alla CustomerPolicy tramite authorizeResource().
 * - Rotte: customers.index|show|store|update|destroy (già definite).
 * - Vista: placeholder, verranno create successivamente.
 */
class CustomerController extends Controller
{
    public function __construct()
    {
        /**
         * Collega le azioni REST ai metodi della Policy:
         * index  -> viewAny
         * show   -> view
         * create -> create
         * store  -> create
         * edit   -> update
         * update -> update
         * destroy-> delete
         *
         * Il secondo argomento deve combaciare col parametro rotta {customer}.
         */
        $this->authorizeResource(Customer::class, 'customer');
    }

    /** Elenco clienti (Policy: viewAny) */
    public function index()
    {
        // NB: qui potrai sostituire con Livewire o query reali
        return view('pages.customers.index');
    }

    /** Form creazione (Policy: create) */
    public function create()
    {
        return view('pages.customers.create');
    }

    /** Salvataggio nuovo cliente (Policy: create) */
    public function store(Request $request)
    {
        // TODO: valida $request, crea Customer e reindirizza
        return redirect()->route('customers.index');
    }

    /** Dettaglio cliente (Policy: view) */
    public function show(Customer $customer)
    {
        return view('pages.customers.show', compact('customer'));
    }

    /** Form modifica (Policy: update) */
    public function edit(Customer $customer)
    {
        return view('pages.customers.edit', compact('customer'));
    }

    /** Aggiornamento cliente (Policy: update) */
    public function update(Request $request, Customer $customer)
    {
        // TODO: valida e aggiorna $customer
        return redirect()->route('customers.show', $customer);
    }

    /** Cancellazione cliente (Policy: delete) */
    public function destroy(Customer $customer)
    {
        // TODO: eventuale soft delete
        return redirect()->route('customers.index');
    }
}
