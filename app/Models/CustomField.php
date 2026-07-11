<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crm_connection_id
 * @property int $custom_field_entity_id
 * @property string $external_id
 * @property string $name
 * @property string|null $field_key
 * @property string|null $field_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'crm_connection_id',
    'custom_field_entity_id',
    'external_id',
    'name',
    'field_key',
    'field_type',
])]
class CustomField extends Model
{
    /**
     * @return BelongsTo<CrmConnection, $this>
     */
    public function crmConnection(): BelongsTo
    {
        return $this->belongsTo(CrmConnection::class);
    }

    /**
     * @return BelongsTo<CustomFieldEntity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(CustomFieldEntity::class, 'custom_field_entity_id');
    }
}
