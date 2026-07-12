<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LookupSeeder extends Seeder
{
    /**
     * Canonical lookup values keyed by table.
     *
     * @var array<string, array<int, array{name: string, slug: string}>>
     */
    private const LOOKUPS = [
        'crm_providers' => [
            ['name' => 'Pipedrive', 'slug' => 'pipedrive'],
        ],
        'scan_statuses' => [
            ['name' => 'Pendente', 'slug' => 'pending'],
            ['name' => 'Em andamento', 'slug' => 'running'],
            ['name' => 'Sucesso', 'slug' => 'success'],
            ['name' => 'Erro', 'slug' => 'failed'],
        ],
        'custom_field_entities' => [
            ['name' => 'Negócio', 'slug' => 'deal'],
            ['name' => 'Pessoa', 'slug' => 'person'],
            ['name' => 'Organização', 'slug' => 'organization'],
        ],
        'deal_statuses' => [
            ['name' => 'Aberto', 'slug' => 'open'],
            ['name' => 'Ganho', 'slug' => 'won'],
            ['name' => 'Perdido', 'slug' => 'lost'],
        ],
        'message_roles' => [
            ['name' => 'Cliente', 'slug' => 'user'],
            ['name' => 'Agente', 'slug' => 'assistant'],
        ],
        'file_indexing_statuses' => [
            ['name' => 'Pendente', 'slug' => 'pending'],
            ['name' => 'Em processamento', 'slug' => 'in_progress'],
            ['name' => 'Concluído', 'slug' => 'completed'],
            ['name' => 'Falha', 'slug' => 'failed'],
        ],
    ];

    /**
     * Seed the lookup tables idempotently by slug.
     */
    public function run(): void
    {
        $now = now();

        foreach (self::LOOKUPS as $table => $rows) {
            foreach ($rows as $row) {
                $values = ['name' => $row['name'], 'updated_at' => $now];

                if ($table === 'crm_providers') {
                    $values['is_active'] = true;
                }

                DB::table($table)->updateOrInsert(
                    ['slug' => $row['slug']],
                    $values + ['created_at' => $now],
                );
            }
        }
    }
}
