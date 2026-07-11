<?php

namespace App\Models;

use Database\Factories\CrmScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crm_connection_id
 * @property int $scan_status_id
 * @property int $pipelines_count
 * @property int $custom_fields_count
 * @property int $persons_count
 * @property int $deals_count
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'crm_connection_id',
    'scan_status_id',
    'pipelines_count',
    'custom_fields_count',
    'persons_count',
    'deals_count',
    'error_message',
    'started_at',
    'finished_at',
])]
class CrmScan extends Model
{
    /** @use HasFactory<CrmScanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CrmConnection, $this>
     */
    public function crmConnection(): BelongsTo
    {
        return $this->belongsTo(CrmConnection::class);
    }

    /**
     * @return BelongsTo<ScanStatus, $this>
     */
    public function scanStatus(): BelongsTo
    {
        return $this->belongsTo(ScanStatus::class);
    }
}
