<?php

use App\Jobs\ScanCrmConnection;
use App\Livewire\Crm\ScanCard;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\CrmPerson;
use App\Models\CrmScan;
use App\Models\CustomField;
use App\Models\CustomFieldEntity;
use App\Models\Deal;
use App\Models\Message;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LookupSeeder::class);
});

test('rescan_enqueues_scan_job', function () {
    Queue::fake();

    $company = User::factory()->create();
    $connection = CrmConnection::factory()->for($company)->create();
    CrmScan::factory()->for($connection)->success()->create();

    Livewire::actingAs($company)
        ->test(ScanCard::class)
        ->call('rescan');

    Queue::assertPushed(ScanCrmConnection::class, fn ($job) => $job->crmConnection->is($connection));
});

test('rescan_disabled_while_scan_running', function () {
    Queue::fake();

    $company = User::factory()->create();
    $connection = CrmConnection::factory()->for($company)->create();
    CrmScan::factory()->for($connection)->running()->create();

    Livewire::actingAs($company)
        ->test(ScanCard::class)
        ->assertSee('syncing')
        ->call('rescan');

    // Guard: no job queued while a scan is already in progress.
    Queue::assertNothingPushed();
});

test('dashboard_shows_scanned_counts', function () {
    $company = User::factory()->create();
    $connection = CrmConnection::factory()->for($company)->create();
    CrmScan::factory()->for($connection)->success()->create();

    $pipeline = Pipeline::create(['crm_connection_id' => $connection->id, 'external_id' => '1', 'name' => 'Vendas']);
    Pipeline::create(['crm_connection_id' => $connection->id, 'external_id' => '2', 'name' => 'Suporte']);
    Pipeline::create(['crm_connection_id' => $connection->id, 'external_id' => '3', 'name' => 'Onboarding']);

    foreach (range(1, 9) as $i) {
        PipelineStage::create(['pipeline_id' => $pipeline->id, 'external_id' => (string) (100 + $i), 'name' => "Stage {$i}", 'order_index' => $i]);
    }

    $personEntity = CustomFieldEntity::slug('person')->id;
    $dealEntity = CustomFieldEntity::slug('deal')->id;
    foreach (range(1, 4) as $i) {
        CustomField::create(['crm_connection_id' => $connection->id, 'custom_field_entity_id' => $personEntity, 'external_id' => (string) (300 + $i), 'name' => "PF{$i}"]);
    }
    foreach (range(1, 6) as $i) {
        CustomField::create(['crm_connection_id' => $connection->id, 'custom_field_entity_id' => $dealEntity, 'external_id' => (string) (400 + $i), 'name' => "DF{$i}"]);
    }

    foreach (range(1, 42) as $i) {
        CrmPerson::create(['crm_connection_id' => $connection->id, 'external_id' => (string) (500 + $i), 'name' => "Person {$i}"]);
    }
    foreach (range(1, 17) as $i) {
        Deal::create(['crm_connection_id' => $connection->id, 'external_id' => (string) (600 + $i), 'title' => "Deal {$i}"]);
    }

    Livewire::actingAs($company)
        ->test(ScanCard::class)
        ->assertSee('pipelines')
        ->assertSee('stages')
        ->assertSee('c. fields person')
        ->assertSee('c. fields deal')
        ->assertSee('persons')
        ->assertSee('deals')
        ->assertSee('42')  // persons
        ->assertSee('17'); // deals
});

test('conversation_volume_counts_are_scoped_to_company', function () {
    $company = User::factory()->create();
    $other = User::factory()->create();

    // The company needs a connection for the panel (and volume cards) to render.
    CrmConnection::factory()->for($company)->create();

    $conversationA = Conversation::factory()->for($company)->create();
    Conversation::factory()->for($company)->create();
    Message::factory()->count(13)->for($conversationA)->create();

    // Another company's traffic must not leak into the counts.
    $conversationB = Conversation::factory()->for($other)->create();
    Message::factory()->count(88)->for($conversationB)->create();

    Livewire::actingAs($company)
        ->test(ScanCard::class)
        ->assertSee('Conversas')
        ->assertSee('Mensagens')
        ->assertSee('13')     // scoped message count
        ->assertDontSee('88'); // other company's messages excluded
});
