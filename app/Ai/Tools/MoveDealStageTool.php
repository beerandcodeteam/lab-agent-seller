<?php

namespace App\Ai\Tools;

use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Move the conversation's deal to a target stage (RF-06). The only LLM-facing
 * input is `stage_id` — the external Pipedrive stage id from ListPipelinesTool
 * (RF-05/CT-04). The target must belong to the deal's own pipeline; a stage of
 * another pipeline, or a closed deal, is refused without calling the driver.
 */
class MoveDealStageTool extends PipedriveConversationTool
{
    /**
     * Returned when the target stage belongs to another pipeline (RF-06).
     */
    private const CrossPipelineMarker = 'O estágio informado pertence a outro funil (pipeline); só é possível mover o negócio dentro do mesmo funil.';

    /**
     * Returned when the target stage does not exist in any pipeline.
     */
    private const UnknownStageMarker = 'O estágio informado não foi encontrado nos funis da empresa.';

    /**
     * Returned when the deal was moved successfully.
     */
    private const MovedMarker = 'O negócio foi movido para o estágio solicitado no CRM.';

    public function description(): string
    {
        return 'Move o negócio (deal) deste cliente para um estágio-alvo do mesmo funil no CRM. Informe apenas o "stage_id" (id do estágio obtido em ListPipelines). O negócio é identificado automaticamente pela conversa. Só move dentro do mesmo funil e nunca altera um negócio já fechado.';
    }

    public function handle(Request $request): string
    {
        $stageExternalId = (string) $request['stage_id'];

        $resolution = $this->resolve();

        if (! $resolution->hasDeal()) {
            return self::NoDealMarker;
        }

        try {
            $deal = $resolution->driver->fetchDeal($resolution->token, $resolution->deal->externalId);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        if ($this->isClosedStatus($deal['status'])) {
            return self::ClosedDealMarker;
        }

        try {
            $pipelines = $resolution->driver->fetchPipelinesWithStages($resolution->token);
            $pipelines = is_array($pipelines) ? $pipelines : iterator_to_array($pipelines);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        $targetPipelineExternalId = $this->pipelineOfStage($pipelines, $stageExternalId);

        if ($targetPipelineExternalId === null) {
            return self::UnknownStageMarker;
        }

        $dealPipelineExternalId = $deal['pipeline_external_id'] ?? $resolution->deal->pipelineExternalId;

        if ($targetPipelineExternalId !== $dealPipelineExternalId) {
            return self::CrossPipelineMarker;
        }

        try {
            $resolution->driver->moveDealStage($resolution->token, $resolution->deal->externalId, $stageExternalId);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        return self::MovedMarker;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stage_id' => $schema->string()
                ->description('Id externo do estágio-alvo do Pipedrive, obtido pela ferramenta ListPipelines. Deve pertencer ao mesmo funil do negócio.')
                ->required(),
        ];
    }

    /**
     * Find which pipeline (external id) owns the given stage, or null when no
     * pipeline contains it.
     *
     * @param  iterable<array{id: string, name: string, stages: list<array{id: string, name: string}>}>  $pipelines
     */
    private function pipelineOfStage(iterable $pipelines, string $stageExternalId): ?string
    {
        foreach ($pipelines as $pipeline) {
            foreach ($pipeline['stages'] as $stage) {
                if ((string) $stage['id'] === $stageExternalId) {
                    return (string) $pipeline['id'];
                }
            }
        }

        return null;
    }
}
