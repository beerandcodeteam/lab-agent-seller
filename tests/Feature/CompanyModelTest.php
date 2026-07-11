<?php

use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\User;

test('company_has_one_connection_and_many_conversations', function () {
    $company = User::factory()->create();
    $connection = CrmConnection::factory()->for($company)->create();
    $conversations = Conversation::factory()->count(2)->for($company)->create();

    $company->refresh();

    expect($company->crmConnection)->not->toBeNull();
    expect($company->crmConnection->id)->toBe($connection->id);
    expect($company->conversations->pluck('id')->sort()->values()->all())
        ->toBe($conversations->pluck('id')->sort()->values()->all());
});
