<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Controllers
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\DashboardController;

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\LocationController;

use App\Http\Controllers\RentalController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\OrganizationController;

use App\Http\Controllers\VehicleDocumentController;

use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditController;

Route::get('/', function () {
    return view('auth.login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    | Vista principale con tiles e menu radiale.
    | Permessi: gestiti nella view via Gate (le tiles e il menu si auto-filtrano).
    */
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

/*
|--------------------------------------------------------------------------
| ANAGRAFICHE
|--------------------------------------------------------------------------
*/

// ------------------------- Clienti -------------------------
    /*
    | Elenco clienti
    | Permesso: customers.viewAny
    */
    Route::get('/customers', [CustomerController::class, 'index'])
        ->name('customers.index')
        ->middleware('permission:customers.viewAny');

    /*
    | Dettaglio cliente
    | Permesso: customers.view
    */
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->name('customers.show')
        ->middleware('permission:customers.view');

    /*
    | Crea cliente (POST) – form via SPA/modale o pagina separata
    | Permesso: customers.create
    */
    Route::post('/customers', [CustomerController::class, 'store'])
        ->name('customers.store')
        ->middleware('permission:customers.create');

    /*
    | Aggiorna cliente (PUT/PATCH)
    | Permesso: customers.update
    */
    Route::match(['put','patch'], '/customers/{customer}', [CustomerController::class, 'update'])
        ->name('customers.update')
        ->middleware('permission:customers.update');

    /*
    | Elimina cliente (DELETE)
    | Permesso: customers.delete
    */
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->name('customers.destroy')
        ->middleware('permission:customers.delete');

// ------------------------- Veicoli -------------------------
    /*
    | Elenco veicoli
    | Permesso: vehicles.viewAny
    */
    Route::get('/vehicles', [VehicleController::class, 'index'])
        ->name('vehicles.index')
        ->middleware('permission:vehicles.viewAny');

    /*
    | Crea veicolo
    | Permesso: vehicles.create
    */
    Route::get('/vehicles/create', [VehicleController::class, 'create'])
        ->name('vehicles.create')
        ->middleware('permission:vehicles.create');
    Route::post('/vehicles', [VehicleController::class, 'store'])
        ->name('vehicles.store')
        ->middleware('permission:vehicles.create');

    /*
    | Dettaglio veicolo
    | Permesso: vehicles.view
    */
    Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])
        ->name('vehicles.show')
        ->middleware('permission:vehicles.view');

    /*
    | Aggiorna veicolo
    | Permesso: vehicles.update
    */
    Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])
        ->name('vehicles.edit')
        ->middleware('permission:vehicles.update');
    Route::match(['put','patch'], '/vehicles/{vehicle}', [VehicleController::class, 'update'])
        ->name('vehicles.update')
        ->middleware('permission:vehicles.update');

    /*
    | Elimina veicolo
    | Permesso: vehicles.delete
    */
    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])
        ->name('vehicles.destroy')
        ->middleware('permission:vehicles.delete');

// ------------------------- Sedi -------------------------
    /*
    | Elenco sedi
    | Permesso: locations.viewAny
    */
    Route::get('/locations', [LocationController::class, 'index'])
        ->name('locations.index')
        ->middleware('permission:locations.viewAny');

    /*
    | Dettaglio sede
    | Permesso: locations.view
    */
    Route::get('/locations/{location}', [LocationController::class, 'show'])
        ->name('locations.show')
        ->middleware('permission:locations.view');

    /*
    | Crea sede
    | Permesso: locations.create
    */
    Route::post('/locations', [LocationController::class, 'store'])
        ->name('locations.store')
        ->middleware('permission:locations.create');

    /*
    | Aggiorna sede
    | Permesso: locations.update
    */
    Route::match(['put','patch'], '/locations/{location}', [LocationController::class, 'update'])
        ->name('locations.update')
        ->middleware('permission:locations.update');

    /*
    | Elimina sede
    | Permesso: locations.delete
    */
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])
        ->name('locations.destroy')
        ->middleware('permission:locations.delete');

/*
|--------------------------------------------------------------------------
| NOLEGGI
|--------------------------------------------------------------------------
*/

// ------------------------- Contratti (Rentals) -------------------------
    /*
    | Elenco contratti
    | Permesso: rentals.viewAny
    */
    Route::get('/rentals', [RentalController::class, 'index'])
        ->name('rentals.index')
        ->middleware('permission:rentals.viewAny');

    /*
    | Dettaglio contratto
    | Permesso: rentals.view
    */
    Route::get('/rentals/{rental}', [RentalController::class, 'show'])
        ->name('rentals.show')
        ->middleware('permission:rentals.view');

    /*
    | Crea contratto
    | Permesso: rentals.create
    */
    Route::post('/rentals', [RentalController::class, 'store'])
        ->name('rentals.store')
        ->middleware('permission:rentals.create');

    /*
    | Aggiorna contratto
    | Permesso: rentals.update
    */
    Route::match(['put','patch'], '/rentals/{rental}', [RentalController::class, 'update'])
        ->name('rentals.update')
        ->middleware('permission:rentals.update');

    /*
    | Elimina contratto
    | Permesso: rentals.delete
    */
    Route::delete('/rentals/{rental}', [RentalController::class, 'destroy'])
        ->name('rentals.destroy')
        ->middleware('permission:rentals.delete');

