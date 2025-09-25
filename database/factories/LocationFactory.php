<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Factory: Location (sedi Admin o Renter) */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->renter(),
            'name'            => 'Sede ' . $this->faker->city(),
            'address_line'    => $this->faker->streetAddress(),
            'city'            => $this->faker->city(),
            'province'        => $this->faker->stateAbbr(),
            'postal_code'     => $this->faker->postcode(),
            'country_code'    => 'IT',
            'lat'             => $this->faker->latitude(36.0, 46.5),
            'lng'             => $this->faker->longitude(6.5, 18.5),
            'notes'           => $this->faker->optional()->sentence(),
        ];
    }
}
