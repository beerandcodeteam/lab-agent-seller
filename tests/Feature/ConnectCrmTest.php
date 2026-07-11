<?php

use App\Jobs\ScanCrmConnection;
use App\Livewire\Crm\Connect;
use App\Models\CrmConnection;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LookupSeeder::class);
});

test('connecting_valid_token_creates_encrypted_connection_and_queues_scan', function () {
    Queue::fake();
    Http::fake([
        'api.pipedrive.com/*' => Http::response(['data' => ['id' => 1]], 200),
    ]);

    $company = User::factory()->create();
    $token = 'pipedrive-valid-token-abc123';

    Livewire::actingAs($company)
        ->test(Connect::class)
        ->set('api_token', $token)
        ->call('connect')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $connection = CrmConnection::where('user_id', $company->id)->first();

    expect($connection)->not->toBeNull();
    expect($connection->api_token)->toBe($token);
    expect($connection->last_validated_at)->not->toBeNull();
    expect($connection->crmProvider->slug)->toBe('pipedrive');

    // Token gravado criptografado (raw != texto puro).
    $raw = DB::table('crm_connections')->where('id', $connection->id)->value('api_token');
    expect($raw)->not->toBe($token);

    Queue::assertPushed(ScanCrmConnection::class, function ($job) use ($connection) {
        return $job->crmConnection->is($connection);
    });
});

test('invalid_token_does_not_persist_connection', function () {
    Queue::fake();
    Http::fake([
        'api.pipedrive.com/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $company = User::factory()->create();

    Livewire::actingAs($company)
        ->test(Connect::class)
        ->set('api_token', 'wrong-token')
        ->call('connect')
        ->assertSet('errorType', 'danger')
        ->assertSet('api_token', '') // campo limpo, nunca reecoado
        ->assertNoRedirect();

    expect(CrmConnection::where('user_id', $company->id)->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

test('token_never_returned_to_client_after_save', function () {
    Queue::fake();
    Http::fake([
        'api.pipedrive.com/*' => Http::response(['data' => ['id' => 1]], 200),
    ]);

    $company = User::factory()->create();
    $token = 'super-secret-token-xyz789';

    Livewire::actingAs($company)
        ->test(Connect::class)
        ->set('api_token', $token)
        ->call('connect')
        ->assertSet('api_token', '')
        ->assertDontSee($token);
});

test('network_failure_preserves_token_and_shows_amber_banner', function () {
    Queue::fake();
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    $company = User::factory()->create();
    $token = 'unverified-token';

    Livewire::actingAs($company)
        ->test(Connect::class)
        ->set('api_token', $token)
        ->call('connect')
        ->assertSet('errorType', 'warn')
        ->assertSet('api_token', $token) // preservado para retry
        ->assertNoRedirect();

    expect(CrmConnection::where('user_id', $company->id)->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

test('empty_token_fails_inline_validation_without_calling_api', function () {
    Http::fake();

    $company = User::factory()->create();

    Livewire::actingAs($company)
        ->test(Connect::class)
        ->set('api_token', '')
        ->call('connect')
        ->assertHasErrors(['api_token' => 'required'])
        ->assertSet('errorType', null) // sem banner
        ->assertNoRedirect();

    Http::assertNothingSent();
    expect(CrmConnection::where('user_id', $company->id)->exists())->toBeFalse();
});
