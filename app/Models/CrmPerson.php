<?php

namespace App\Models;

use Database\Factories\CrmPersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crm_connection_id
 * @property string $external_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['crm_connection_id', 'external_id', 'name', 'email', 'phone'])]
class CrmPerson extends Model
{
    /** @use HasFactory<CrmPersonFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'crm_persons';

    /**
     * @return BelongsTo<CrmConnection, $this>
     */
    public function crmConnection(): BelongsTo
    {
        return $this->belongsTo(CrmConnection::class);
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
