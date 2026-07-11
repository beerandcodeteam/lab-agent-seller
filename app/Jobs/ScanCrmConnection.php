<?php

namespace App\Jobs;

use App\Models\CrmConnection;
use App\Models\ScanStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kicks off a CRM scan for a connection. Actual Pipedrive traversal (pipelines,
 * custom fields, persons, deals) is implemented in a later phase; here the job
 * records a pending scan so the connection has a scan lifecycle from the start.
 */
class ScanCrmConnection implements ShouldQueue
{
    use Queueable;

    public function __construct(public CrmConnection $crmConnection) {}

    public function handle(): void
    {
        $this->crmConnection->crmScans()->create([
            'scan_status_id' => ScanStatus::where('slug', 'pending')->value('id'),
        ]);
    }
}
