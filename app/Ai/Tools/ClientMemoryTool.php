<?php

namespace App\Ai\Tools;

use App\Models\Conversation;
use App\Services\Memory\Mem0Client;
use Laravel\Ai\Contracts\Tool;

/**
 * Shared base for the per-client memory tools. Both derive their mem0 scope
 * app-side from the injected {@see Conversation} — `user_id` is the final
 * client and `agent_id` is the company — so no identifier ever appears in the
 * LLM-facing input schema and a client's memory is isolated per company.
 *
 * On any mem0 failure (or when mem0 is not configured) the tools return a
 * generic marker for the model — never a technical error, id or token.
 */
abstract class ClientMemoryTool implements Tool
{
    /**
     * Returned when mem0 is not configured, so the agent knows the memory is
     * simply unavailable rather than empty.
     */
    protected const DisabledMarker = 'A memória do cliente não está disponível no momento.';

    /**
     * Returned when a mem0 call fails, so the agent proceeds without memory
     * instead of surfacing an error to the client.
     */
    protected const FailureMarker = 'Não foi possível acessar a memória do cliente agora.';

    public function __construct(protected Conversation $conversation) {}

    /**
     * The shared mem0 client (no-op when unconfigured).
     */
    protected function memory(): Mem0Client
    {
        return app(Mem0Client::class);
    }

    /**
     * The mem0 `user_id` scope: the final client of this conversation.
     */
    protected function userId(): string
    {
        return 'client_'.$this->conversation->client_id;
    }

    /**
     * The mem0 `agent_id` scope: the company (tenant) of this conversation, so
     * memories never cross tenant boundaries.
     */
    protected function agentId(): string
    {
        return 'company_'.$this->conversation->user_id;
    }

    /**
     * Serialize a tool payload to a compact JSON string for the model.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function encode(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
