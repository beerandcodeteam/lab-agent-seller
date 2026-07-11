<?php

namespace App\Services\Crm\Drivers;

use App\Services\Crm\Contracts\CrmDriver;
use App\Services\Crm\CrmTokenStatus;
use App\Services\Crm\Exceptions\CrmApiException;
use App\Services\Crm\Exceptions\EmptyTokenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Pipedrive REST API driver. Token validation hits the account verification
 * endpoint (`/users/me`) with the personal API token as a query parameter.
 * Scan data is streamed page by page from the v1 REST endpoints.
 */
class PipedriveDriver implements CrmDriver
{
    /** How many records to request per page. */
    private const PAGE_SIZE = 100;

    /**
     * Custom-field endpoint per entity slug.
     *
     * @var array<string, string>
     */
    private const FIELD_ENDPOINTS = [
        'person' => '/personFields',
        'deal' => '/dealFields',
    ];

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

    public function fetchPipelines(string $token): iterable
    {
        foreach ($this->paginate('/pipelines', $token) as $pipeline) {
            yield [
                'external_id' => (string) $pipeline['id'],
                'name' => (string) ($pipeline['name'] ?? ''),
            ];
        }
    }

    public function fetchStages(string $token): iterable
    {
        foreach ($this->paginate('/stages', $token) as $stage) {
            yield [
                'external_id' => (string) $stage['id'],
                'pipeline_external_id' => (string) ($stage['pipeline_id'] ?? ''),
                'name' => (string) ($stage['name'] ?? ''),
                'order_index' => (int) ($stage['order_nr'] ?? 0),
            ];
        }
    }

    public function fetchCustomFields(string $token, string $entity): iterable
    {
        $endpoint = self::FIELD_ENDPOINTS[$entity]
            ?? throw new CrmApiException("Entidade de campo customizado desconhecida: {$entity}.");

        foreach ($this->paginate($endpoint, $token) as $field) {
            // Only edit_flag=true fields are custom; the rest are Pipedrive defaults.
            if (empty($field['edit_flag'])) {
                continue;
            }

            yield [
                'external_id' => (string) $field['id'],
                'name' => (string) ($field['name'] ?? ''),
                'field_key' => isset($field['key']) ? (string) $field['key'] : null,
                'field_type' => isset($field['field_type']) ? (string) $field['field_type'] : null,
            ];
        }
    }

    public function fetchPersons(string $token): iterable
    {
        foreach ($this->paginate('/persons', $token) as $person) {
            yield [
                'external_id' => (string) $person['id'],
                'name' => isset($person['name']) ? (string) $person['name'] : null,
                'email' => $this->primaryValue($person['email'] ?? null),
                'phone' => $this->primaryValue($person['phone'] ?? null),
            ];
        }
    }

    public function fetchDeals(string $token): iterable
    {
        foreach ($this->paginate('/deals', $token) as $deal) {
            yield [
                'external_id' => (string) $deal['id'],
                'title' => (string) ($deal['title'] ?? ''),
                'value' => $deal['value'] ?? null,
                'pipeline_external_id' => $this->idValue($deal['pipeline_id'] ?? null),
                'stage_external_id' => $this->idValue($deal['stage_id'] ?? null),
                'person_external_id' => $this->idValue($deal['person_id'] ?? null),
                'status' => isset($deal['status']) ? (string) $deal['status'] : null,
            ];
        }
    }

    /**
     * Stream every record from a paginated Pipedrive collection endpoint.
     *
     * Yields items eagerly per page so callers persist partial results — if a
     * later page fails, everything already yielded stays imported.
     *
     * @return iterable<array<string, mixed>>
     *
     * @throws CrmApiException on a network failure or non-2xx response
     */
    private function paginate(string $endpoint, string $token): iterable
    {
        $baseUrl = rtrim((string) config('services.pipedrive.base_url'), '/');
        $start = 0;

        while (true) {
            try {
                $response = Http::timeout(15)
                    ->acceptJson()
                    ->get("{$baseUrl}{$endpoint}", [
                        'api_token' => $token,
                        'start' => $start,
                        'limit' => self::PAGE_SIZE,
                    ]);
            } catch (ConnectionException) {
                throw new CrmApiException(
                    "Falha de rede ao acessar {$endpoint} na Pipedrive. Tente novamente em alguns minutos."
                );
            }

            if (! $response->successful()) {
                throw new CrmApiException($this->failureMessage($response, $endpoint, $start));
            }

            $data = $response->json('data') ?? [];

            foreach ($data as $item) {
                yield $item;
            }

            $pagination = $response->json('additional_data.pagination') ?? [];

            if (empty($pagination['more_items_in_collection'])) {
                break;
            }

            $start = (int) ($pagination['next_start'] ?? ($start + self::PAGE_SIZE));
        }
    }

    /**
     * Build a legible, copyable error message for a failed page request.
     */
    private function failureMessage(Response $response, string $endpoint, int $start): string
    {
        $page = intdiv($start, self::PAGE_SIZE) + 1;

        return sprintf(
            'Pipedrive API respondeu %d (%s) ao paginar %s — página %d. Tente novamente em alguns minutos.',
            $response->status(),
            $this->reasonPhrase($response->status()),
            $endpoint,
            $page,
        );
    }

    /**
     * Human-readable reason phrase for the common HTTP failure statuses.
     */
    private function reasonPhrase(int $status): string
    {
        return match ($status) {
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => 'HTTP '.$status,
        };
    }

    /**
     * Pipedrive returns email/phone as a list of `{value, primary}` maps (or,
     * for imported data, a plain string). Return the primary/first value.
     */
    private function primaryValue(mixed $field): ?string
    {
        if (is_string($field)) {
            return $field !== '' ? $field : null;
        }

        if (! is_array($field) || $field === []) {
            return null;
        }

        foreach ($field as $entry) {
            if (is_array($entry) && ! empty($entry['primary']) && ! empty($entry['value'])) {
                return (string) $entry['value'];
            }
        }

        $first = $field[0] ?? null;

        if (is_array($first)) {
            return isset($first['value']) ? (string) $first['value'] : null;
        }

        return $first !== null ? (string) $first : null;
    }

    /**
     * Relation ids on a deal may come back as a scalar or as a `{value: id}`
     * map depending on the endpoint. Normalise both to a string id.
     */
    private function idValue(mixed $value): ?string
    {
        if (is_array($value)) {
            return isset($value['value']) ? (string) $value['value'] : null;
        }

        return $value !== null ? (string) $value : null;
    }
}
