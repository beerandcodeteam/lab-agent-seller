<?php

use App\Models\Client;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\CrmPerson;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\Crm\ConversationDealResolver;
use App\Services\Crm\Drivers\PipedriveDriver;

/**
 * Build a conversation whose company owns a CRM connection, for the given
 * client email.
 *
 * @return array{conversation: Conversation, connection: CrmConnection}
 */
function conversationFor(string $clientEmail, string $token = 'secret-token'): array
{
    $company = User::factory()->create();
    $connection = CrmConnection::factory()->for($company)->create(['api_token' => $token]);
    $client = Client::factory()->create(['email' => $clientEmail]);
    $conversation = Conversation::factory()->create([
        'user_id' => $company->id,
        'client_id' => $client->id,
    ]);

    return ['conversation' => $conversation, 'connection' => $connection];
}

function resolver(): ConversationDealResolver
{
    return app(ConversationDealResolver::class);
}

test('matches the person case-insensitively and resolves the token and driver', function () {
    ['conversation' => $conversation, 'connection' => $connection] = conversationFor('Foo@Bar.com', 'my-token');

    $person = CrmPerson::factory()->for($connection)->create([
        'external_id' => 'p-1',
        'email' => 'foo@bar.com',
    ]);
    Deal::factory()->for($connection)->open()->create([
        'crm_person_id' => $person->id,
        'external_id' => 'd-1',
    ]);

    $resolution = resolver()->resolve($conversation);

    expect($resolution->token)->toBe('my-token')
        ->and($resolution->driver)->toBeInstanceOf(PipedriveDriver::class)
        ->and($resolution->personExternalIds)->toBe(['p-1'])
        ->and($resolution->deal)->not->toBeNull()
        ->and($resolution->deal->externalId)->toBe('d-1');
});

test('scopes matching to the company connection, never another tenant', function () {
    ['conversation' => $conversationA] = conversationFor('shared@client.com');

    // Company B has a person + deal with the SAME email, under its own connection.
    $companyB = User::factory()->create();
    $connectionB = CrmConnection::factory()->for($companyB)->create();
    $personB = CrmPerson::factory()->for($connectionB)->create([
        'external_id' => 'b-1',
        'email' => 'shared@client.com',
    ]);
    Deal::factory()->for($connectionB)->open()->create([
        'crm_person_id' => $personB->id,
        'external_id' => 'deal-b',
    ]);

    $resolution = resolver()->resolve($conversationA);

    expect($resolution->personExternalIds)->toBe([])
        ->and($resolution->deal)->toBeNull();
});

test('unions the deals of multiple matched persons', function () {
    ['conversation' => $conversation, 'connection' => $connection] = conversationFor('dup@client.com');

    $personOne = CrmPerson::factory()->for($connection)->create(['external_id' => 'p-1', 'email' => 'dup@client.com']);
    $personTwo = CrmPerson::factory()->for($connection)->create(['external_id' => 'p-2', 'email' => 'dup@client.com']);

    Deal::factory()->for($connection)->open()->create(['crm_person_id' => $personOne->id, 'external_id' => 'd-old']);
    $recent = Deal::factory()->for($connection)->open()->create(['crm_person_id' => $personTwo->id, 'external_id' => 'd-recent']);

    Deal::whereKey($recent->id)->update(['updated_at' => now()->addDay()]);

    $resolution = resolver()->resolve($conversation);

    expect($resolution->personExternalIds)->toContain('p-1')->toContain('p-2')
        ->and($resolution->deal->externalId)->toBe('d-recent');
});

test('selects the open deal with the most recent updated_at', function () {
    ['conversation' => $conversation, 'connection' => $connection] = conversationFor('multi@client.com');

    $person = CrmPerson::factory()->for($connection)->create(['external_id' => 'p-1', 'email' => 'multi@client.com']);

    $older = Deal::factory()->for($connection)->open()->create(['crm_person_id' => $person->id, 'external_id' => 'd-older']);
    $newer = Deal::factory()->for($connection)->open()->create(['crm_person_id' => $person->id, 'external_id' => 'd-newer']);

    Deal::whereKey($older->id)->update(['updated_at' => now()->subDays(5)]);
    Deal::whereKey($newer->id)->update(['updated_at' => now()->subDay()]);

    $resolution = resolver()->resolve($conversation);

    expect($resolution->deal->externalId)->toBe('d-newer');
});

test('exposes the deal pipeline external id and status', function () {
    ['conversation' => $conversation, 'connection' => $connection] = conversationFor('pipe@client.com');

    $person = CrmPerson::factory()->for($connection)->create(['external_id' => 'p-1', 'email' => 'pipe@client.com']);
    $pipeline = Pipeline::create([
        'crm_connection_id' => $connection->id,
        'external_id' => 'pipe-9',
        'name' => 'Vendas',
    ]);
    Deal::factory()->for($connection)->open()->create([
        'crm_person_id' => $person->id,
        'pipeline_id' => $pipeline->id,
        'external_id' => 'd-1',
    ]);

    $resolution = resolver()->resolve($conversation);

    expect($resolution->deal->pipelineExternalId)->toBe('pipe-9')
        ->and($resolution->deal->status)->toBe('open');
});

test('returns no deal but keeps person ids when only closed deals exist', function () {
    ['conversation' => $conversation, 'connection' => $connection] = conversationFor('closed@client.com');

    $person = CrmPerson::factory()->for($connection)->create(['external_id' => 'p-1', 'email' => 'closed@client.com']);
    Deal::factory()->for($connection)->won()->create(['crm_person_id' => $person->id, 'external_id' => 'd-won']);
    Deal::factory()->for($connection)->lost()->create(['crm_person_id' => $person->id, 'external_id' => 'd-lost']);

    $resolution = resolver()->resolve($conversation);

    expect($resolution->deal)->toBeNull()
        ->and($resolution->personExternalIds)->toBe(['p-1']);
});

test('returns no deal and no person ids when the client matches nothing', function () {
    ['conversation' => $conversation] = conversationFor('nomatch@client.com');

    $resolution = resolver()->resolve($conversation);

    expect($resolution->deal)->toBeNull()
        ->and($resolution->personExternalIds)->toBe([]);
});

test('resolves empty without error when the company has no crm connection', function () {
    $company = User::factory()->create();
    $client = Client::factory()->create(['email' => 'someone@client.com']);
    $conversation = Conversation::factory()->create([
        'user_id' => $company->id,
        'client_id' => $client->id,
    ]);

    $resolution = resolver()->resolve($conversation);

    expect($resolution->token)->toBeNull()
        ->and($resolution->driver)->toBeNull()
        ->and($resolution->deal)->toBeNull()
        ->and($resolution->personExternalIds)->toBe([]);
});
