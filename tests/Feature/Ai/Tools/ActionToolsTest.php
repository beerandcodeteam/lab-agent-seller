<?php

use App\Ai\Tools\MarkDealLostTool;
use App\Ai\Tools\MarkDealWonTool;
use App\Ai\Tools\MoveDealStageTool;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\CrmPerson;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

/**
 * Build a conversation whose company owns a Pipedrive connection and whose
 * client matches a person with an open deal 42 in pipeline pipe-1 (unless
 * $withDeal is false, in which case no deal is mirrored).
 *
 * @return array{conversation: Conversation}
 */
function actionContext(string $clientEmail = 'cliente@empresa.com', bool $withDeal = true): array
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

    return ['conversation' => $conversation];
}

/**
 * @param  array<string, mixed>  $data
 */
function fakeDeal(array $data = ['id' => 42, 'status' => 'open', 'pipeline_id' => 'pipe-1']): void
{
    Http::fake([
        '*/deals/42*' => Http::response(['success' => true, 'data' => $data]),
        '*/stages*' => Http::response(['success' => true, 'data' => [
            ['id' => 7, 'name' => 'Proposta', 'pipeline_id' => 'pipe-1'],
            ['id' => 9, 'name' => 'Fechamento', 'pipeline_id' => 'pipe-2'],
        ]]),
        '*/pipelines*' => Http::response(['success' => true, 'data' => [
            ['id' => 'pipe-1', 'name' => 'Vendas'],
            ['id' => 'pipe-2', 'name' => 'Suporte'],
        ]]),
    ]);
}

test('MoveDealStage exposes only a stage_id input', function () {
    $schema = (new MoveDealStageTool(Conversation::factory()->create()))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['stage_id']);
});

test('MoveDealStage moves the deal to a same-pipeline stage via PUT', function () {
    fakeDeal();
    ['conversation' => $conversation] = actionContext();

    $result = (new MoveDealStageTool($conversation))->handle(new Request(['stage_id' => '7']));

    expect($result)->toContain('movido');

    Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PUT'
        && str_contains($request->url(), '/deals/42')
        && $request['stage_id'] === '7');
});

test('MoveDealStage refuses a stage from another pipeline without any PUT', function () {
    fakeDeal();
    ['conversation' => $conversation] = actionContext();

    $result = (new MoveDealStageTool($conversation))->handle(new Request(['stage_id' => '9']));

    expect($result)->toContain('outro funil');

    Http::assertNotSent(fn (ClientRequest $request) => $request->method() === 'PUT');
});

test('MarkDealWon exposes an empty input schema and marks the deal won', function () {
    fakeDeal();
    ['conversation' => $conversation] = actionContext();

    expect((new MarkDealWonTool($conversation))->schema(new JsonSchemaTypeFactory))->toBe([]);

    $result = (new MarkDealWonTool($conversation))->handle(new Request);

    expect($result)->toContain('ganho');

    Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PUT'
        && $request['status'] === 'won');
});

test('MarkDealLost exposes only a lost_reason input and marks the deal lost with the reason', function () {
    fakeDeal();
    ['conversation' => $conversation] = actionContext();

    expect(array_keys((new MarkDealLostTool($conversation))->schema(new JsonSchemaTypeFactory)))->toBe(['lost_reason']);

    $result = (new MarkDealLostTool($conversation))->handle(new Request(['lost_reason' => 'Preço acima do orçamento']));

    expect($result)->toContain('perdido');

    Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PUT'
        && $request['status'] === 'lost'
        && $request['lost_reason'] === 'Preço acima do orçamento');
});

test('MarkDealLost without a reason marks lost without a lost_reason', function () {
    fakeDeal();
    ['conversation' => $conversation] = actionContext();

    (new MarkDealLostTool($conversation))->handle(new Request);

    Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PUT'
        && $request['status'] === 'lost'
        && ! isset($request['lost_reason']));
});

test('every action refuses a closed deal with a marker and never mutates it', function () {
    fakeDeal(['id' => 42, 'status' => 'won', 'pipeline_id' => 'pipe-1']);
    ['conversation' => $conversation] = actionContext();

    $move = (new MoveDealStageTool($conversation))->handle(new Request(['stage_id' => '7']));
    $won = (new MarkDealWonTool($conversation))->handle(new Request);
    $lost = (new MarkDealLostTool($conversation))->handle(new Request);

    expect($move)->toContain('já está fechado')
        ->and($won)->toContain('já está fechado')
        ->and($lost)->toContain('já está fechado');

    Http::assertNotSent(fn (ClientRequest $request) => $request->method() === 'PUT');
});

test('every action returns the "sem deal" marker when the client has no deal', function () {
    Http::fake();
    ['conversation' => $conversation] = actionContext(withDeal: false);

    expect((new MoveDealStageTool($conversation))->handle(new Request(['stage_id' => '7'])))->toContain('Nenhum negócio')
        ->and((new MarkDealWonTool($conversation))->handle(new Request))->toContain('Nenhum negócio')
        ->and((new MarkDealLostTool($conversation))->handle(new Request))->toContain('Nenhum negócio');

    Http::assertNothingSent();
});

test('an action failure returns a failure marker without leaking tool, id or error', function () {
    Http::fake(['*/deals/42*' => Http::response(['success' => false], 500)]);
    ['conversation' => $conversation] = actionContext();

    $result = (new MarkDealWonTool($conversation))->handle(new Request);

    expect($result)->toContain('Não foi possível confirmar')
        ->and($result)->not->toContain('42')
        ->and($result)->not->toContain('MarkDealWon')
        ->and($result)->not->toContain('500')
        ->and($result)->not->toContain('tkn')
        ->and($result)->not->toContain('Exception');
});
