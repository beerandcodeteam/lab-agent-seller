<?php

namespace App\Ai\Tools;

use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Timeline of stage changes for the conversation's deal (RF-02). Only stage
 * changes are returned; every other flow event is discarded. Takes no input.
 */
class GetDealStageHistoryTool extends PipedriveConversationTool
{
    public function description(): string
    {
        return <<<'DESCRIPTION'
            Retorna a linha do tempo das mudanças de estágio (funil) do negócio deste cliente no CRM: para cada mudança, o estágio de origem, o estágio de destino e o momento em que ocorreu. Apenas mudanças de estágio são incluídas.

            Use quando o cliente perguntar sobre o andamento ou o histórico da negociação ("por quais etapas já passei?", "quando avançou?", "há quanto tempo está parado?"), ou quando você precisar entender a evolução do negócio ao longo do tempo para responder com precisão.

            Não recebe parâmetros: o negócio é identificado automaticamente pela conversa. Para saber o estágio atual (e não o histórico), use a ferramenta de dados do negócio.
            DESCRIPTION;
    }

    public function handle(Request $request): string
    {
        $resolution = $this->resolve();

        if (! $resolution->hasDeal()) {
            return self::NoDealMarker;
        }

        try {
            $changes = $resolution->driver->fetchDealStageChanges($resolution->token, $resolution->deal->externalId);
            $changes = is_array($changes) ? $changes : iterator_to_array($changes);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        $stageNames = $this->stageNames($resolution);

        $history = [];

        foreach ($changes as $change) {
            $history[] = [
                'de' => $this->label($change['from_stage_external_id'], $stageNames),
                'para' => $this->label($change['to_stage_external_id'], $stageNames),
                'quando' => $change['changed_at'],
            ];
        }

        return $this->encode(['mudancas_de_estagio' => $history]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Translate a stage external id to its name when known, else keep the id;
     * a null stage becomes an explicit unknown marker.
     *
     * @param  array<string, string>  $stageNames
     */
    private function label(?string $stageExternalId, array $stageNames): string
    {
        if ($stageExternalId === null) {
            return '(desconhecido)';
        }

        return $stageNames[$stageExternalId] ?? $stageExternalId;
    }
}
