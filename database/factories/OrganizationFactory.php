<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory: Organization
 * - type: admin | renter
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name'         => $this->faker->unique()->company(),
            'type'         => 'renter',
            'vat'          => $this->faker->optional()->bothify('IT###########'),
            'address_line' => $this->faker->streetAddress(),
            'city'         => $this->faker->city(),
            'province'     => $this->faker->stateAbbr(),
            'postal_code'  => $this->faker->postcode(),
            'country_code' => 'IT',
            'phone'        => $this->faker->phoneNumber(),
            'email'        => $this->faker->companyEmail(),
            'is_active'    => true,
        ];
    }

    /** Stato: Admin (proprietario parco) */
    public function admin(): self
    {
        return $this->state(fn () => ['type' => 'admin']);
    }

    /** Stato: Renter (noleggiatore) */
    public function renter(): self
    {
        return $this->state(fn () => ['type' => 'renter']);
    }
}
