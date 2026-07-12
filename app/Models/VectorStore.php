<?php

namespace App\Models;

use Database\Factories\VectorStoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $openai_vector_store_id
 * @property string $name
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'openai_vector_store_id',
    'name',
    'description',
])]
class VectorStore extends Model
{
    /** @use HasFactory<VectorStoreFactory> */
    use HasFactory;

    /**
     * The company (tenant) that owns this vector store.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Files uploaded to this vector store.
     *
     * @return HasMany<VectorStoreFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(VectorStoreFile::class);
    }
}
