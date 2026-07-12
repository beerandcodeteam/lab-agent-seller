<?php

namespace App\Services\Crm;

use App\Services\Crm\Contracts\CrmDriver;

/**
 * Result of resolving a conversation to its CRM identity, app-side. Holds the
 * company token, the resolved driver, the matched persons' external ids (for
 * person-scoped reads even without a deal) and the resolved deal (or null when
 * there is no resolvable open deal — the "sem deal" state of RF-10).
 */
final class ConversationDealResolution
{
    /**
     * @param  list<string>  $personExternalIds
     */
    public function __construct(
        public ?string $token,
        public ?CrmDriver $driver,
        public array $personExternalIds,
        public ?ResolvedDeal $deal,
    ) {}

    /**
     * A resolution with no company/connection — nothing to read (RF-10/RNF-02).
     */
    public static function empty(): self
    {
        return new self(null, null, [], null);
    }

    /**
     * Whether an open deal was resolved for the conversation.
     */
    public function hasDeal(): bool
    {
        return $this->deal !== null;
    }
}
