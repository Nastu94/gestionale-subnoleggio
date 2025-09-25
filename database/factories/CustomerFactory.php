<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Factory: Customer (cliente del noleggiatore) */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->renter(),
            'name'            => $this->faker->name(),
            'email'           => $this->faker->unique()->safeEmail(),
            'phone'           => $this->faker->optional()->phoneNumber(),
            'doc_id_type'     => $this->faker->randomElement(['id','passport','license']),
            'doc_id_number'   => strtoupper($this->faker->unique()->bothify('??########')),
            'birthdate'       => $this->faker->date('Y-m-d', '2004-01-01'),
            'address_line'    => $this->faker->streetAddress(),
            'city'            => $this->faker->city(),
            'province'        => $this->faker->stateAbbr(),
            'postal_code'     => $this->faker->postcode(),
            'country_code'    => 'IT',
            'notes'           => $this->faker->optional()->sentence(),
        ];
    }
}
