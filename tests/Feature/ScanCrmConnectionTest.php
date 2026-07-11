<?php

use App\Jobs\ScanCrmConnection;
use App\Models\CrmConnection;
use App\Models\CrmPerson;
use App\Models\CustomField;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(LookupSeeder::class);
});

/**
 * Static, repeatable fake of a small Pipedrive account (single page per
 * endpoint). Every request to the same endpoint returns the same payload,
 * so it survives being scanned twice (upsert test).
 */
function fakePipedriveAccount(): void
{
    $page = fn (array $data): array => [
        'data' => $data,
        'additional_data' => ['pagination' => ['more_items_in_collection' => false]],
    ];

    Http::fake([
        '*/pipelines*' => Http::response($page([
            ['id' => 1, 'name' => 'Vendas'],
            ['id' => 2, 'name' => 'Suporte'],
        ])),
        '*/stages*' => Http::response($page([
            ['id' => 11, 'name' => 'Lead', 'pipeline_id' => 1, 'order_nr' => 1],
            ['id' => 12, 'name' => 'Proposta', 'pipeline_id' => 1, 'order_nr' => 2],
            ['id' => 13, 'name' => 'Aberto', 'pipeline_id' => 2, 'order_nr' => 1],
        ])),
        '*/personFields*' => Http::response($page([
            ['id' => 101, 'name' => 'Nome', 'key' => 'name', 'field_type' => 'varchar', 'edit_flag' => false],
            ['id' => 102, 'name' => 'CPF', 'key' => 'abc', 'field_type' => 'varchar', 'edit_flag' => true],
            ['id' => 103, 'name' => 'Segmento', 'key' => 'def', 'field_type' => 'enum', 'edit_flag' => true],
        ])),
        '*/dealFields*' => Http::response($page([
            ['id' => 201, 'name' => 'Origem', 'key' => 'ghi', 'field_type' => 'enum', 'edit_flag' => true],
            ['id' => 202, 'name' => 'Prioridade', 'key' => 'jkl', 'field_type' => 'enum', 'edit_flag' => true],
        ])),
        '*/persons*' => Http::response($page([
            ['id' => 10, 'name' => 'Ana', 'email' => [['value' => 'ana@x.com', 'primary' => true]], 'phone' => [['value' => '111', 'primary' => true]]],
            ['id' => 20, 'name' => 'Bruno', 'email' => [['value' => 'bruno@x.com', 'primary' => true]], 'phone' => []],
            ['id' => 30, 'name' => 'Carla', 'email' => 'carla@x.com', 'phone' => null],
        ])),
        '*/deals*' => Http::response($page([
            ['id' => 1000, 'title' => 'Negócio A', 'value' => 500, 'pipeline_id' => 1, 'stage_id' => 11, 'person_id' => 10, 'status' => 'open'],
            ['id' => 2000, 'title' => 'Negócio B', 'value' => 900, 'pipeline_id' => 2, 'stage_id' => 13, 'person_id' => 20, 'status' => 'won'],
        ])),
    ]);
}

test('scan_imports_all_entities', function () {
    fakePipedriveAccount();

    $connection = CrmConnection::factory()->create();

    ScanCrmConnection::enqueue($connection);

    expect(Pipeline::where('crm_connection_id', $connection->id)->count())->toBe(2);
    expect(PipelineStage::whereIn('pipeline_id', $connection->pipelines()->pluck('id'))->count())->toBe(3);
    expect(CustomField::where('crm_connection_id', $connection->id)->count())->toBe(4); // 2 person + 2 deal (non-custom filtered out)
    expect(CrmPerson::where('crm_connection_id', $connection->id)->count())->toBe(3);
    expect(Deal::where('crm_connection_id', $connection->id)->count())->toBe(2);

    // Cross-references and normalisation resolved.
    $deal = Deal::where('external_id', '1000')->firstOrFail();
    expect($deal->pipeline->external_id)->toBe('1');
    expect($deal->pipelineStage->external_id)->toBe('11');
    expect($deal->person->external_id)->toBe('10');
    expect($deal->dealStatus->slug)->toBe('open');

    expect(CrmPerson::where('external_id', '30')->value('email'))->toBe('carla@x.com');
});

test('scan_upsert_does_not_duplicate', function () {
    fakePipedriveAccount();

    $connection = CrmConnection::factory()->create();

    ScanCrmConnection::enqueue($connection);
    ScanCrmConnection::enqueue($connection);

    expect(Pipeline::where('crm_connection_id', $connection->id)->count())->toBe(2);
    expect(PipelineStage::whereIn('pipeline_id', $connection->pipelines()->pluck('id'))->count())->toBe(3);
    expect(CustomField::where('crm_connection_id', $connection->id)->count())->toBe(4);
    expect(CrmPerson::where('crm_connection_id', $connection->id)->count())->toBe(3);
    expect(Deal::where('crm_connection_id', $connection->id)->count())->toBe(2);
});

test('scan_status_transitions_to_success', function () {
    fakePipedriveAccount();

    $connection = CrmConnection::factory()->create();

    $scan = ScanCrmConnection::enqueue($connection)->fresh();

    expect($scan->scanStatus->slug)->toBe('success');
    expect($scan->started_at)->not->toBeNull();
    expect($scan->finished_at)->not->toBeNull();
    expect($scan->pipelines_count)->toBe(2);
    expect($scan->custom_fields_count)->toBe(4);
    expect($scan->persons_count)->toBe(3);
    expect($scan->deals_count)->toBe(2);
});

test('scan_failure_sets_failed_status_and_keeps_partial_data', function () {
    Http::fake([
        '*/pipelines*' => Http::response(['data' => [['id' => 1, 'name' => 'Vendas']], 'additional_data' => ['pagination' => ['more_items_in_collection' => false]]]),
        '*/stages*' => Http::response(['data' => [], 'additional_data' => ['pagination' => ['more_items_in_collection' => false]]]),
        '*/personFields*' => Http::response(['data' => [], 'additional_data' => ['pagination' => ['more_items_in_collection' => false]]]),
        '*/dealFields*' => Http::response(['data' => [], 'additional_data' => ['pagination' => ['more_items_in_collection' => false]]]),
        // Persons paginate: pages 1 and 2 succeed, page 3 returns 429.
        '*/persons*' => Http::sequence()
            ->push(['data' => [['id' => 1, 'name' => 'P1', 'email' => 'p1@x.com']], 'additional_data' => ['pagination' => ['more_items_in_collection' => true, 'next_start' => 100]]])
            ->push(['data' => [['id' => 2, 'name' => 'P2', 'email' => 'p2@x.com']], 'additional_data' => ['pagination' => ['more_items_in_collection' => true, 'next_start' => 200]]])
            ->pushStatus(429),
        '*/deals*' => Http::response(['data' => [], 'additional_data' => ['pagination' => ['more_items_in_collection' => false]]]),
    ]);

    $connection = CrmConnection::factory()->create();

    $scan = ScanCrmConnection::enqueue($connection)->fresh();

    expect($scan->scanStatus->slug)->toBe('failed');
    expect($scan->finished_at)->not->toBeNull();
    expect($scan->error_message)->toContain('429');
    expect($scan->error_message)->toContain('/persons');

    // Persons from pages 1–2 stay imported; no rollback.
    expect(CrmPerson::where('crm_connection_id', $connection->id)->count())->toBe(2);
    expect(Pipeline::where('crm_connection_id', $connection->id)->count())->toBe(1);
});
