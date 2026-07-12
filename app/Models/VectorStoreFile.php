<?php

namespace App\Models;

use Database\Factories\VectorStoreFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vector_store_id
 * @property string $openai_document_id
 * @property string $openai_file_id
 * @property string $filename
 * @property int|null $file_indexing_status_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'vector_store_id',
    'openai_document_id',
    'openai_file_id',
    'filename',
    'file_indexing_status_id',
])]
class VectorStoreFile extends Model
{
    /** @use HasFactory<VectorStoreFileFactory> */
    use HasFactory;

    /**
     * The vector store this file belongs to.
     *
     * @return BelongsTo<VectorStore, $this>
     */
    public function vectorStore(): BelongsTo
    {
        return $this->belongsTo(VectorStore::class);
    }

    /**
     * The coarse indexing status derived from the store aggregate.
     *
     * @return BelongsTo<FileIndexingStatus, $this>
     */
    public function indexingStatus(): BelongsTo
    {
        return $this->belongsTo(FileIndexingStatus::class, 'file_indexing_status_id');
    }
}
