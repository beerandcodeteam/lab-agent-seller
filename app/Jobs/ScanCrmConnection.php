<?php

namespace App\Jobs;

use App\Models\CrmConnection;
use App\Models\CrmPerson;
use App\Models\CrmScan;
use App\Models\CustomField;
use App\Models\CustomFieldEntity;
use App\Models\Deal;
use App\Models\DealStatus;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\ScanStatus;
use App\Services\Crm\Contracts\CrmDriver;
use App\Services\Crm\CrmDriverManager;
use App\Services\Crm\Exceptions\CrmApiException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scans a company's CRM and imports its data locally.
 *
 * Drives the scan state machine `pending → running → success|failed`, upserting
 * pipelines/stages, custom fields, persons and deals by `external_id` so a
 * re-scan never duplicates rows. On a provider API failure the scan is marked
 * `failed` with a copyable message, and every record imported before the
 * failure stays in the database (no rollback of partial data).
 */
class ScanCrmConnection implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CrmConnection $crmConnection,
        public ?CrmScan $crmScan = null,
    ) {}

    /**
     * Create a pending scan and queue the job to process it.
     */
    public static function enqueue(CrmConnection $connection): CrmScan
    {
        $scan = $connection->crmScans()->create([
            'scan_status_id' => ScanStatus::slug('pending')?->id,
        ]);

        self::dispatch($connection, $scan);

        return $scan;
    }

    public function handle(CrmDriverManager $drivers): void
    {
        $connection = $this->crmConnection;

        $scan = $this->crmScan
            ?? $connection->crmScans()->latest('id')->first()
            ?? $connection->crmScans()->create([
                'scan_status_id' => ScanStatus::slug('pending')?->id,
            ]);

        $scan->update([
            'scan_status_id' => ScanStatus::slug('running')?->id,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $driver = $drivers->driver($connection->crmProvider->slug);
        $token = $connection->api_token;

        try {
            $this->import($connection, $driver, $token);
        } catch (CrmApiException $exception) {
            // Partial data already imported stays put — no rollback.
            $scan->update([
                'scan_status_id' => ScanStatus::slug('failed')?->id,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ] + $this->counts($connection));

            return;
        }

        $scan->update([
            'scan_status_id' => ScanStatus::slug('success')?->id,
            'finished_at' => now(),
        ] + $this->counts($connection));
    }

    /**
     * Import every entity, resolving cross-references by external id.
     */
    private function import(CrmConnection $connection, CrmDriver $driver, string $token): void
    {
        $pipelineIds = [];
        foreach ($driver->fetchPipelines($token) as $pipeline) {
            $pipelineIds[$pipeline['external_id']] = Pipeline::updateOrCreate(
                ['crm_connection_id' => $connection->id, 'external_id' => $pipeline['external_id']],
                ['name' => $pipeline['name']],
            )->id;
        }

        $stageIds = [];
        foreach ($driver->fetchStages($token) as $stage) {
            $pipelineId = $pipelineIds[$stage['pipeline_external_id']] ?? null;

            if ($pipelineId === null) {
                continue;
            }

            $stageIds[$stage['external_id']] = PipelineStage::updateOrCreate(
                ['pipeline_id' => $pipelineId, 'external_id' => $stage['external_id']],
                ['name' => $stage['name'], 'order_index' => $stage['order_index']],
            )->id;
        }

        foreach (['person', 'deal'] as $entity) {
            $entityId = CustomFieldEntity::slug($entity)?->id;

            foreach ($driver->fetchCustomFields($token, $entity) as $field) {
                CustomField::updateOrCreate(
                    ['crm_connection_id' => $connection->id, 'external_id' => $field['external_id']],
                    [
                        'custom_field_entity_id' => $entityId,
                        'name' => $field['name'],
                        'field_key' => $field['field_key'],
                        'field_type' => $field['field_type'],
                    ],
                );
            }
        }

        $personIds = [];
        foreach ($driver->fetchPersons($token) as $person) {
            $personIds[$person['external_id']] = CrmPerson::updateOrCreate(
                ['crm_connection_id' => $connection->id, 'external_id' => $person['external_id']],
                ['name' => $person['name'], 'email' => $person['email'], 'phone' => $person['phone']],
            )->id;
        }

        $statusIds = DealStatus::pluck('id', 'slug')->all();
        foreach ($driver->fetchDeals($token) as $deal) {
            Deal::updateOrCreate(
                ['crm_connection_id' => $connection->id, 'external_id' => $deal['external_id']],
                [
                    'title' => $deal['title'],
                    'value' => $deal['value'],
                    'pipeline_id' => $this->mapId($pipelineIds, $deal['pipeline_external_id']),
                    'pipeline_stage_id' => $this->mapId($stageIds, $deal['stage_external_id']),
                    'crm_person_id' => $this->mapId($personIds, $deal['person_external_id']),
                    'deal_status_id' => $deal['status'] !== null ? ($statusIds[$deal['status']] ?? null) : null,
                ],
            );
        }
    }

    /**
     * Resolve a local id from an external-id map, or null when absent.
     *
     * @param  array<string, int>  $map
     */
    private function mapId(array $map, ?string $externalId): ?int
    {
        return $externalId !== null ? ($map[$externalId] ?? null) : null;
    }

    /**
     * Per-entity counts straight from the database, so they reflect exactly
     * what was imported (including partial data after a failure).
     *
     * @return array{pipelines_count: int, custom_fields_count: int, persons_count: int, deals_count: int}
     */
    private function counts(CrmConnection $connection): array
    {
        return [
            'pipelines_count' => $connection->pipelines()->count(),
            'custom_fields_count' => $connection->customFields()->count(),
            'persons_count' => $connection->crmPersons()->count(),
            'deals_count' => $connection->deals()->count(),
        ];
    }
}
