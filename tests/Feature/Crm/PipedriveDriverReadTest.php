<?php

use App\Services\Crm\Drivers\PipedriveDriver;
use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function pipedriveReadDriver(): PipedriveDriver
{
    return new PipedriveDriver;
}

test('fetchDeal returns title, value, stage, status and pipeline', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => true, 'data' => [
            'id' => 42,
            'title' => 'Negócio grande',
            'value' => 1500,
            'stage_id' => 7,
            'status' => 'open',
            'pipeline_id' => 3,
        ]]),
    ]);

    $deal = pipedriveReadDriver()->fetchDeal('tkn', '42');

    expect($deal['title'])->toBe('Negócio grande')
        ->and($deal['value'])->toBe(1500)
        ->and($deal['stage_external_id'])->toBe('7')
        ->and($deal['status'])->toBe('open')
        ->and($deal['pipeline_external_id'])->toBe('3');
});

test('fetchDeal marks missing stage and status as null', function () {
    Http::fake([
        '*/deals/99*' => Http::response(['success' => true, 'data' => [
            'id' => 99,
            'title' => 'Sem estágio',
        ]]),
    ]);

    $deal = pipedriveReadDriver()->fetchDeal('tkn', '99');

    expect($deal['stage_external_id'])->toBeNull()
        ->and($deal['status'])->toBeNull()
        ->and($deal['value'])->toBeNull();
});

test('fetchDeal throws on a live 404', function () {
    Http::fake([
        '*/deals/404*' => Http::response(['success' => false], 404),
    ]);

    expect(fn () => pipedriveReadDriver()->fetchDeal('tkn', '404'))->toThrow(CrmApiException::class);
});

test('fetchDeal throws on a non-2xx response', function () {
    Http::fake([
        '*/deals/500*' => Http::response(['success' => false], 500),
    ]);

    expect(fn () => pipedriveReadDriver()->fetchDeal('tkn', '500'))->toThrow(CrmApiException::class);
});

test('fetchDeal throws on a connection failure', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(fn () => pipedriveReadDriver()->fetchDeal('tkn', '42'))->toThrow(CrmApiException::class);
});

test('fetchDealStageChanges filters the flow to stage changes only', function () {
    Http::fake([
        '*/deals/42/flow*' => Http::response(['success' => true, 'data' => [
            ['object' => 'dealChange', 'timestamp' => '2026-01-01 10:00:00', 'data' => [
                'field_key' => 'stage_id', 'old_value' => 1, 'new_value' => 2, 'log_time' => '2026-01-01 10:00:00',
            ]],
            ['object' => 'dealChange', 'timestamp' => '2026-01-02 11:00:00', 'data' => [
                'field_key' => 'title', 'old_value' => 'a', 'new_value' => 'b',
            ]],
            ['object' => 'note', 'timestamp' => '2026-01-03 12:00:00', 'data' => ['content' => 'oi']],
        ]]),
    ]);

    $changes = iterator_to_array(pipedriveReadDriver()->fetchDealStageChanges('tkn', '42'));

    expect($changes)->toHaveCount(1)
        ->and($changes[0]['from_stage_external_id'])->toBe('1')
        ->and($changes[0]['to_stage_external_id'])->toBe('2')
        ->and($changes[0]['changed_at'])->toBe('2026-01-01 10:00:00');
});

test('fetchDealComments returns deal comments', function () {
    Http::fake([
        '*/deals/42/flow*' => Http::response(['success' => true, 'data' => [
            ['object' => 'dealChange', 'timestamp' => '2026-01-01', 'data' => ['field_key' => 'stage_id', 'old_value' => 1, 'new_value' => 2]],
            ['object' => 'note', 'timestamp' => '2026-01-03 12:00:00', 'data' => ['content' => 'primeiro contato', 'add_time' => '2026-01-03 12:00:00']],
        ]]),
    ]);

    $comments = iterator_to_array(pipedriveReadDriver()->fetchDealComments('tkn', '42'));

    expect($comments)->toHaveCount(1)
        ->and($comments[0]['content'])->toBe('primeiro contato')
        ->and($comments[0]['created_at'])->toBe('2026-01-03 12:00:00');
});

test('fetchNotes merges and labels deal and person notes', function () {
    Http::fake([
        '*notes?*deal_id=42*' => Http::response(['success' => true, 'data' => [
            ['id' => 1, 'content' => 'nota do deal', 'add_time' => '2026-01-01'],
        ]]),
        '*notes?*person_id=9*' => Http::response(['success' => true, 'data' => [
            ['id' => 2, 'content' => 'nota da pessoa', 'add_time' => '2026-01-02'],
        ]]),
    ]);

    $notes = collect(pipedriveReadDriver()->fetchNotes('tkn', '42', '9'));

    expect($notes)->toHaveCount(2)
        ->and($notes->firstWhere('source', 'deal')['content'])->toBe('nota do deal')
        ->and($notes->firstWhere('source', 'person')['content'])->toBe('nota da pessoa');
});

test('fetchNotes returns person notes even without a deal', function () {
    Http::fake([
        '*notes?*person_id=9*' => Http::response(['success' => true, 'data' => [
            ['id' => 2, 'content' => 'nota da pessoa', 'add_time' => '2026-01-02'],
        ]]),
    ]);

    $notes = collect(pipedriveReadDriver()->fetchNotes('tkn', null, '9'));

    expect($notes)->toHaveCount(1)
        ->and($notes[0]['source'])->toBe('person');
});

test('fetchPipelinesWithStages returns pipelines with stage id and name', function () {
    Http::fake([
        '*/pipelines*' => Http::response(['success' => true, 'data' => [
            ['id' => 1, 'name' => 'Vendas'],
            ['id' => 2, 'name' => 'Suporte'],
        ]]),
        '*/stages*' => Http::response(['success' => true, 'data' => [
            ['id' => 10, 'name' => 'Lead', 'pipeline_id' => 1],
            ['id' => 11, 'name' => 'Ganho', 'pipeline_id' => 1],
            ['id' => 20, 'name' => 'Aberto', 'pipeline_id' => 2],
        ]]),
    ]);

    $pipelines = collect(pipedriveReadDriver()->fetchPipelinesWithStages('tkn'));

    expect($pipelines)->toHaveCount(2);

    $vendas = $pipelines->firstWhere('id', '1');
    expect($vendas['name'])->toBe('Vendas')
        ->and(collect($vendas['stages'])->pluck('id')->all())->toBe(['10', '11'])
        ->and(collect($vendas['stages'])->pluck('name')->all())->toBe(['Lead', 'Ganho']);
});
