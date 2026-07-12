<?php

use App\Ai\Tools\GetDealCommentsTool;
use App\Ai\Tools\GetDealDataTool;
use App\Ai\Tools\GetDealNotesTool;
use App\Ai\Tools\GetDealStageHistoryTool;
use App\Ai\Tools\ListPipelinesTool;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\CrmPerson;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

/**
 * Build a conversation whose company owns a Pipedrive connection and whose
 * client matches a person (and, unless $withDeal is false, an open deal 42).
 *
 * @return array{conversation: Conversation, connection: CrmConnection}
 */
function readContext(string $clientEmail = 'cliente@empresa.com', bool $withDeal = true): array
{
    $company = User::factory()->create();
    $connection = CrmConnection::factory()->for($company)->create(['api_token' => 'tkn']);
    $client = Client::factory()->create(['email' => $clientEmail]);
    $conversation = Conversation::factory()->create([
        'user_id' => $company->id,
        'client_id' => $client->id,
    ]);
    $person = CrmPerson::factory()->for($connection)->create([
        'external_id' => 'p-1',
        'email' => $clientEmail,
    ]);

    if ($withDeal) {
        $pipeline = Pipeline::create([
            'crm_connection_id' => $connection->id,
            'external_id' => 'pipe-1',
            'name' => 'Vendas',
        ]);
        Deal::factory()->for($connection)->open()->create([
            'crm_person_id' => $person->id,
            'pipeline_id' => $pipeline->id,
            'external_id' => '42',
        ]);
    }

    return ['conversation' => $conversation, 'connection' => $connection];
}

test('every read tool exposes an empty input schema', function () {
    $conversation = Conversation::factory()->create();
    $schema = new JsonSchemaTypeFactory;

    $tools = [
        GetDealDataTool::class,
        GetDealStageHistoryTool::class,
        GetDealCommentsTool::class,
        GetDealNotesTool::class,
        ListPipelinesTool::class,
    ];

    foreach ($tools as $tool) {
        expect((new $tool($conversation))->schema($schema))->toBe([]);
    }
});

test('GetDealData returns live title, value, stage name and status', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => true, 'data' => [
            'id' => 42, 'title' => 'Negócio grande', 'value' => 1500, 'stage_id' => 7, 'status' => 'open', 'pipeline_id' => 'pipe-1',
        ]]),
        '*/stages*' => Http::response(['success' => true, 'data' => [
            ['id' => 7, 'name' => 'Proposta enviada', 'pipeline_id' => 'pipe-1'],
        ]]),
        '*/pipelines*' => Http::response(['success' => true, 'data' => [
            ['id' => 'pipe-1', 'name' => 'Vendas'],
        ]]),
    ]);

    ['conversation' => $conversation] = readContext();

    $result = (new GetDealDataTool($conversation))->handle(new Request);
    $data = json_decode($result, true);

    expect($data['negocio']['titulo'])->toBe('Negócio grande')
        ->and($data['negocio']['valor'])->toBe(1500)
        ->and($data['negocio']['estagio'])->toBe('Proposta enviada')
        ->and($data['negocio']['status'])->toBe('open');
});

test('GetDealData marks missing stage and status with explicit markers, not "sem deal"', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => true, 'data' => [
            'id' => 42, 'title' => 'Sem estágio', 'value' => null,
        ]]),
    ]);

    ['conversation' => $conversation] = readContext();

    $result = (new GetDealDataTool($conversation))->handle(new Request);

    expect($result)->toContain('(sem estágio)')
        ->and($result)->toContain('(desconhecido)')
        ->and($result)->not->toContain('Nenhum negócio');
});

test('GetDealStageHistory returns only stage changes with names', function () {
    Http::fake([
        '*/deals/42/flow*' => Http::response(['success' => true, 'data' => [
            ['object' => 'dealChange', 'timestamp' => '2026-01-01 10:00:00', 'data' => [
                'field_key' => 'stage_id', 'old_value' => 1, 'new_value' => 2, 'log_time' => '2026-01-01 10:00:00',
            ]],
            ['object' => 'dealChange', 'data' => ['field_key' => 'title', 'old_value' => 'a', 'new_value' => 'b']],
            ['object' => 'note', 'data' => ['content' => 'oi']],
        ]]),
        '*/stages*' => Http::response(['success' => true, 'data' => [
            ['id' => 1, 'name' => 'Novo', 'pipeline_id' => 'pipe-1'],
            ['id' => 2, 'name' => 'Qualificado', 'pipeline_id' => 'pipe-1'],
        ]]),
        '*/pipelines*' => Http::response(['success' => true, 'data' => [
            ['id' => 'pipe-1', 'name' => 'Vendas'],
        ]]),
    ]);

    ['conversation' => $conversation] = readContext();

    $data = json_decode((new GetDealStageHistoryTool($conversation))->handle(new Request), true);

    expect($data['mudancas_de_estagio'])->toHaveCount(1)
        ->and($data['mudancas_de_estagio'][0]['de'])->toBe('Novo')
        ->and($data['mudancas_de_estagio'][0]['para'])->toBe('Qualificado')
        ->and($data['mudancas_de_estagio'][0]['quando'])->toBe('2026-01-01 10:00:00');
});

