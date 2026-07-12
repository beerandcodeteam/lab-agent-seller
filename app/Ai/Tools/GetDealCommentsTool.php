<?php

namespace App\Ai\Tools;

use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Comments of the conversation's deal, fetched live (RF-03). Takes no input.
 */
class GetDealCommentsTool extends PipedriveConversationTool
{
    public function description(): string
    {
        return 'Retorna os comentários do negócio (deal) deste cliente no CRM, cada um com seu conteúdo e momento. Não recebe nenhum parâmetro — o negócio é identificado automaticamente pela conversa.';
    }

    public function handle(Request $request): string
    {
        $resolution = $this->resolve();

        if (! $resolution->hasDeal()) {
            return self::NoDealMarker;
        }

        try {
            $comments = $resolution->driver->fetchDealComments($resolution->token, $resolution->deal->externalId);
            $comments = is_array($comments) ? $comments : iterator_to_array($comments);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        $list = [];

        foreach ($comments as $comment) {
            $list[] = [
                'conteudo' => $comment['content'],
                'quando' => $comment['created_at'],
            ];
        }

        return $this->encode(['comentarios' => $list]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
