<?php

use App\Models\CrmConnection;
use App\Models\CrmPerson;
use App\Models\CustomField;
use App\Models\CustomFieldEntity;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;

test('crm_model_graph_wired', function () {
    $connection = CrmConnection::factory()->create();

    $pipeline = Pipeline::create([
        'crm_connection_id' => $connection->id,
        'external_id' => 'p-1',
        'name' => 'Vendas',
    ]);

    $stage = PipelineStage::create([
        'pipeline_id' => $pipeline->id,
        'external_id' => 's-1',
        'name' => 'Qualificação',
        'order_index' => 1,
    ]);

    $person = CrmPerson::factory()->for($connection)->create();

    $entity = CustomFieldEntity::firstOrCreate(
        ['slug' => 'deal'],
        ['name' => 'Negócio'],
    );

    $customField = CustomField::create([
        'crm_connection_id' => $connection->id,
        'custom_field_entity_id' => $entity->id,
        'external_id' => 'cf-1',
        'name' => 'Orçamento',
        'field_key' => 'budget',
        'field_type' => 'monetary',
    ]);

    $deal = Deal::create([
        'crm_connection_id' => $connection->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stage->id,
        'crm_person_id' => $person->id,
        'external_id' => 'd-1',
        'title' => 'Negócio grande',
        'value' => 1500.50,
    ]);

    expect($pipeline->stages->pluck('id'))->toContain($stage->id);
    expect($deal->person->id)->toBe($person->id);
    expect($deal->pipeline->id)->toBe($pipeline->id);
    expect($deal->pipelineStage->id)->toBe($stage->id);
    expect($customField->entity->slug)->toBe('deal');
    expect($stage->pipeline->id)->toBe($pipeline->id);
    expect($connection->deals->pluck('id'))->toContain($deal->id);
});