test('GetDealComments returns the deal comments', function () {
    Http::fake([
        '*/deals/42/flow*' => Http::response(['success' => true, 'data' => [
            ['object' => 'note', 'data' => ['content' => 'Cliente pediu desconto', 'add_time' => '2026-01-02 09:00:00']],
            ['object' => 'dealChange', 'data' => ['field_key' => 'stage_id', 'old_value' => 1, 'new_value' => 2]],
        ]]),
    ]);

    ['conversation' => $conversation] = readContext();

    $data = json_decode((new GetDealCommentsTool($conversation))->handle(new Request), true);

    expect($data['comentarios'])->toHaveCount(1)
        ->and($data['comentarios'][0]['conteudo'])->toBe('Cliente pediu desconto');
});

test('GetDealNotes returns merged deal and person notes labelled by origin', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/notes') && str_contains($url, 'deal_id=42')) {
            return Http::response(['success' => true, 'data' => [
                ['content' => 'nota do negócio', 'add_time' => '2026-01-01 08:00:00'],
            ]]);
        }

        if (str_contains($url, '/notes') && str_contains($url, 'person_id=p-1')) {
            return Http::response(['success' => true, 'data' => [
                ['content' => 'nota da pessoa', 'add_time' => '2026-01-01 09:00:00'],
            ]]);
        }

        return Http::response(['success' => true, 'data' => []]);
    });

    ['conversation' => $conversation] = readContext();

    $data = json_decode((new GetDealNotesTool($conversation))->handle(new Request), true);

    $origins = collect($data['notas'])->pluck('origem')->all();

    expect($data['notas'])->toHaveCount(2)
        ->and($origins)->toContain('negócio')
        ->and($origins)->toContain('pessoa');
});

test('GetDealNotes still returns the person notes when there is no deal', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'person_id=p-1')) {
            return Http::response(['success' => true, 'data' => [
                ['content' => 'nota da pessoa', 'add_time' => '2026-01-01 09:00:00'],
            ]]);
        }

        return Http::response(['success' => true, 'data' => []]);
    });

    ['conversation' => $conversation] = readContext(withDeal: false);

    $data = json_decode((new GetDealNotesTool($conversation))->handle(new Request), true);

    expect($data['notas'])->toHaveCount(1)
        ->and($data['notas'][0]['origem'])->toBe('pessoa')
        ->and($data['notas'][0]['conteudo'])->toBe('nota da pessoa');
});

test('ListPipelines returns pipelines and stages with id and name, even without a deal', function () {
    Http::fake([
        '*/stages*' => Http::response(['success' => true, 'data' => [
            ['id' => 7, 'name' => 'Proposta', 'pipeline_id' => 'pipe-1'],
        ]]),
        '*/pipelines*' => Http::response(['success' => true, 'data' => [
            ['id' => 'pipe-1', 'name' => 'Vendas'],
        ]]),
    ]);

    ['conversation' => $conversation] = readContext(withDeal: false);

    $data = json_decode((new ListPipelinesTool($conversation))->handle(new Request), true);

    expect($data['pipelines'])->toHaveCount(1)
        ->and($data['pipelines'][0]['id'])->toBe('pipe-1')
        ->and($data['pipelines'][0]['estagios'][0]['id'])->toBe('7')
        ->and($data['pipelines'][0]['estagios'][0]['nome'])->toBe('Proposta');
});

test('a deal-scoped read returns the "sem deal" marker when the client matches no deal', function () {
    ['conversation' => $conversation] = readContext('outro@cliente.com', withDeal: false);

    expect((new GetDealDataTool($conversation))->handle(new Request))
        ->toContain('Nenhum negócio');
});

test('a Pipedrive failure returns a failure marker without leaking tool, id or error', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => false], 500),
    ]);

    ['conversation' => $conversation] = readContext();

    $result = (new GetDealDataTool($conversation))->handle(new Request);

    expect($result)->toContain('Não foi possível confirmar')
        ->and($result)->not->toContain('42')
        ->and($result)->not->toContain('GetDealData')
        ->and($result)->not->toContain('500')
        ->and($result)->not->toContain('tkn')
        ->and($result)->not->toContain('Exception');
});
