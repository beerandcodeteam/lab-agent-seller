<?php

namespace App\Ai\Tools;

use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Mark the conversation's deal as lost (RF-08). The only LLM-facing input is an
 * optional free-text `lost_reason`, forwarded verbatim to the driver (CT-04).
 * A deal that is already closed (won/lost) is refused without calling the
 * driver (RF-12).
 */
class MarkDealLostTool extends PipedriveConversationTool
{
    /**
     * Returned when the deal was marked lost successfully.
     */
    private const LostMarker = 'O negócio foi marcado como perdido no CRM.';

    public function description(): string
    {
        return 'Marca o negócio (deal) deste cliente como perdido (lost) no CRM. Aceita opcionalmente "lost_reason", um texto livre com o motivo da perda, registrado como informado. O negócio é identificado automaticamente pela conversa. Nunca altera um negócio já fechado.';
    }

    public function handle(Request $request): string
    {
        $lostReason = $request['lost_reason'] ?? null;
        $lostReason = is_string($lostReason) && trim($lostReason) !== '' ? $lostReason : null;

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
            $resolution->driver->markDealLost($resolution->token, $resolution->deal->externalId, $lostReason);
        } catch (CrmApiException) {
            return self::FailureMarker;
        }

        return self::LostMarker;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lost_reason' => $schema->string()
                ->description('Motivo da perda em texto livre (opcional). Registrado como informado, sem validação.'),
        ];
    }
}
