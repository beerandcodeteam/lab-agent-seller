<?php

namespace App\Livewire\Crm;

use App\Jobs\ScanCrmConnection;
use App\Models\CrmConnection;
use App\Models\CrmScan;
use App\Models\CustomField;
use App\Models\Message;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * CRM scan card on the company panel. Renders the scan state machine as one of
 * four UI states (pending / syncing / completed / failed), polls itself while a
 * scan is in progress, exposes an on-demand re-scan, and summarises the imported
 * data alongside the honest conversation/message volume for the company.
 */
class ScanCard extends Component
{
    /**
     * Queue a fresh scan, unless one is already pending/running.
     */
    public function rescan(): void
    {
        $connection = $this->connection();

        if (! $connection || $this->isScanning($this->latestScan($connection))) {
            return;
        }

        ScanCrmConnection::enqueue($connection);
    }

    /**
     * The authenticated company's CRM connection, if any.
     */
    public function connection(): ?CrmConnection
    {
        return auth()->user()?->crmConnection()->with('crmProvider')->first();
    }

    /**
     * The most recent scan for a connection.
     */
    private function latestScan(CrmConnection $connection): ?CrmScan
    {
        return $connection->crmScans()->with('scanStatus')->latest('id')->first();
    }

    /**
     * A scan is "in progress" while pending or running.
     */
    private function isScanning(?CrmScan $scan): bool
    {
        return in_array($scan?->scanStatus?->slug, ['pending', 'running'], true);
    }

    /**
     * Map the scan status slug to the four card UI states used by the design.
     */
    private function uiState(?CrmScan $scan): string
    {
        return match ($scan?->scanStatus?->slug) {
            'running' => 'syncing',
            'success' => 'completed',
            'failed' => 'failed',
            default => 'pending',
        };
    }

    public function render()
    {
        $connection = $this->connection();
        $scan = $connection ? $this->latestScan($connection) : null;
        $state = $this->uiState($scan);

        $company = auth()->user();

        return view('livewire.crm.scan-card', [
            'connection' => $connection,
            'scan' => $scan,
            'state' => $state,
            'scanning' => $this->isScanning($scan),
            'counts' => $connection ? $this->counts($connection) : null,
            'conversationsCount' => $company ? $company->conversations()->count() : 0,
            'messagesCount' => $company
                ? Message::whereIn('conversation_id', $company->conversations()->select('id'))->count()
                : 0,
        ]);
    }

    /**
     * Live per-entity counts from the database (source of truth for the summary).
     *
     * @return array<string, int>
     */
    private function counts(CrmConnection $connection): array
    {
        /** @var Collection<int, CustomField> $customFields */
        $customFields = $connection->customFields()->with('entity')->get();

        return [
            'pipelines' => $connection->pipelines()->count(),
            'stages' => $connection->pipelines()->withCount('stages')->get()->sum('stages_count'),
            'customFieldsPerson' => $customFields->filter(fn ($field) => $field->entity?->slug === 'person')->count(),
            'customFieldsDeal' => $customFields->filter(fn ($field) => $field->entity?->slug === 'deal')->count(),
            'persons' => $connection->crmPersons()->count(),
            'deals' => $connection->deals()->count(),
        ];
    }
}
