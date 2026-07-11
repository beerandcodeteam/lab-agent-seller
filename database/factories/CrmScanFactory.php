<?php

namespace Database\Factories;

use App\Models\CrmConnection;
use App\Models\CrmScan;
use App\Models\ScanStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmScan>
 */
class CrmScanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crm_connection_id' => CrmConnection::factory(),
            'scan_status_id' => $this->statusId('pending'),
            'pipelines_count' => 0,
            'custom_fields_count' => 0,
            'persons_count' => 0,
            'deals_count' => 0,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scan_status_id' => $this->statusId('pending'),
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scan_status_id' => $this->statusId('running'),
            'started_at' => now(),
            'finished_at' => null,
        ]);
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scan_status_id' => $this->statusId('success'),
            'pipelines_count' => fake()->numberBetween(1, 5),
            'custom_fields_count' => fake()->numberBetween(1, 10),
            'persons_count' => fake()->numberBetween(1, 50),
            'deals_count' => fake()->numberBetween(1, 30),
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scan_status_id' => $this->statusId('failed'),
            'error_message' => fake()->sentence(),
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
    }

    /**
     * Resolve a scan status id by slug, seeding the lookup if absent.
     */
    private function statusId(string $slug): int
    {
        return ScanStatus::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug)],
        )->id;
    }
}
