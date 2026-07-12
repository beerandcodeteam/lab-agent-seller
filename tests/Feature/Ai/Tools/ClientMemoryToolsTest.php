<?php

use App\Ai\Tools\RecallClientMemoriesTool;
use App\Ai\Tools\RememberClientFactTool;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Memory\MemoryCategory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

/**
 * A conversation whose client is 7 and company (tenant) is 3, so the mem0 scope
 * is a stable, assertable (user_id, agent_id) pair.
 */
function memoryConversation(): Conversation
{
    $company = User::factory()->create(['id' => 3]);
    $client = Client::factory()->create(['id' => 7]);

    return Conversation::factory()->create([
        'user_id' => $company->id,
        'client_id' => $client->id,
    ]);
}

beforeEach(function () {
    config()->set('services.mem0.api_key', 'm0-test');
    config()->set('services.mem0.base_url', 'https://api.mem0.ai');
});

test('RememberClientFact exposes a required content and optional category input', function () {
    $schema = (new RememberClientFactTool(memoryConversation()))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['content', 'category']);
});

test('RememberClientFact stores the fact scoped to the client and company', function () {
    Http::fake([
        '*/v3/memories/add/' => Http::response(['status' => 'PENDING', 'event_id' => 'evt-1']),
    ]);

    $result = (new RememberClientFactTool(memoryConversation()))->handle(new Request([
        'content' => 'Cliente achou o preço alto e comparou com o concorrente X.',
        'category' => MemoryCategory::Objection->value,
    ]));

    expect($result)->toContain('registrado');

    Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/v3/memories/add/')
        && $request->hasHeader('Authorization', 'Token m0-test')
        && $request['user_id'] === 'client_7'
        && $request['agent_id'] === 'company_3'
        && $request['messages'][0]['content'] === 'Cliente achou o preço alto e comparou com o concorrente X.'
        && $request['metadata']['category'] === 'objecao');
});

test('RememberClientFact rejects an empty content without calling mem0', function () {
    Http::fake();

    $result = (new RememberClientFactTool(memoryConversation()))->handle(new Request(['content' => '   ']));

    expect($result)->toBe('Não foi possível acessar a memória do cliente agora.');
    Http::assertNothingSent();
});

test('RememberClientFact ignores an unknown category', function () {
    Http::fake(['*/v3/memories/add/' => Http::response(['status' => 'PENDING'])]);

    (new RememberClientFactTool(memoryConversation()))->handle(new Request([
        'content' => 'Cliente decide junto com o sócio.',
        'category' => 'inexistente',
    ]));

    Http::assertSent(fn (ClientRequest $request) => ! array_key_exists('metadata', $request->data()));
});

test('RememberClientFact degrades to a generic marker on a mem0 failure', function () {
    Http::fake(['*/v3/memories/add/' => Http::response(['error' => 'boom'], 500)]);

    $result = (new RememberClientFactTool(memoryConversation()))->handle(new Request([
        'content' => 'Cliente quer fechar até o fim do mês.',
    ]));

    expect($result)->toBe('Não foi possível acessar a memória do cliente agora.');
});

test('RecallClientMemories returns the matched memories scoped to client and company', function () {
    Http::fake([
        '*/v3/memories/search/' => Http::response(['results' => [
            ['id' => 'm1', 'memory' => 'Cliente achou o preço alto.', 'score' => 0.9],
            ['id' => 'm2', 'memory' => 'Quem decide é o sócio.', 'score' => 0.8],
        ]]),
    ]);

    $result = (new RecallClientMemoriesTool(memoryConversation()))->handle(new Request([
        'query' => 'objeções sobre preço',
    ]));

    expect($result)
        ->toContain('Cliente achou o preço alto.')
        ->toContain('Quem decide é o sócio.');

    Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/v3/memories/search/')
        && $request['query'] === 'objeções sobre preço'
        && $request['filters']['user_id'] === 'client_7'
        && $request['filters']['agent_id'] === 'company_3');
});

test('RecallClientMemories reports when the client has no memories yet', function () {
    Http::fake(['*/v3/memories/search/' => Http::response(['results' => []])]);

    $result = (new RecallClientMemoriesTool(memoryConversation()))->handle(new Request([
        'query' => 'qualquer coisa',
    ]));

    expect($result)->toBe('Nenhuma memória registrada para este cliente ainda.');
});

test('RecallClientMemories degrades to a generic marker on a mem0 failure', function () {
    Http::fake(['*/v3/memories/search/' => Http::response('nope', 503)]);

    $result = (new RecallClientMemoriesTool(memoryConversation()))->handle(new Request([
        'query' => 'objeções',
    ]));

    expect($result)->toBe('Não foi possível acessar a memória do cliente agora.');
});

test('memory tools report unavailable and skip mem0 when no api key is configured', function () {
    config()->set('services.mem0.api_key', null);
    Http::fake();

    $conversation = memoryConversation();

    expect((new RememberClientFactTool($conversation))->handle(new Request(['content' => 'x'])))
        ->toBe('A memória do cliente não está disponível no momento.');

    expect((new RecallClientMemoriesTool($conversation))->handle(new Request(['query' => 'x'])))
        ->toBe('A memória do cliente não está disponível no momento.');

    Http::assertNothingSent();
});
