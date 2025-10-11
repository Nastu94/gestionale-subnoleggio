<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Role, Permission};
use App\Models\{User, Organization};

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Pulisci cache permessi
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        // 2) Elenco permessi (resource.action)
        $perms = [
            // Vehicles & documents
            'vehicles.viewAny', 'vehicles.view', 'vehicles.create', 'vehicles.update', 'vehicles.delete',
            'vehicles.update_mileage', 'vehicles.manage_maintenance', 'vehicles.restore', 'vehicles.assign_location',
            'vehicle_documents.viewAny', 'vehicle_documents.view', 'vehicle_documents.create', 'vehicle_documents.update', 'vehicle_documents.delete', 'vehicle_documents.manage',
            'vehicle_pricing.viewAny', 'vehicle_pricing.view', 'vehicle_pricing.create', 'vehicle_pricing.update', 'vehicle_pricing.delete',
            'vehicle_pricing.publish', 'vehicle_pricing.archive',

            // Assignments
            'assignments.viewAny', 'assignments.view', 'assignments.create', 'assignments.update', 'assignments.delete',

            // Blocks
            'blocks.viewAny', 'blocks.view', 'blocks.create', 'blocks.update', 'blocks.delete', 'blocks.override',

            // Customers
            'customers.viewAny', 'customers.view', 'customers.create', 'customers.update', 'customers.delete',

            // Rentals
            'rentals.viewAny', 'rentals.view', 'rentals.create', 'rentals.update', 'rentals.delete',

            // Locations
            'locations.viewAny', 'locations.view', 'locations.create', 'locations.update', 'locations.delete',

            // Audit/Report
            'audit.view', 'reports.view',
        ];

        foreach ($perms as $p) {
            Permission::findOrCreate($p, $guard);
        }

        // 3) Ruoli
        $admin  = Role::findOrCreate('admin',  $guard);
        $renter = Role::findOrCreate('renter', $guard);

        // 4) Assegna permessi ai ruoli
        // Admin: tutto
        $admin->syncPermissions(Permission::all());

        // Renter: set mirato + i due granulari su veicoli
        $renterPerms = [
            'vehicles.viewAny','vehicles.view',
            'vehicles.update_mileage','vehicles.manage_maintenance', 'vehicles.assign_location',
            'vehicle_pricing.viewAny','vehicle_pricing.view','vehicle_pricing.create','vehicle_pricing.update', 'vehicle_pricing.delete',
            'vehicle_pricing.publish', 'vehicle_pricing.archive',
            'vehicle_documents.viewAny','vehicle_documents.view', 'vehicle_documents.update',

            'assignments.viewAny','assignments.view',

            'blocks.viewAny','blocks.view','blocks.create','blocks.update','blocks.delete',

            'customers.viewAny','customers.view','customers.create','customers.update','customers.delete',

            'rentals.viewAny','rentals.view','rentals.create','rentals.update','rentals.delete',

            'locations.viewAny','locations.view', 'locations.create','locations.update', 'locations.delete',
            // opzionali:
            // 'audit.view','reports.view',
        ];
        $renter->syncPermissions($renterPerms);

        // 5) Assegna ruoli in base al tipo di organizzazione
        $admins  = User::whereHas('organization', fn($q) => $q->where('type','admin'))->get();
        $renters = User::whereHas('organization', fn($q) => $q->where('type','renter'))->get();

        foreach ($admins as $u)  { $u->syncRoles(['admin']); }
        foreach ($renters as $u) { $u->syncRoles(['renter']); }

        // 6) Ricostruisci cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
