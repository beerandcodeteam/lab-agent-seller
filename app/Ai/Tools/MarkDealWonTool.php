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
        return 'Marca o negócio (deal) deste cliente como ganho (won) no CRM. Não recebe nenhum parâmetro — o negócio é identificado automaticamente pela conversa. Nunca altera um negócio já fechado.';
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
