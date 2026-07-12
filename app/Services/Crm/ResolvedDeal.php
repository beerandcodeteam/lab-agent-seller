<?php

namespace App\Services\Crm;

/**
 * The single deal a conversation resolves to (RF-12), carrying only the
 * external identifiers a driver call needs — never a local id or client data.
 */
final class ResolvedDeal
{
    public function __construct(
        public string $externalId,
        public ?string $pipelineExternalId,
        public ?string $status,
    ) {}
}
