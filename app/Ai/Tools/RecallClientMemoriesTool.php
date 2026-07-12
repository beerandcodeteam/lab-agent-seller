<?php

namespace App\Ai\Tools;

use App\Services\Memory\Mem0Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Retrieve what the agent already knows about the current client from its own
 * per-client memory (mem0): stated objections, arguments already used, features
 * already informed, budget, preferences and other durable negotiation facts.
 *
 * This lets the agent recall context on demand instead of carrying the whole
 * raw chat history in every turn — it queries memory only when it is useful.
 */
class RecallClientMemoriesTool extends ClientMemoryTool
{
    /**
     * How many memories to bring back at most, keeping the recall focused.
     */
    private const Limit = 5;

    /**
     * Returned when the client has no stored memories yet.
     */
    private const EmptyMarker = 'Nenhuma memória registrada para este cliente ainda.';

    public function description(): string
    {
        return <<<'DESCRIPTION'
            Consulta a sua memória de longo prazo sobre ESTE cliente e retorna os fatos mais relevantes já registrados: objeções declaradas, argumentos de venda que você já usou, recursos já apresentados, orçamento, prazos, quem decide, preferências e dores.

            Use no início do atendimento e sempre que for retomar a conversa, antes de argumentar ou apresentar algo, para não repetir o que já foi dito e manter a coerência com atendimentos anteriores. Descreva em "query" o que você quer lembrar (ex.: "objeções sobre preço", "recursos já apresentados", "quem decide a compra").

            O cliente é identificado automaticamente pela conversa. Se não houver memória, você recebe um aviso de que ainda não há nada registrado — nesse caso, conduza a conversa normalmente.
            DESCRIPTION;
    }

    public function handle(Request $request): string
    {
        if (! $this->memory()->enabled()) {
            return self::DisabledMarker;
        }

        $query = $request['query'] ?? null;
        $query = is_string($query) ? trim($query) : '';

        if ($query === '') {
            return self::FailureMarker;
        }

        try {
            $memories = $this->memory()->search($query, $this->userId(), $this->agentId(), self::Limit);
        } catch (Mem0Exception) {
            return self::FailureMarker;
        }

        if ($memories === []) {
            return self::EmptyMarker;
        }

        return $this->encode(['memorias' => $memories]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('O que você quer lembrar sobre este cliente (ex.: "objeções sobre preço"). Obrigatório.')
                ->required(),
        ];
    }
}
