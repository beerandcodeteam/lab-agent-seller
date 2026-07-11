<?php

use App\Livewire\Crm\ConnectionStatus;
use App\Models\CrmConnection;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LookupSeeder::class);
});

test('connection_status_reflects_state', function () {
    $company = User::factory()->create();

    // Sem conexão → CTA de conectar.
    Livewire::actingAs($company)
        ->test(ConnectionStatus::class)
        ->assertSee('Conectar Pipedrive')
        ->assertDontSee('CRM conectado');

    // Com conexão → provedor e data de conexão.
    CrmConnection::factory()->for($company)->create();

    Livewire::actingAs($company)
        ->test(ConnectionStatus::class)
        ->assertSee('CRM conectado')
        ->assertSee('Pipedrive')
        ->assertSee(now()->format('d/m/Y'))
        ->assertDontSee('Conectar Pipedrive');
});

test('company_can_disconnect_connection', function () {
    $company = User::factory()->create();
    $connection = CrmConnection::factory()->for($company)->create();

    Livewire::actingAs($company)
        ->test(ConnectionStatus::class)
        ->assertSee('CRM conectado')
        ->call('disconnect')
        ->assertSee('Conectar Pipedrive');

    expect(CrmConnection::whereKey($connection->id)->exists())->toBeFalse();
});

test('status_block_shows_disconnect_and_update_token_actions', function () {
    $company = User::factory()->create();
    CrmConnection::factory()->for($company)->create();

    Livewire::actingAs($company)
        ->test(ConnectionStatus::class)
        ->assertSee('Desconectar')
        ->assertSee('Atualizar token');
});