// ------------------------- Assegnazioni -------------------------
    /*
    | Elenco assegnazioni veicolo→renter
    | Permesso: assignments.viewAny
    */
    Route::get('/assignments', [AssignmentController::class, 'index'])
        ->name('assignments.index')
        ->middleware('permission:assignments.viewAny');

    /*
    | Dettaglio assegnazione
    | Permesso: assignments.view
    */
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])
        ->name('assignments.show')
        ->middleware('permission:assignments.view');

    /*
    | Crea assegnazione
    | Permesso: assignments.create
    */
    Route::post('/assignments', [AssignmentController::class, 'store'])
        ->name('assignments.store')
        ->middleware('permission:assignments.create');

    /*
    | Aggiorna assegnazione
    | Permesso: assignments.update
    */
    Route::match(['put','patch'], '/assignments/{assignment}', [AssignmentController::class, 'update'])
        ->name('assignments.update')
        ->middleware('permission:assignments.update');

    /*
    | Elimina assegnazione
    | Permesso: assignments.delete
    */
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])
        ->name('assignments.destroy')
        ->middleware('permission:assignments.delete');

// ------------------------- Blocchi -------------------------
    /*
    | Elenco blocchi veicolo
    | Permesso: blocks.viewAny
    */
    Route::get('/blocks', [BlockController::class, 'index'])
        ->name('blocks.index')
        ->middleware('permission:blocks.viewAny');

    /*
    | Dettaglio blocco
    | Permesso: blocks.view
    */
    Route::get('/blocks/{block}', [BlockController::class, 'show'])
        ->name('blocks.show')
        ->middleware('permission:blocks.view');

    /*
    | Crea blocco
    | Permesso: blocks.create
    */
    Route::post('/blocks', [BlockController::class, 'store'])
        ->name('blocks.store')
        ->middleware('permission:blocks.create');

    /*
    | Aggiorna blocco
    | Permesso: blocks.update
    */
    Route::match(['put','patch'], '/blocks/{block}', [BlockController::class, 'update'])
        ->name('blocks.update')
        ->middleware('permission:blocks.update');

    /*
    | Elimina blocco
    | Permesso: blocks.delete
    */
    Route::delete('/blocks/{block}', [BlockController::class, 'destroy'])
        ->name('blocks.destroy')
        ->middleware('permission:blocks.delete');

    /*
    | Override blocco (es. forzare rimozione/deroga)
    | Permesso: blocks.override
    */
    Route::post('/blocks/{block}/override', [BlockController::class, 'override'])
        ->name('blocks.override')
        ->middleware('permission:blocks.override');

/*
|--------------------------------------------------------------------------
| FLOTTA – Documenti veicolo
|--------------------------------------------------------------------------
*/

    /*
    | Elenco documenti veicolo
    | Permesso: vehicle_documents.viewAny
    */
    Route::get('/vehicle-documents', [VehicleDocumentController::class, 'index'])
        ->name('vehicle-documents.index')
        ->middleware('permission:vehicle_documents.viewAny');

    /*
    | Dettaglio documento
    | Permesso: vehicle_documents.view
    */
    Route::get('/vehicle-documents/{document}', [VehicleDocumentController::class, 'show'])
        ->name('vehicle-documents.show')
        ->middleware('permission:vehicle_documents.view');

    /*
    | Carica/crea documento (upload, metadata)
    | Permesso: vehicle_documents.manage
    */
    Route::post('/vehicle-documents', [VehicleDocumentController::class, 'store'])
        ->name('vehicle-documents.store')
        ->middleware('permission:vehicle_documents.manage');

    /*
    | Aggiorna documento
    | Permesso: vehicle_documents.manage
    */
    Route::match(['put','patch'], '/vehicle-documents/{document}', [VehicleDocumentController::class, 'update'])
        ->name('vehicle-documents.update')
        ->middleware('permission:vehicle_documents.manage');

    /*
    | Elimina documento
    | Permesso: vehicle_documents.manage
    */
    Route::delete('/vehicle-documents/{document}', [VehicleDocumentController::class, 'destroy'])
        ->name('vehicle-documents.destroy')
        ->middleware('permission:vehicle_documents.manage');

/* --------------------------------------------------------------------------
| AMMINISTRAZIONE (solo admin via Gate 'manage.renters')
|-------------------------------------------------------------------------- */

    /*
     | Renter / Organizations (CRUD base – puoi ampliare dopo)
     | Permesso: manage.renters (definito in AuthServiceProvider)
     */
    Route::resource('organizations', OrganizationController::class)
        ->middleware('can:manage.renters')
        ->names([
            'index'   => 'organizations.index',
            'create'  => 'organizations.create',
            'store'   => 'organizations.store',
            'show'    => 'organizations.show',
            'edit'    => 'organizations.edit',
            'update'  => 'organizations.update',
            'destroy' => 'organizations.destroy',
        ]);

    /*
     | Alias admin per "Assegna veicoli" (usa la index attuale ma è visibile/visitabile solo agli admin)
     | Permesso: manage.renters (definito in AuthServiceProvider)
     */
    Route::get('/admin/assignments', [AssignmentController::class, 'index'])
        ->name('admin.assignments')
        ->middleware('can:manage.renters');

/*
|--------------------------------------------------------------------------
| REPORT & AUDIT
|--------------------------------------------------------------------------
*/

    /*
    | Report – indice / dispatcher
    | Permesso: reports.view
    */
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports.index')
        ->middleware('permission:reports.view');

    /*
    | Audit – log eventi/azioni
    | Permesso: audit.view
    */
    Route::get('/audit', [AuditController::class, 'index'])
        ->name('audit.index')
        ->middleware('permission:audit.view');
});
