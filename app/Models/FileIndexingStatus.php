<?php

namespace App\Models;

use App\Models\Concerns\IsLookup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug'])]
class FileIndexingStatus extends Model
{
    use IsLookup;

    /**
     * @return HasMany<VectorStoreFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(VectorStoreFile::class);
    }
}
