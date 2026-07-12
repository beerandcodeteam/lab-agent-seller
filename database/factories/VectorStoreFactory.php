<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VectorStore;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VectorStore>
 */
class VectorStoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'openai_vector_store_id' => 'vs_'.Str::random(24),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
        ];
    }
}
