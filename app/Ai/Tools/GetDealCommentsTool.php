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
        return <<<'DESCRIPTION'
            Retorna os comentários registrados no negócio (deal) deste cliente no CRM, cada um com seu conteúdo e o momento em que foi escrito. Comentários são o registro cronológico do time sobre a negociação.

            Use quando precisar de contexto do histórico de atendimento para responder bem — o que já foi combinado, tratado ou observado sobre este negócio — antes de responder uma dúvida cujo esclarecimento pode estar registrado ali.

            Não recebe parâmetros: o negócio é identificado automaticamente pela conversa. Para anotações vinculadas ao cliente/pessoa, use a ferramenta de notas; este retorno traz apenas os comentários do negócio.
            DESCRIPTION;
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
