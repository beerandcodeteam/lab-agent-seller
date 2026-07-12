<?php

namespace App\Services\Crm;

use App\Models\Conversation;
use App\Models\CrmPerson;
use App\Models\Deal;
use Illuminate\Support\Str;

/**
 * Resolves a conversation to its CRM identity entirely app-side — token,
 * driver, matched `crm_persons` and the deal to operate on — so no identifier
 * ever reaches the LLM (RF-09). Performs no HTTP: it only reads the local
 * mirror to resolve external ids the driver will use at call time (RNF-01).
 */
class ConversationDealResolver
{
    public function __construct(private CrmDriverManager $manager) {}

    /**
     * Resolve the conversation's company token, driver, matched persons and
     * open deal. Missing company/connection, unmatched client, or no open deal
     * all yield a `deal = null` resolution (the "sem deal" state of RF-10).
     */
    public function resolve(Conversation $conversation): ConversationDealResolution
    {
        $connection = $conversation->user?->crmConnection;

        if ($connection === null) {
            return ConversationDealResolution::empty();
        }

        $driver = $this->manager->driver($connection->crmProvider->slug);
        $token = $connection->api_token;

        $email = Str::lower(trim((string) $conversation->client?->email));

        if ($email === '') {
            return new ConversationDealResolution($token, $driver, [], null);
        }

        // Match persons case-insensitively, scoped to this company's connection
        // (tenant isolation, RNF-02). Multiple matches are all considered.
        $persons = CrmPerson::query()
            ->where('crm_connection_id', $connection->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get();

        /** @var list<string> $personExternalIds */
        $personExternalIds = $persons
            ->pluck('external_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        // UNION of the matched persons' deals → the open deal most recently
        // updated (RF-12). No open deal → null ("sem deal", RF-10).
        $deal = Deal::query()
            ->where('crm_connection_id', $connection->id)
            ->whereIn('crm_person_id', $persons->pluck('id'))
            ->whereHas('dealStatus', fn ($query) => $query->where('slug', 'open'))
            ->orderByDesc('updated_at')
            ->first();

        $resolvedDeal = $deal === null ? null : new ResolvedDeal(
            externalId: (string) $deal->external_id,
            pipelineExternalId: $deal->pipeline?->external_id !== null
                ? (string) $deal->pipeline->external_id
                : null,
            status: $deal->dealStatus?->slug,
        );

        return new ConversationDealResolution($token, $driver, $personExternalIds, $resolvedDeal);
    }
}
