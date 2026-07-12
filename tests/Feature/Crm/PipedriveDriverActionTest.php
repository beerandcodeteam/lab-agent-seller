<?php

use App\Services\Crm\Drivers\PipedriveDriver;
use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function pipedriveActionDriver(): PipedriveDriver
{
    return new PipedriveDriver;
}

test('moveDealStage PUTs the target stage_id', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => true, 'data' => ['id' => 42]]),
    ]);

    pipedriveActionDriver()->moveDealStage('tkn', '42', '7');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/deals/42')
            && $request['stage_id'] === '7';
    });
});

test('markDealWon PUTs status won', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => true, 'data' => ['id' => 42]]),
    ]);

    pipedriveActionDriver()->markDealWon('tkn', '42');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/deals/42')
            && $request['status'] === 'won';
    });
});

test('markDealLost PUTs status lost with the given reason', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => true, 'data' => ['id' => 42]]),
    ]);

    pipedriveActionDriver()->markDealLost('tkn', '42', 'Preço acima do orçamento');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'PUT'
            && $request['status'] === 'lost'
            && $request['lost_reason'] === 'Preço acima do orçamento';
    });
});

test('markDealLost without a reason PUTs status lost only', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => true, 'data' => ['id' => 42]]),
    ]);

    pipedriveActionDriver()->markDealLost('tkn', '42');

    Http::assertSent(function (Request $request) {
        return $request['status'] === 'lost'
            && ! isset($request['lost_reason']);
    });
});

test('a non-2xx response throws CrmApiException on move', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => false], 500),
    ]);

    expect(fn () => pipedriveActionDriver()->moveDealStage('tkn', '42', '7'))
        ->toThrow(CrmApiException::class);
});

test('a connection failure throws CrmApiException on won', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(fn () => pipedriveActionDriver()->markDealWon('tkn', '42'))
        ->toThrow(CrmApiException::class);
});

test('a non-2xx response throws CrmApiException on lost', function () {
    Http::fake([
        '*/deals/42*' => Http::response(['success' => false], 429),
    ]);

    expect(fn () => pipedriveActionDriver()->markDealLost('tkn', '42', 'motivo'))
        ->toThrow(CrmApiException::class);
});
