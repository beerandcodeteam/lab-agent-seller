<?php

namespace Database\Factories;

use App\Models\CrmConnection;
use App\Models\CrmPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmPerson>
 */
class CrmPersonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crm_connection_id' => CrmConnection::factory(),
            'external_id' => (string) fake()->unique()->numberBetween(1, 1_000_000),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
