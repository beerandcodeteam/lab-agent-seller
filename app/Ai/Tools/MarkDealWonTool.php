<?php

namespace App\Ai\Tools;

use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Mark the conversation's deal as won (RF-07). Takes no input. A deal that is
 * already closed (won/lost) is refused without calling the driver (RF-12).
 */
class MarkDealWonTool extends PipedriveConversationTool
{
    /**
     * Returned when the deal was marked won successfully.
     */
    private const WonMarker = 'O negócio foi marcado como ganho no CRM.';

    public function description(): string
    {
        return <<<'DESCRIPTION'
            Marca o negócio (deal) deste cliente como ganho (won) no CRM, encerrando a negociação como venda fechada.

            Use somente quando o cliente confirmar de forma inequívoca a aceitação da proposta e o playbook autorizar o fechamento — é uma ação definitiva. Na dúvida sobre a intenção do cliente, confirme com ele antes de chamar; não marque como ganho por otimismo ou antecipação.

            Não recebe parâmetros: o negócio é identificado automaticamente pela conversa. A ferramenta recusa, sem alterar nada, um negócio já fechado. Para registrar perda use a ferramenta de negócio perdido; para apenas mudar de etapa, a ferramenta de mover estágio.
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

        if ($this->isClosedStatus($deal['status'])) {
            return self::ClosedDealMarker;
        }

        try {
            $resolution->driver->markDealWon($resolution->token, $resolution->deal->externalId);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        return self::WonMarker;
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
