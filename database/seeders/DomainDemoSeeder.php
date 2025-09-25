<?php

namespace Database\Seeders;

use App\Models\{
    User, Organization, Location, Vehicle, VehicleDocument, VehicleState,
    VehicleAssignment, AssignmentConstraint, Customer, Rental, RentalChecklist,
    RentalPhoto, RentalDamage, VehicleBlock
};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/**
 * Popola un dataset minimale ma realistico per test end-to-end:
 * - 1 Admin org + 2 Renter org
 * - utenti (admin/renter)
 * - locations (admin/renter)
 * - 6 veicoli (admin)
 * - affidamenti ai renter
 * - blocchi, clienti, rentals con checklist/foto/danni
 * - log stati veicolo
 */
class DomainDemoSeeder extends Seeder
{
    public function run(): void
    {
        // === ORGS ===
        $adminOrg  = Organization::factory()->admin()->create([
            'name'  => 'Fleet Admin S.p.A.',
            'email' => 'fleet-admin@example.com',
        ]);

        $renterA   = Organization::factory()->renter()->create([
            'name'  => 'Renter Uno SRL',
            'email' => 'renter1@example.com',
        ]);

        $renterB   = Organization::factory()->renter()->create([
            'name'  => 'Renter Due SRL',
            'email' => 'renter2@example.com',
        ]);

        // === USERS ===
        $adminUser = User::factory()->create([
            'organization_id'   => $adminOrg->id,
            'name'              => 'Admin Owner',
            'email'             => 'admin@example.com',
            'password'          => Hash::make('password'),
        ]);

        $renterUserA = User::factory()->create([
            'organization_id'   => $renterA->id,
            'name'              => 'Renter A Owner',
            'email'             => 'renter.a@example.com',
            'password'          => Hash::make('password'),
        ]);

        $renterUserB = User::factory()->create([
            'organization_id'   => $renterB->id,
            'name'              => 'Renter B Owner',
            'email'             => 'renter.b@example.com',
            'password'          => Hash::make('password'),
        ]);

        // === LOCATIONS ===
        $adminLoc = Location::factory()->create([
            'organization_id' => $adminOrg->id,
            'name'            => 'Deposito Centrale',
            'city'            => 'Roma',
        ]);

        $renterALoc = Location::factory()->create([
            'organization_id' => $renterA->id,
            'name'            => 'Filiale Renter A',
            'city'            => 'Milano',
        ]);

        $renterBLoc = Location::factory()->create([
            'organization_id' => $renterB->id,
            'name'            => 'Filiale Renter B',
            'city'            => 'Torino',
        ]);

        // === VEHICLES (6) ===
        $vehicles = Vehicle::factory()
            ->count(6)
            ->state(fn () => ['admin_organization_id' => $adminOrg->id, 'default_pickup_location_id' => $adminLoc->id])
            ->create();

        // Stato iniziale: available per tutti
        foreach ($vehicles as $v) {
            VehicleState::create([
                'vehicle_id' => $v->id,
                'state'      => 'available',
                'started_at' => now()->subDays(10),
                'ended_at'   => now()->subDays(9),
                'reason'     => 'seed:init',
                'created_by' => $adminUser->id,
            ]);
        }

        // === VEHICLE DOCUMENTS (assicurazione) ===
        foreach ($vehicles as $v) {
            VehicleDocument::create([
                'vehicle_id'  => $v->id,
                'type'        => 'insurance',
                'number'      => 'POL' . str_pad((string)$v->id, 6, '0', STR_PAD_LEFT),
                'issue_date'  => now()->subYear()->toDateString(),
                'expiry_date' => now()->addMonths(2)->toDateString(),
                'status'      => 'valid',
            ]);
        }

        // === ASSIGNMENTS ===
        $now = now();

        // veicoli 1..3 → Renter A (aperti e attivi)
        $assignmentsA = collect();
        foreach ($vehicles->slice(0, 3) as $v) {
            $a = VehicleAssignment::create([
                'vehicle_id'    => $v->id,
                'renter_org_id' => $renterA->id,
                'start_at'      => $now->copy()->subDays(7),
                'end_at'        => null,
                'status'        => 'active',
                'mileage_start' => $v->mileage_current ?? 30000,
                'created_by'    => $adminUser->id,
            ]);
            $assignmentsA->push($a);

            // Stato: assigned (corrente)
            VehicleState::create([
                'vehicle_id' => $v->id,
                'state'      => 'assigned',
                'started_at' => $now->copy()->subDays(7),
                'ended_at'   => null,
                'reason'     => 'seed:assignment',
                'created_by' => $adminUser->id,
            ]);
        }

        // veicoli 4..5 → Renter B (attivi con finestra)
        $assignmentsB = collect();
        foreach ($vehicles->slice(3, 2) as $v) {
            $a = VehicleAssignment::create([
                'vehicle_id'    => $v->id,
                'renter_org_id' => $renterB->id,
                'start_at'      => $now->copy()->subDays(3),
                'end_at'        => $now->copy()->addDays(10),
                'status'        => 'active',
                'mileage_start' => $v->mileage_current ?? 35000,
                'created_by'    => $adminUser->id,
            ]);
            $assignmentsB->push($a);

            VehicleState::create([
                'vehicle_id' => $v->id,
                'state'      => 'assigned',
                'started_at' => $now->copy()->subDays(3),
                'ended_at'   => null,
                'reason'     => 'seed:assignment',
                'created_by' => $adminUser->id,
            ]);
        }

        // veicolo 6 → resta libero (available) con stato corrente disponibile
        // (già creato sopra come 'available' - ended_at impostato; per chiarezza aggiorniamo lo stato corrente)
        $freeVehicle = $vehicles->last();

        // === CUSTOMERS ===
        $customersA = Customer::factory()->count(3)->state(fn () => ['organization_id' => $renterA->id])->create();
        $customersB = Customer::factory()->count(2)->state(fn () => ['organization_id' => $renterB->id])->create();

        // === BLOCKS (il renter può aprire blocchi sui veicoli affidati) ===
        $blockStart = $now->copy()->addDays(1)->setTime(9, 0);
        $blockEnd   = $now->copy()->addDays(1)->setTime(18, 0);

        VehicleBlock::create([
            'vehicle_id'      => $assignmentsA[0]->vehicle_id,
            'organization_id' => $renterA->id,        // creato dal renter
            'type'            => 'maintenance',
            'start_at'        => $blockStart,
            'end_at'          => $blockEnd,
            'status'          => 'scheduled',
            'reason'          => 'Tagliando programmato',
            'created_by'      => $renterUserA->id,
        ]);

        // === RENTALS (Renter A) ===
        /** Rental 1: completato (pickup e return) */
        $assignA1 = $assignmentsA[0];
        $vehA1    = $assignA1->vehicle_id;
        $custA1   = $customersA[0];

        $r1_pick  = $now->copy()->subDays(2)->setTime(10, 0);
        $r1_ret   = $now->copy()->subDays(1)->setTime(10, 0);

        $rental1 = Rental::create([
            'organization_id'    => $renterA->id,
            'vehicle_id'         => $vehA1,
            'assignment_id'      => $assignA1->id,
            'customer_id'        => $custA1->id,
            'planned_pickup_at'  => $r1_pick,
            'planned_return_at'  => $r1_ret,
            'actual_pickup_at'   => $r1_pick,
            'actual_return_at'   => $r1_ret,
            'pickup_location_id' => $renterALoc->id,
            'return_location_id' => $renterALoc->id,
            'status'             => 'checked_in',
            'mileage_out'        => 40000,
            'mileage_in'         => 40250,
            'fuel_out_percent'   => 80,
            'fuel_in_percent'    => 70,
            'created_by'         => $renterUserA->id,
        ]);

        // Checklists
        RentalChecklist::create([
            'rental_id'          => $rental1->id,
            'type'               => 'pickup',
            'mileage'            => 40000,
            'fuel_percent'       => 80,
            'cleanliness'        => 'good',
            'signed_by_customer' => true,
            'signed_by_operator' => true,
            'checklist_json'     => json_encode(['triangolo' => true, 'ruota_scorta' => true]),
            'created_by'         => $renterUserA->id,
        ]);

        RentalChecklist::create([
            'rental_id'          => $rental1->id,
            'type'               => 'return',
            'mileage'            => 40250,
            'fuel_percent'       => 70,
            'cleanliness'        => 'fair',
            'signed_by_customer' => true,
            'signed_by_operator' => true,
            'checklist_json'     => json_encode(['danni_nuovi' => false]),
            'created_by'         => $renterUserA->id,
        ]);

        // Danni (esempio al rientro)
        RentalDamage::create([
            'rental_id'    => $rental1->id,
            'phase'        => 'return',
            'area'         => 'front_bumper',
            'severity'     => 'low',
            'description'  => 'Graffio superficiale',
            'estimated_cost' => 80.00,
            'photos_count' => 0,
            'created_by'   => $renterUserA->id,
        ]);

        // Stato veicolo: rented durante il periodo
        VehicleState::create([
            'vehicle_id' => $vehA1,
            'state'      => 'rented',
            'started_at' => $r1_pick,
            'ended_at'   => $r1_ret,
            'reason'     => 'seed:rental1',
            'created_by' => $renterUserA->id,
        ]);

        // === Rental 2: attivo (in_use) su altro veicolo di Renter A ===
        $assignA2 = $assignmentsA[1];
        $vehA2    = $assignA2->vehicle_id;
        $custA2   = $customersA[1];

        $r2_pick  = $now->copy()->subHours(3);
        $r2_ret   = $now->copy()->addHours(21);

        $rental2 = Rental::create([
            'organization_id'    => $renterA->id,
            'vehicle_id'         => $vehA2,
            'assignment_id'      => $assignA2->id,
            'customer_id'        => $custA2->id,
            'planned_pickup_at'  => $r2_pick,
            'planned_return_at'  => $r2_ret,
            'actual_pickup_at'   => $r2_pick,
            'actual_return_at'   => null,
            'pickup_location_id' => $renterALoc->id,
            'return_location_id' => $renterALoc->id,
            'status'             => 'in_use',
            'mileage_out'        => 50000,
            'fuel_out_percent'   => 90,
            'created_by'         => $renterUserA->id,
        ]);

        RentalChecklist::create([
            'rental_id'          => $rental2->id,
            'type'               => 'pickup',
            'mileage'            => 50000,
            'fuel_percent'       => 90,
            'cleanliness'        => 'excellent',
            'signed_by_customer' => true,
            'signed_by_operator' => true,
            'created_by'         => $renterUserA->id,
        ]);

        VehicleState::create([
            'vehicle_id' => $vehA2,
            'state'      => 'rented',
            'started_at' => $r2_pick,
            'ended_at'   => null,
            'reason'     => 'seed:rental2',
            'created_by' => $renterUserA->id,
        ]);

        // === Rental 3: prenotato (reserved) domani, NON sovrapposto al blocco
        $assignA3 = $assignmentsA[2];
        $vehA3    = $assignA3->vehicle_id;
        $custA3   = $customersA[2];

        $r3_pick  = $now->copy()->addDays(2)->setTime(9, 0);
        $r3_ret   = $now->copy()->addDays(2)->setTime(18, 0);

        $rental3 = Rental::create([
            'organization_id'    => $renterA->id,
            'vehicle_id'         => $vehA3,
            'assignment_id'      => $assignA3->id,
            'customer_id'        => $custA3->id,
            'planned_pickup_at'  => $r3_pick,
            'planned_return_at'  => $r3_ret,
            'status'             => 'reserved',
            'pickup_location_id' => $renterALoc->id,
            'return_location_id' => $renterALoc->id,
            'created_by'         => $renterUserA->id,
        ]);

        // === RENTALS (Renter B) ===
        $assignB1 = $assignmentsB[0];
        $vehB1    = $assignB1->vehicle_id;
        $custB1   = $customersB[0];

        $b1_pick  = $now->copy()->addDays(1)->setTime(9, 0);
        $b1_ret   = $now->copy()->addDays(1)->setTime(17, 0);

        $rentalB1 = Rental::create([
            'organization_id'    => $renterB->id,
            'vehicle_id'         => $vehB1,
            'assignment_id'      => $assignB1->id,
            'customer_id'        => $custB1->id,
            'planned_pickup_at'  => $b1_pick,
            'planned_return_at'  => $b1_ret,
            'status'             => 'reserved',
            'pickup_location_id' => $renterBLoc->id,
            'return_location_id' => $renterBLoc->id,
            'created_by'         => $renterUserB->id,
        ]);

        // === Constraints (esempio opzionale)
        AssignmentConstraint::create([
            'assignment_id' => $assignA1->id,
            'max_km'        => 5000,
            'min_driver_age'=> 21,
        ]);
    }
}
