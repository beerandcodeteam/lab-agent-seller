<?php

namespace App\Ai\Tools;

use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Every pipeline and its stages (id + name), fetched live (RF-05). Independent
 * of any deal, so it stays available even without a resolvable deal (RF-10).
 * The `id` is the external Pipedrive stage id accepted by MoveDealStageTool.
 */
class ListPipelinesTool extends PipedriveConversationTool
{
    public function description(): string
    {
        return <<<'DESCRIPTION'
            Retorna todos os funis (pipelines) da empresa e seus estágios, cada estágio com seu id e nome. É a fonte dos ids de estágio aceitos pela ferramenta de mover o negócio de estágio.

            Use esta ferramenta imediatamente antes de mover um negócio, para descobrir o id do estágio-alvo correto. Não tente adivinhar ou reutilizar um id de memória: consulte aqui primeiro e escolha o estágio que pertence ao mesmo funil do negócio.

            Não recebe parâmetros e independe de haver um negócio em aberto. Não exponha ids ao cliente; use os nomes dos estágios ao conversar.
            DESCRIPTION;
    }

    public function handle(Request $request): string
    {
        $resolution = $this->resolve();

        if ($resolution->driver === null || $resolution->token === null) {
            return self::FailureMarker;
        }

        try {
            $pipelines = $resolution->driver->fetchPipelinesWithStages($resolution->token);
            $pipelines = is_array($pipelines) ? $pipelines : iterator_to_array($pipelines);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        $list = [];

        foreach ($pipelines as $pipeline) {
            $stages = [];

            foreach ($pipeline['stages'] as $stage) {
                $stages[] = [
                    'id' => $stage['id'],
                    'nome' => $stage['name'],
                ];
            }

            $list[] = [
                'id' => $pipeline['id'],
                'nome' => $pipeline['name'],
                'estagios' => $stages,
            ];
        }

        return $this->encode(['pipelines' => $list]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
