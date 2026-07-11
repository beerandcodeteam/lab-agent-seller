<?php

namespace App\Services\Crm\Drivers;

use App\Services\Crm\Contracts\CrmDriver;
use App\Services\Crm\CrmTokenStatus;
use App\Services\Crm\Exceptions\EmptyTokenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Pipedrive REST API driver. Token validation hits the account verification
 * endpoint (`/users/me`) with the personal API token as a query parameter.
 */
class PipedriveDriver implements CrmDriver
{
    public function slug(): string
    {
        return 'pipedrive';
    }

    /**
     * Rules:
     *  - blank token  → EmptyTokenException (never calls the API)
     *  - HTTP 401     → Invalid (token rejected, must be discarded)
     *  - network fail → Retryable (token neither verified nor discarded)
     *  - 5xx          → Retryable
     *  - 2xx          → Valid
     */
    public function validateToken(string $token): CrmTokenStatus
    {
        if (trim($token) === '') {
            throw new EmptyTokenException;
        }

        $baseUrl = rtrim((string) config('services.pipedrive.base_url'), '/');

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get("{$baseUrl}/users/me", ['api_token' => $token]);
        } catch (ConnectionException) {
            return CrmTokenStatus::Retryable;
        }

        if ($response->status() === 401) {
            return CrmTokenStatus::Invalid;
        }

        if ($response->serverError()) {
            return CrmTokenStatus::Retryable;
        }

        return $response->successful()
            ? CrmTokenStatus::Valid
            : CrmTokenStatus::Invalid;
    }
}
