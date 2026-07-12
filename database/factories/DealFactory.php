<?php

namespace Database\Factories;

use App\Models\CrmConnection;
use App\Models\Deal;
use App\Models\DealStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crm_connection_id' => CrmConnection::factory(),
            'deal_status_id' => fn (): int => $this->statusId('open', 'Aberto'),
            'external_id' => (string) fake()->unique()->numberBetween(1, 1_000_000),
            'title' => fake()->sentence(3),
            'value' => fake()->randomFloat(2, 100, 10_000),
        ];
    }

    /**
     * An open deal.
     */
    public function open(): static
    {
        return $this->state(fn (): array => ['deal_status_id' => $this->statusId('open', 'Aberto')]);
    }

    /**
     * A won (closed) deal.
     */
    public function won(): static
    {
        return $this->state(fn (): array => ['deal_status_id' => $this->statusId('won', 'Ganho')]);
    }

    /**
     * A lost (closed) deal.
     */
    public function lost(): static
    {
        return $this->state(fn (): array => ['deal_status_id' => $this->statusId('lost', 'Perdido')]);
    }

    /**
     * Resolve (creating if needed) a deal_status lookup id by slug.
     */
    private function statusId(string $slug, string $name): int
    {
        return DealStatus::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
    }
}
