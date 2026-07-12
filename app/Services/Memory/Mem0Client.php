<?php

namespace App\Services\Memory;

use App\Ai\Tools\RecallClientMemoriesTool;
use App\Ai\Tools\RememberClientFactTool;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client for the mem0 Platform REST API (v3). Stores and retrieves the
 * per-client sales memories written by the {@see RememberClientFactTool}
 * and read by the {@see RecallClientMemoriesTool}.
 *
 * Scope is always a (user_id, agent_id) pair: `user_id` is the final client and
 * `agent_id` is the company (tenant), so a client's memories are isolated per
 * company and never bleed across tenants.
 *
 * The client is a no-op (add is silently dropped, search returns nothing) when
 * no MEM0_API_KEY is configured, so the feature can ship dark and the agent
 * keeps working without it.
 */
class Mem0Client
{
    private ?string $apiKey;

    private string $baseUrl;

    private int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.mem0.api_key');
        $this->baseUrl = rtrim((string) config('services.mem0.base_url', 'https://api.mem0.ai'), '/');
        $this->timeout = (int) config('services.mem0.timeout', 10);
    }

    /**
     * Whether mem0 is configured. When false the tools tell the model the
     * memory is unavailable instead of pretending to store or recall.
     */
    public function enabled(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Store a fact for the (client, company) pair. The content is passed as a
     * user turn so mem0's extraction pipeline dedupes and merges it against
     * existing memories; the category (when given) is attached as metadata.
     *
     * @throws Mem0Exception on any transport error or non-2xx response.
     */
    public function add(string $content, string $userId, string $agentId, ?MemoryCategory $category = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
            'user_id' => $userId,
            'agent_id' => $agentId,
        ];

        if ($category !== null) {
            $payload['metadata'] = ['category' => $category->value];
        }

        try {
            $response = $this->request()->post($this->baseUrl.'/v3/memories/add/', $payload);
        } catch (Throwable $exception) {
            throw new Mem0Exception('mem0 add request failed', previous: $exception);
        }

        if ($response->failed()) {
            throw new Mem0Exception('mem0 add returned status '.$response->status());
        }
    }

    /**
     * Semantic search of the (client, company) memories. Returns the memory
     * texts most relevant to the query, at most $limit of them.
     *
     * @return array<int, string>
     *
     * @throws Mem0Exception on any transport error or non-2xx response.
     */
    public function search(string $query, string $userId, string $agentId, int $limit = 5): array
    {
        if (! $this->enabled()) {
            return [];
        }

        try {
            $response = $this->request()->post($this->baseUrl.'/v3/memories/search/', [
                'query' => $query,
                'filters' => [
                    'user_id' => $userId,
                    'agent_id' => $agentId,
                ],
            ]);
        } catch (Throwable $exception) {
            throw new Mem0Exception('mem0 search request failed', previous: $exception);
        }

        if ($response->failed()) {
            throw new Mem0Exception('mem0 search returned status '.$response->status());
        }

        return $this->extractMemories($response->json(), $limit);
    }

    /**
     * A pending request configured with the mem0 token auth and timeout.
     */
    private function request(): PendingRequest
    {
        return Http::withToken((string) $this->apiKey, 'Token')
            ->acceptJson()
            ->timeout($this->timeout);
    }

    /**
     * Normalise the search response — mem0 has returned the hits both as a bare
     * list and under a `results` key across versions — into a capped list of
     * memory strings.
     *
     * @param  mixed  $body
     * @return array<int, string>
     */
    private function extractMemories($body, int $limit): array
    {
        if (! is_array($body)) {
            return [];
        }

        $items = $body['results'] ?? (array_is_list($body) ? $body : []);

        if (! is_array($items)) {
            return [];
        }

        $memories = [];

        foreach ($items as $item) {
            if (count($memories) >= $limit) {
                break;
            }

            $memory = is_array($item) ? ($item['memory'] ?? null) : null;

            if (is_string($memory) && $memory !== '') {
                $memories[] = $memory;
            }
        }

        return $memories;
    }
}
