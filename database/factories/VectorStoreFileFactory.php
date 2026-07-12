<?php

namespace Database\Factories;

use App\Models\FileIndexingStatus;
use App\Models\VectorStore;
use App\Models\VectorStoreFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VectorStoreFile>
 */
class VectorStoreFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vector_store_id' => VectorStore::factory(),
            'openai_document_id' => 'doc_'.Str::random(24),
            'openai_file_id' => 'file_'.Str::random(24),
            'filename' => fake()->word().'.pdf',
            'file_indexing_status_id' => $this->statusId('pending'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_indexing_status_id' => $this->statusId('pending'),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_indexing_status_id' => $this->statusId('in_progress'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_indexing_status_id' => $this->statusId('completed'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_indexing_status_id' => $this->statusId('failed'),
        ]);
    }

    /**
     * Resolve a file indexing status id by slug, seeding the lookup if absent.
     */
    private function statusId(string $slug): int
    {
        return FileIndexingStatus::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug)],
        )->id;
    }
}
