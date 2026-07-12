<?php

namespace App\Models;

use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crm_connection_id
 * @property int|null $pipeline_id
 * @property int|null $pipeline_stage_id
 * @property int|null $crm_person_id
 * @property int|null $deal_status_id
 * @property string $external_id
 * @property string $title
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'crm_connection_id',
    'pipeline_id',
    'pipeline_stage_id',
    'crm_person_id',
    'deal_status_id',
    'external_id',
    'title',
    'value',
])]
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
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
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class);
    }

    /**
     * @return BelongsTo<CrmPerson, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(CrmPerson::class, 'crm_person_id');
    }

    /**
     * @return BelongsTo<DealStatus, $this>
     */
    public function dealStatus(): BelongsTo
    {
        return $this->belongsTo(DealStatus::class);
    }
}
