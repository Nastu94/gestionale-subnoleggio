<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Rental;
use App\Models\VehicleAssignment;
use App\Models\VehicleBlock;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Policies\CustomerPolicy;
use App\Policies\LocationPolicy;
use App\Policies\RentalPolicy;
use App\Policies\VehicleAssignmentPolicy;
use App\Policies\VehicleBlockPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\VehicleDocumentPolicy;

/**
 * AuthServiceProvider
 *
 * - Registra il mapping Model → Policy (explicito, così non dipendiamo dall'auto-discovery).
 * - Imposta Gate::before per consentire a chi ha ruolo 'admin' di bypassare i controlli.
 * - Non modifichiamo variabili/nomi usati altrove: le Policy esistenti rimangono uguali.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mappa dei Model alle rispettive Policy.
     * Aggancia le policy che mi hai passato nel file condiviso.
     *
     * NB: assicurati che i namespace corrispondano ai tuoi file reali.
     */
    protected $policies = [
        // Anagrafiche
        Customer::class            => CustomerPolicy::class,
        Location::class            => LocationPolicy::class,

        // Noleggi
        Rental::class              => RentalPolicy::class,
        VehicleAssignment::class   => VehicleAssignmentPolicy::class,
        VehicleBlock::class        => VehicleBlockPolicy::class,

        // Flotta
        Vehicle::class             => VehiclePolicy::class,
        VehicleDocument::class     => VehicleDocumentPolicy::class,
    ];

    /**
     * Bootstrap di autenticazione/autorizzazione.
     */
    public function boot(): void
    {
        // 1) Registra le Policy sopra
        $this->registerPolicies();

        /**
         * 2) Admin = “superuser”
         *
         * Se l'utente ha ruolo 'admin', consenti tutto (true) prima di valutare Policy/Permission.
         * Restituisci null per lasciare la valutazione normale.
         *
         * Usiamo Spatie\Permission: hasRole('admin') è immediato e cache-friendly.
         */
        Gate::before(function ($user, string $ability = null) {
            // Evita errori quando $user è null (ospite)
            if (!$user) {
                return null;
            }

            return $user->hasRole('admin') ? true : null;
        });
        
        /**
         * Abilità esplicita per area admin "Gestione renter"
         * - Vero solo se l'utente ha ruolo 'admin'
         */
        Gate::define('manage.renters', fn($user) => $user->hasRole('admin'));
    }
}
