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
        return 'Retorna os dados ao vivo do negócio (deal) deste cliente no CRM: título, valor, estágio atual e status. Não recebe nenhum parâmetro — o cliente e o negócio são identificados automaticamente a partir da conversa. Use antes de afirmar qualquer dado sobre a proposta/negócio do cliente.';
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
