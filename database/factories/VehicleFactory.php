<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\Organization;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        // VIN: 70% presente (univoco), 30% NULL — senza I,O,Q
        $vin = $this->faker->boolean(70)
            ? strtoupper($this->faker->unique()->regexify('[A-HJ-NPR-Z0-9]{17}'))
            : null;

        return [
            'admin_organization_id'      => Organization::factory()->admin(),
            'vin'                        => $vin,
            'plate'                      => strtoupper($this->faker->unique()->bothify('??###??')),
            'make'                       => $this->faker->randomElement(['Fiat','Ford','Renault','VW','Toyota','Peugeot']),
            'model'                      => $this->faker->randomElement(['Panda','Focus','Clio','Golf','Yaris','208']),
            'year'                       => $this->faker->numberBetween(2018, 2024),
            'color'                      => $this->faker->safeColorName(),
            'fuel_type'                  => $this->faker->randomElement(['petrol','diesel','hybrid','electric']),
            'transmission'               => $this->faker->randomElement(['manual','automatic']),
            'seats'                      => $this->faker->numberBetween(4, 7),
            'segment'                    => $this->faker->randomElement(['compact','sedan','suv','van']),
            'mileage_current'            => $this->faker->numberBetween(10000, 85000),
            'default_pickup_location_id' => Location::factory(),
            'is_active'                  => true,
            'notes'                      => $this->faker->optional()->sentence(),
        ];
    }
}
