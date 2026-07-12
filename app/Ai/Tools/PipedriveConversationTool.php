<?php

namespace App\Ai\Tools;

use App\Models\Conversation;
use App\Services\Crm\ConversationDealResolution;
use App\Services\Crm\ConversationDealResolver;
use App\Services\Crm\Exceptions\CrmApiException;
use Laravel\Ai\Contracts\Tool;

/**
 * Shared base for the Pipedrive conversation tools. Every tool resolves its
 * CRM identity (token, driver, matched persons, open deal) entirely app-side
 * from the injected {@see Conversation}, so no identifier ever appears in the
 * LLM-facing input schema (RF-09/CT-04).
 *
 * On any provider failure the tools return a generic marker for the model —
 * never a tool name, CRM id, token or technical error message (RF-11/RNF-03).
 */
abstract class PipedriveConversationTool implements Tool
{
    /**
     * Returned when the client has no resolvable open deal (RF-10). Distinct
     * from the "sem estágio"/"desconhecido" field markers of RF-01.
     */
    protected const NoDealMarker = 'Nenhum negócio (deal) foi encontrado para este cliente no CRM.';

    /**
     * Returned when a live CRM call fails, so the agent answers with "não
     * consegui confirmar" without fabricating data (RF-11/RNF-03).
     */
    protected const FailureMarker = 'Não foi possível confirmar essa informação no CRM neste momento.';

    /**
     * Returned when a mutation is refused because the live deal is already
     * closed (won/lost). The driver is never called in that case (RF-12).
     */
    protected const ClosedDealMarker = 'O negócio já está fechado (ganho ou perdido) e não pode ser alterado.';

    public function __construct(protected Conversation $conversation) {}

    /**
     * Whether a live deal status marks a closed (won/lost) deal that mutations
     * must refuse to touch (RF-12).
     */
    protected function isClosedStatus(?string $status): bool
    {
        return $status === 'won' || $status === 'lost';
    }

    /**
     * Resolve the conversation's CRM identity app-side (no HTTP here).
     */
    protected function resolve(): ConversationDealResolution
    {
        return app(ConversationDealResolver::class)->resolve($this->conversation);
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

    /**
     * Map every stage external id to its name, best-effort. A provider failure
     * degrades to an empty map (callers fall back to the raw id as the label),
     * never surfacing a technical error to the client (RF-11/RNF-03).
     *
     * @return array<string, string>
     */
    protected function stageNames(ConversationDealResolution $resolution): array
    {
        if ($resolution->driver === null || $resolution->token === null) {
            return [];
        }

        try {
            $names = [];

            foreach ($resolution->driver->fetchPipelinesWithStages($resolution->token) as $pipeline) {
                foreach ($pipeline['stages'] as $stage) {
                    $names[(string) $stage['id']] = (string) $stage['name'];
                }
            }

            return $names;
        } catch (CrmApiException) {
            return [];
        }
    }
}
