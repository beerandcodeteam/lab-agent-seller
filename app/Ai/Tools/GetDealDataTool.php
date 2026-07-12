<?php

namespace App\Ai\Tools;

use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Live deal data (title, value, current stage, status) of the conversation's
 * client (RF-01). Takes no input — the deal is resolved app-side (RF-09).
 */
class GetDealDataTool extends PipedriveConversationTool
{
    public function description(): string
    {
        return <<<'DESCRIPTION'
            Consulta ao vivo, no CRM, os dados do negócio (deal) em aberto deste cliente e retorna título, valor, estágio atual no funil e status (aberto/ganho/perdido).

            Use esta ferramenta sempre que precisar afirmar ou confirmar qualquer dado da proposta em andamento — quando o cliente pergunta "em que pé está minha proposta?", "qual o valor?", "em que etapa estamos?", ou antes de você mencionar valor, estágio ou status na resposta. Os dados retornados são a verdade sobre o negócio e prevalecem sobre sua memória ou suposições.

            Não recebe parâmetros: o cliente e o negócio são identificados automaticamente pela conversa. Se o retorno indicar que não há negócio, informe isso com transparência em vez de inventar dados.
            DESCRIPTION;
    }

    public function handle(Request $request): string
    {
        $resolution = $this->resolve();

        if (! $resolution->hasDeal()) {
            return self::NoDealMarker;
        }

        try {
            $deal = $resolution->driver->fetchDeal($resolution->token, $resolution->deal->externalId);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        $stageExternalId = $deal['stage_external_id'];

        if ($stageExternalId === null) {
            $stage = '(sem estágio)';
        } else {
            $stage = $this->stageNames($resolution)[$stageExternalId] ?? $stageExternalId;
        }

        return $this->encode([
            'negocio' => [
                'titulo' => $deal['title'],
                'valor' => $deal['value'],
                'estagio' => $stage,
                'status' => $deal['status'] ?? '(desconhecido)',
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
