<?php

use App\Ai\Agents\SellerAgent;
use App\Models\Conversation;
use App\Models\User;
use App\Models\VectorStore;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\Tools\FileSearch;

/**
 * @return array<int, FileSearch>
 */
function fileSearchTools(SellerAgent $agent): array
{
    return collect($agent->tools())
        ->filter(fn (object $tool): bool => $tool instanceof FileSearch)
        ->values()
        ->all();
}

it('adds exactly one FileSearch scoped to the company store ids and keeps the Pipedrive tools', function () {
    $company = User::factory()->create();
    $stores = VectorStore::factory()->count(3)->for($company)->create();

    $otherCompany = User::factory()->create();
    VectorStore::factory()->count(2)->for($otherCompany)->create();

    $conversation = Conversation::factory()->for($company)->create();
    $agent = new SellerAgent($conversation);

    $fileSearches = fileSearchTools($agent);

    expect($fileSearches)->toHaveCount(1);

    expect($fileSearches[0]->ids())
        ->toEqualCanonicalizing($stores->pluck('openai_vector_store_id')->all());

    // The 9 Pipedrive tools + 2 memory tools + WebSearch + the single FileSearch.
    expect(collect($agent->tools()))->toHaveCount(13);
});

it('does not add a FileSearch when the company has no stores', function () {
    $company = User::factory()->create();
    $conversation = Conversation::factory()->for($company)->create();
    $agent = new SellerAgent($conversation);

    expect(fileSearchTools($agent))->toHaveCount(0);

    // The 9 Pipedrive tools + 2 memory tools + WebSearch remain untouched.
    expect(collect($agent->tools()))->toHaveCount(12);
});

it('catalogs the name and description of each store in the instructions', function () {
    $company = User::factory()->create(['name' => 'ACME']);
    VectorStore::factory()->for($company)->create([
        'name' => 'Manual do Produto',
        'description' => 'Especificações técnicas dos produtos',
    ]);
    VectorStore::factory()->for($company)->create([
        'name' => 'Políticas Comerciais',
        'description' => 'Regras de desconto e prazo',
    ]);

    $conversation = Conversation::factory()->for($company)->create();
    $instructions = (new SellerAgent($conversation))->instructions();

    expect($instructions)
        ->toContain('<knowledge_bases>')
        ->toContain('Manual do Produto')
        ->toContain('Especificações técnicas dos produtos')
        ->toContain('Políticas Comerciais')
        ->toContain('Regras de desconto e prazo');
});

it('does not add a knowledge base catalog when the company has no stores', function () {
    $company = User::factory()->create();
    $conversation = Conversation::factory()->for($company)->create();

    expect((new SellerAgent($conversation))->instructions())
        ->not->toContain('<knowledge_bases>');
});

it('requests file-search results in the response when the company has stores', function () {
    $company = User::factory()->create();
    VectorStore::factory()->for($company)->create();
    $conversation = Conversation::factory()->for($company)->create();

    // Surfacing the retrieved passages is what lets the output guardrail verify
    // knowledge-base-sourced claims instead of flagging them as invented.
    expect((new SellerAgent($conversation))->providerOptions(Lab::OpenAI))
        ->toBe(['include' => ['file_search_call.results']]);
});

it('does not request file-search results when the company has no stores', function () {
    $company = User::factory()->create();
    $conversation = Conversation::factory()->for($company)->create();

    // The `include` key is rejected by OpenAI without the matching tool present.
    expect((new SellerAgent($conversation))->providerOptions(Lab::OpenAI))->toBe([]);
});

it('requests no provider options for non-OpenAI providers', function () {
    $company = User::factory()->create();
    VectorStore::factory()->for($company)->create();
    $conversation = Conversation::factory()->for($company)->create();

    expect((new SellerAgent($conversation))->providerOptions(Lab::Anthropic))->toBe([]);
});
