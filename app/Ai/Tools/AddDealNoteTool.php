<?php

namespace App\Ai\Tools;

use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Persist a free-text note on the conversation's deal (or, when there is no
 * resolvable deal, on the matched person). The note is attached CRM-side to
 * whatever identity is resolved app-side from the conversation (RF-09). The
 * only LLM-facing input is the note `content`, forwarded verbatim.
 */
class AddDealNoteTool extends PipedriveConversationTool
{
    /**
     * Returned when the note was saved successfully.
     */
    private const SavedMarker = 'A anotação foi registrada no CRM.';

    public function description(): string
    {
        return <<<'DESCRIPTION'
            Registra uma anotação (nota) permanente no histórico do negócio deste cliente no CRM. A nota fica visível para a equipe comercial humana em futuros atendimentos.

            Use esta ferramenta sempre que o cliente revelar, durante a conversa, uma informação relevante para a negociação que valha a pena preservar além deste chat — por exemplo: contexto sobre sua experiência ou uso do produto, preferências, restrições, objeções, orçamento, prazos, quem decide a compra, motivos de interesse ou de hesitação, ou qualquer fato do relacionamento comercial digno de nota. Não peça permissão nem anuncie ao cliente que está anotando: registre em segundo plano e siga a conversa naturalmente.

            Escreva o campo "content" como uma anotação objetiva em terceira pessoa, resumindo o fato relevante em uma ou duas frases (ex.: "Cliente relatou que a integração atual é lenta e busca migrar até o fim do trimestre."). Não registre saudações, conversa fiada nem dados que o CRM já fornece por outras ferramentas (valor, estágio, status). O cliente e o negócio são identificados automaticamente pela conversa; não há outro parâmetro. Quando não houver negócio, a nota é vinculada à pessoa do cliente.
            DESCRIPTION;
    }

    public function handle(Request $request): string
    {
        $content = $request['content'] ?? null;
        $content = is_string($content) ? trim($content) : '';

        if ($content === '') {
            return self::FailureMarker;
        }

        $resolution = $this->resolve();

        $dealExternalId = $resolution->hasDeal() ? $resolution->deal->externalId : null;
        $personExternalId = $resolution->personExternalIds[0] ?? null;

        // No deal and no matched person → nothing to attach the note to (RF-10).
        if ($dealExternalId === null && $personExternalId === null) {
            return self::NoDealMarker;
        }

        try {
            $resolution->driver->addNote($resolution->token, $dealExternalId, $personExternalId, $content);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        return self::SavedMarker;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->description('Texto da anotação a registrar no CRM: um resumo objetivo, em terceira pessoa, do fato comercial relevante informado pelo cliente. Obrigatório.')
                ->required(),
        ];
    }
}
