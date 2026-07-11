<?php

use App\Models\CrmConnection;
use App\Models\CrmScan;
use Illuminate\Support\Facades\DB;

test('api_token_is_encrypted_at_rest', function () {
    $token = 'pipedrive-secret-token-123';
    $connection = CrmConnection::factory()->create(['api_token' => $token]);

    $raw = DB::table('crm_connections')->where('id', $connection->id)->value('api_token');

    expect($raw)->not->toBe($token);
    expect($connection->fresh()->api_token)->toBe($token);
});

test('connection_scan_relationships', function () {
    $connection = CrmConnection::factory()->create();
    $scan = CrmScan::factory()->success()->for($connection)->create();

    expect($connection->crmScans->pluck('id'))->toContain($scan->id);
    expect($scan->crmConnection->id)->toBe($connection->id);
    expect($scan->scanStatus->slug)->toBe('success');
});
