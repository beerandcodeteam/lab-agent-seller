<?php

use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('lookup_tables_have_unique_slug', function () {
    $tables = [
        'crm_providers',
        'scan_statuses',
        'custom_field_entities',
        'deal_statuses',
        'message_roles',
    ];

    foreach ($tables as $table) {
        $row = ['name' => 'First', 'slug' => 'dup-slug', 'created_at' => now(), 'updated_at' => now()];

        if ($table === 'crm_providers') {
            $row['is_active'] = true;
        }

        DB::table($table)->insert($row);

        // Savepoint so the aborted duplicate insert doesn't poison the outer test transaction (pgsql).
        expect(fn () => DB::transaction(fn () => DB::table($table)->insert($row)))
            ->toThrow(QueryException::class);
    }
});

test('one_active_connection_per_company', function () {
    $user = User::factory()->create();
    $providerId = DB::table('crm_providers')->insertGetId([
        'name' => 'Pipedrive', 'slug' => 'pipedrive', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $row = [
        'user_id' => $user->id,
        'crm_provider_id' => $providerId,
        'api_token' => 'token',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('crm_connections')->insert($row);

    expect(fn () => DB::table('crm_connections')->insert($row))
        ->toThrow(QueryException::class);
});

test('scanned_rows_unique_by_external_id', function () {
    $user = User::factory()->create();
    $providerId = DB::table('crm_providers')->insertGetId([
        'name' => 'Pipedrive', 'slug' => 'pipedrive', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $connectionId = DB::table('crm_connections')->insertGetId([
        'user_id' => $user->id, 'crm_provider_id' => $providerId, 'api_token' => 'token',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('pipelines')->upsert(
        [['crm_connection_id' => $connectionId, 'external_id' => 'ext-1', 'name' => 'Sales', 'created_at' => now(), 'updated_at' => now()]],
        ['crm_connection_id', 'external_id'],
        ['name', 'updated_at'],
    );
    DB::table('pipelines')->upsert(
        [['crm_connection_id' => $connectionId, 'external_id' => 'ext-1', 'name' => 'Sales Updated', 'created_at' => now(), 'updated_at' => now()]],
        ['crm_connection_id', 'external_id'],
        ['name', 'updated_at'],
    );

    expect(DB::table('pipelines')->where('crm_connection_id', $connectionId)->count())->toBe(1);
    expect(DB::table('pipelines')->where('external_id', 'ext-1')->value('name'))->toBe('Sales Updated');
});

test('one_conversation_per_client_company_pair', function () {
    $user = User::factory()->create();
    $clientId = DB::table('clients')->insertGetId([
        'email' => 'client@example.com', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $row = ['client_id' => $clientId, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()];

    DB::table('conversations')->insert($row);

    expect(fn () => DB::table('conversations')->insert($row))
        ->toThrow(QueryException::class);
});

test('client_email_is_unique', function () {
    $row = ['email' => 'dup@example.com', 'created_at' => now(), 'updated_at' => now()];

    DB::table('clients')->insert($row);

    expect(fn () => DB::table('clients')->insert($row))
        ->toThrow(QueryException::class);
});

test('lookup_seeder_is_idempotent', function () {
    $this->seed(LookupSeeder::class);
    $this->seed(LookupSeeder::class);

    expect(DB::table('crm_providers')->count())->toBe(1);
    expect(DB::table('scan_statuses')->count())->toBe(4);
    expect(DB::table('custom_field_entities')->count())->toBe(3);
    expect(DB::table('deal_statuses')->count())->toBe(3);
    expect(DB::table('message_roles')->count())->toBe(2);
    expect(DB::table('file_indexing_statuses')->count())->toBe(4);

    expect(DB::table('scan_statuses')->pluck('slug')->sort()->values()->all())
        ->toBe(['failed', 'pending', 'running', 'success']);
    expect(DB::table('deal_statuses')->pluck('slug')->sort()->values()->all())
        ->toBe(['lost', 'open', 'won']);
    expect(DB::table('message_roles')->pluck('slug')->sort()->values()->all())
        ->toBe(['assistant', 'user']);
    expect(DB::table('custom_field_entities')->pluck('slug')->sort()->values()->all())
        ->toBe(['deal', 'organization', 'person']);
    expect(DB::table('file_indexing_statuses')->pluck('slug')->sort()->values()->all())
        ->toBe(['completed', 'failed', 'in_progress', 'pending']);
    expect(DB::table('crm_providers')->pluck('slug')->all())->toBe(['pipedrive']);
});
