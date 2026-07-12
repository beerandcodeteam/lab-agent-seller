<?php

use App\Ai\Agents\SellerAgent;
use App\Models\Conversation;
use App\Models\User;
use App\Models\VectorStore;
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

    // The 9 Pipedrive tools + WebSearch + the single FileSearch.
    expect(collect($agent->tools()))->toHaveCount(11);
});

it('does not add a FileSearch when the company has no stores', function () {
    $company = User::factory()->create();
    $conversation = Conversation::factory()->for($company)->create();
    $agent = new SellerAgent($conversation);

    expect(fileSearchTools($agent))->toHaveCount(0);

    // The 9 Pipedrive tools + WebSearch remain untouched.
    expect(collect($agent->tools()))->toHaveCount(10);
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
