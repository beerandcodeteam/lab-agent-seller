<?php

namespace Database\Factories;

use App\Models\CrmConnection;
use App\Models\CrmProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmConnection>
 */
class CrmConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'crm_provider_id' => CrmProvider::firstOrCreate(
                ['slug' => 'pipedrive'],
                ['name' => 'Pipedrive', 'is_active' => true],
            )->id,
            'api_token' => fake()->sha256(),
            'last_validated_at' => now(),
        ];
    }
}
