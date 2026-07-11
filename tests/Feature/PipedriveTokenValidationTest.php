<?php

use App\Services\Crm\Contracts\CrmDriver;
use App\Services\Crm\CrmDriverManager;
use App\Services\Crm\CrmTokenStatus;
use App\Services\Crm\Exceptions\EmptyTokenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function pipedriveDriver(): CrmDriver
{
    return app(CrmDriverManager::class)->driver('pipedrive');
}

test('pipedrive_token_validation_states', function () {
    // 401 → token inválido.
    Http::fake([
        'api.pipedrive.com/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    expect(pipedriveDriver()->validateToken('bad-token'))
        ->toBe(CrmTokenStatus::Invalid);

    // Falha de rede (timeout) → retryable; token não é descartado.
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    expect(pipedriveDriver()->validateToken('unverified-token'))
        ->toBe(CrmTokenStatus::Retryable);

    // String vazia → falha de validação ANTES de chamar a API.
    Http::fake();

    expect(fn () => pipedriveDriver()->validateToken(''))
        ->toThrow(EmptyTokenException::class);

    Http::assertNothingSent();
});

test('pipedrive_valid_token_returns_valid', function () {
    Http::fake([
        'api.pipedrive.com/*' => Http::response(['data' => ['id' => 1, 'name' => 'Ana']], 200),
    ]);

    expect(pipedriveDriver()->validateToken('good-token'))
        ->toBe(CrmTokenStatus::Valid);
});

test('pipedrive_server_error_is_retryable', function () {
    Http::fake([
        'api.pipedrive.com/*' => Http::response('boom', 503),
    ]);

    expect(pipedriveDriver()->validateToken('some-token'))
        ->toBe(CrmTokenStatus::Retryable);
});
