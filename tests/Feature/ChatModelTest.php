<?php

use App\Models\Client;
use App\Models\Conversation;
use App\Models\MagicLink;
use App\Models\Message;

test('chat_model_graph_wired', function () {
    $client = Client::factory()->create();
    $conversation = Conversation::factory()->for($client)->create();
    $userMessage = Message::factory()->fromUser()->for($conversation)->create();
    $assistantMessage = Message::factory()->fromAssistant()->for($conversation)->create();

    expect($conversation->messages->pluck('id')->all())
        ->toContain($userMessage->id, $assistantMessage->id);
    expect($userMessage->role->slug)->toBe('user');
    expect($assistantMessage->role->slug)->toBe('assistant');
    expect($client->conversations->pluck('id'))->toContain($conversation->id);
    expect($conversation->client->id)->toBe($client->id);
});

test('magic_link_state_helpers', function () {
    $fresh = MagicLink::factory()->create();
    expect($fresh->isExpired())->toBeFalse();
    expect($fresh->isUsed())->toBeFalse();

    $expired = MagicLink::factory()->expired()->create();
    expect($expired->isExpired())->toBeTrue();

    $used = MagicLink::factory()->used()->create();
    expect($used->isUsed())->toBeTrue();
});
