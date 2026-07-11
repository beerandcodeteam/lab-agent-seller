<?php

namespace App\Models;

use Database\Factories\CrmConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $crm_provider_id
 * @property string $api_token
 * @property Carbon|null $last_validated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'crm_provider_id', 'api_token', 'last_validated_at'])]
class CrmConnection extends Model
{
    /** @use HasFactory<CrmConnectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'last_validated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CrmProvider, $this>
     */
    public function crmProvider(): BelongsTo
    {
        return $this->belongsTo(CrmProvider::class);
    }

    /**
     * @return HasMany<CrmScan, $this>
     */
    public function crmScans(): HasMany
    {
        return $this->hasMany(CrmScan::class);
    }

    /**
     * @return HasMany<Pipeline, $this>
     */
    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }

    /**
     * @return HasMany<CustomField, $this>
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    /**
     * @return HasMany<CrmPerson, $this>
     */
    public function crmPersons(): HasMany
    {
        return $this->hasMany(CrmPerson::class);
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
