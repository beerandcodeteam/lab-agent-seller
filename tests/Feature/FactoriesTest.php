<?php

use App\Models\Client;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\CrmPerson;
use App\Models\CrmScan;
use App\Models\MagicLink;
use App\Models\Message;
use App\Models\User;

test('factories_persist_valid_models', function () {
    expect(User::factory()->create()->exists)->toBeTrue();
    expect(CrmConnection::factory()->create()->exists)->toBeTrue();
    expect(CrmPerson::factory()->create()->exists)->toBeTrue();
    expect(Client::factory()->create()->exists)->toBeTrue();
    expect(MagicLink::factory()->create()->exists)->toBeTrue();
    expect(Conversation::factory()->create()->exists)->toBeTrue();
    expect(Message::factory()->create()->exists)->toBeTrue();
});

test('crm_scan_factory_states_persist', function () {
    foreach (['pending', 'running', 'success', 'failed'] as $state) {
        $scan = CrmScan::factory()->{$state}()->create();
        expect($scan->exists)->toBeTrue();
        expect($scan->scanStatus->slug)->toBe($state);
    }
});

test('magic_link_factory_states_persist', function () {
    expect(MagicLink::factory()->expired()->create()->isExpired())->toBeTrue();
    expect(MagicLink::factory()->used()->create()->isUsed())->toBeTrue();
});
