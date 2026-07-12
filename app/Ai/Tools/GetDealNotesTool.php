<?php

namespace App\Ai\Tools;

use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Merged notes of the conversation's deal and person, each item labelled by
 * its origin (RF-04). The person's notes stay available even when there is no
 * resolvable deal (RF-04/RF-10). Takes no input.
 */
class GetDealNotesTool extends PipedriveConversationTool
{
    public function description(): string
    {
        return 'Retorna as anotações (notas) deste cliente no CRM, mesclando as notas do negócio (deal) e as notas da pessoa, cada uma marcada com sua origem (negócio ou pessoa). Não recebe nenhum parâmetro — cliente e negócio são identificados automaticamente pela conversa.';
    }

    public function handle(Request $request): string
    {
        $resolution = $this->resolve();

        $dealExternalId = $resolution->hasDeal() ? $resolution->deal->externalId : null;
        $personExternalIds = $resolution->personExternalIds;

        // Nothing to read at all → "sem deal" (RF-10). With a matched person but
        // no deal, the person's notes are still fetched below (RF-04).
        if ($dealExternalId === null && $personExternalIds === []) {
            return self::NoDealMarker;
        }

        try {
            $notes = [];

            if ($dealExternalId !== null) {
                foreach ($resolution->driver->fetchNotes($resolution->token, $dealExternalId, null) as $note) {
                    $notes[] = $this->normalize($note);
                }
            }

            foreach ($personExternalIds as $personExternalId) {
                foreach ($resolution->driver->fetchNotes($resolution->token, null, $personExternalId) as $note) {
                    $notes[] = $this->normalize($note);
                }
            }
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        return $this->encode(['notas' => $notes]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @param  array{source: 'deal'|'person', content: string|null, created_at: string|null}  $note
     * @return array{origem: string, conteudo: string|null, quando: string|null}
     */
    private function normalize(array $note): array
    {
        return [
            'origem' => $note['source'] === 'deal' ? 'negócio' : 'pessoa',
            'conteudo' => $note['content'],
            'quando' => $note['created_at'],
        ];
    }
}
