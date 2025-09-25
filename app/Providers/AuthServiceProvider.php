<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

// Models
use App\Models\{
    Vehicle, VehicleDocument, VehicleAssignment, VehicleBlock,
    Customer, Rental, Location
};

// Policies
use App\Policies\{
    VehiclePolicy, VehicleDocumentPolicy, VehicleAssignmentPolicy, VehicleBlockPolicy,
    CustomerPolicy, RentalPolicy, LocationPolicy
};

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Vehicle::class           => VehiclePolicy::class,
        VehicleDocument::class   => VehicleDocumentPolicy::class,
        VehicleAssignment::class => VehicleAssignmentPolicy::class,
        VehicleBlock::class      => VehicleBlockPolicy::class,
        Customer::class          => CustomerPolicy::class,
        Rental::class            => RentalPolicy::class,
        Location::class          => LocationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Admin = onnipotente: se l'utente appartiene a un'organizzazione admin,
        // bypassa i singoli controlli di ability.
        Gate::before(function ($user, string $ability) {
            return ($user?->organization?->isAdmin() ?? false) ? true : null;
        });
    }
}
