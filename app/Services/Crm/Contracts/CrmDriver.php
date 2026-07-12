<?php

namespace App\Services\Crm\Contracts;

use App\Services\Crm\CrmTokenStatus;
use App\Services\Crm\Exceptions\CrmApiException;
use App\Services\Crm\Exceptions\EmptyTokenException;

/**
 * Provider-agnostic contract for a CRM integration driver.
 *
 * The `fetch*` methods stream the provider's data page by page (as generators)
 * so a scan can persist partial results: if the provider fails mid-pagination
 * (e.g. a 429), everything yielded before the failure has already been imported.
 */
interface CrmDriver
{
    /**
     * The provider slug this driver serves (matches crm_providers.slug).
     */
    public function slug(): string;

    /**
     * Validate an API token against the provider.
     *
     * @throws EmptyTokenException when the token is blank (no API call is made)
     */
    public function validateToken(string $token): CrmTokenStatus;

    /**
     * Stream the account's pipelines.
     *
     * @return iterable<array{external_id: string, name: string}>
     *
     * @throws CrmApiException on a provider API failure
     */
    public function fetchPipelines(string $token): iterable;

    /**
     * Stream the account's pipeline stages.
     *
     * @return iterable<array{external_id: string, pipeline_external_id: string, name: string, order_index: int}>
     *
     * @throws CrmApiException on a provider API failure
     */
    public function fetchStages(string $token): iterable;

    /**
     * Stream the account's custom fields for a given entity.
     *
     * @param  'person'|'deal'  $entity
     * @return iterable<array{external_id: string, name: string, field_key: string|null, field_type: string|null}>
     *
     * @throws CrmApiException on a provider API failure
     */
    public function fetchCustomFields(string $token, string $entity): iterable;

    /**
     * Stream the account's persons/contacts.
     *
     * @return iterable<array{external_id: string, name: string|null, email: string|null, phone: string|null}>
     *
     * @throws CrmApiException on a provider API failure
     */
    public function fetchPersons(string $token): iterable;

    /**
     * Stream the account's deals.
     *
     * @return iterable<array{external_id: string, title: string, value: float|int|string|null, pipeline_external_id: string|null, stage_external_id: string|null, person_external_id: string|null, status: string|null}>
     *
     * @throws CrmApiException on a provider API failure
     */
    public function fetchDeals(string $token): iterable;

    /**
     * Fetch a single deal's live data by its external id. Missing provider
     * fields are returned as `null` (never fabricated).
     *
     * @return array{title: string|null, value: float|int|string|null, stage_external_id: string|null, status: string|null, pipeline_external_id: string|null}
     *
     * @throws CrmApiException on a provider API failure (a live 404 is a failure, not an empty result)
     */
    public function fetchDeal(string $token, string $dealExternalId): array;

    /**
     * Stream a deal's stage-change events only, filtering out every other kind
     * of flow event.
     *
     * @return iterable<array{from_stage_external_id: string|null, to_stage_external_id: string|null, changed_at: string|null}>
     *
     * @throws CrmApiException on a provider API failure
     */
    public function fetchDealStageChanges(string $token, string $dealExternalId): iterable;

    /**
     * Stream a deal's comments (content + moment).
     *
     * @return iterable<array{content: string|null, created_at: string|null}>
     *
     * @throws CrmApiException on a provider API failure
     */
    public function fetchDealComments(string $token, string $dealExternalId): iterable;

    /**
     * Fetch the merged notes of a deal and/or a person, each item labelled by
     * its origin (`deal` or `person`). Either id may be null; a person's notes
     * remain available even without a deal.
     *
     * @return iterable<array{source: 'deal'|'person', content: string|null, created_at: string|null}>
     *
     * @throws CrmApiException on a provider API failure
     */
    public function fetchNotes(string $token, ?string $dealExternalId, ?string $personExternalId): iterable;

    /**
     * Fetch every pipeline with its stages, each stage carrying its external
     * Pipedrive `id` and `name`.
     *
     * @return iterable<array{id: string, name: string, stages: list<array{id: string, name: string}>}>
     *
     * @throws CrmApiException on a provider API failure
     */
    public function fetchPipelinesWithStages(string $token): iterable;

    /**
     * Move a deal to a target stage, identified by its external Pipedrive stage
     * id (no local↔external translation). Same-pipeline validation and the
     * refusal of a closed deal are decided app-side, not here.
     *
     * @throws CrmApiException on a provider API failure
     */
    public function moveDealStage(string $token, string $dealExternalId, string $stageExternalId): void;

    /**
     * Mark a deal as won (`status=won`).
     *
     * @throws CrmApiException on a provider API failure
     */
    public function markDealWon(string $token, string $dealExternalId): void;

    /**
     * Mark a deal as lost (`status=lost`). When a free-text `$lostReason` is
     * given, it is forwarded verbatim (no validation against configured reasons).
     *
     * @throws CrmApiException on a provider API failure
     */
    public function markDealLost(string $token, string $dealExternalId, ?string $lostReason = null): void;

    /**
     * Create a free-text note attached to a deal and/or a person. Either id may
     * be null; when both are given the note is linked to both. `$content` is
     * forwarded verbatim.
     *
     * @throws CrmApiException on a provider API failure
     */
    public function addNote(string $token, ?string $dealExternalId, ?string $personExternalId, string $content): void;
}
