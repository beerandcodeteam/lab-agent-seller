<?php

namespace App\Services\Ai;

use App\Models\FileIndexingStatus;
use App\Models\User;
use App\Models\VectorStore;
use App\Models\VectorStoreFile;
use App\Services\Ai\Exceptions\VectorStoreOperationException;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Stores;
use Throwable;

/**
 * Business logic for the company knowledge bases (vector stores): every OpenAI
 * call, the creation rollback and the aggregate indexing-status mapping live
 * here (fat service), so the Livewire component only validates, authorizes and
 * delegates (thin component).
 *
 * Persistence never precedes remote confirmation (RNF-02): a local row is
 * created only after the OpenAI id is known, and deleted only after the remote
 * deletion is confirmed or proven idempotent (RNF-05).
 */
class VectorStoreService
{
    /**
     * The aggregate indexing states a store can report (RF-07). Derived from
     * the store-level counts + `ready`; the SDK exposes no per-file status.
     */
    public const string StateProcessing = 'processing';

    public const string StateReady = 'ready';

    public const string StateFailed = 'failed';

    private const string CreateFailureMessage = 'Não foi possível criar a base de conhecimento. Tente novamente em instantes.';

    private const string UploadFailureMessage = 'Não foi possível enviar o arquivo para a base de conhecimento. Tente novamente em instantes.';

    private const string RemoteDeletionFailureMessage = 'Não foi possível concluir a remoção na OpenAI. Nada foi apagado; tente novamente em instantes.';

    /**
     * Create a vector store on OpenAI and persist it locally ONLY after the
     * remote id is confirmed (RF-01/RNF-02). Any failure (exception or missing
     * id) leaves no local record and surfaces a PT-BR error (RF-02).
     */
    public function createForCompany(User $company, string $name, string $description): VectorStore
    {
        try {
            $store = Stores::create($name, $description);
        } catch (Throwable $exception) {
            throw new VectorStoreOperationException(self::CreateFailureMessage, previous: $exception);
        }

        if (blank($store->id)) {
            throw new VectorStoreOperationException(self::CreateFailureMessage);
        }

        return $company->vectorStores()->create([
            'openai_vector_store_id' => $store->id,
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * Rename/redescribe a store LOCALLY only (RF-04): `Laravel\Ai\Stores` has no
     * update and `Store` does not expose a description, so name+description are a
     * local catalog used to build the agent context. No OpenAI call is made and
     * the OpenAI id is preserved.
     */
    public function rename(VectorStore $store, string $name, string $description): void
    {
        $store->update([
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * Upload a file to the store's OpenAI vector store and persist BOTH ids
     * returned by `AddedDocumentResponse` (RF-06): the document id (used as the
     * removal handle) and the underlying File object id.
     */
    public function addFile(VectorStore $store, UploadedFile $file): VectorStoreFile
    {
        try {
            $document = Stores::get($store->openai_vector_store_id)->add($file);
        } catch (Throwable $exception) {
            throw new VectorStoreOperationException(self::UploadFailureMessage, previous: $exception);
        }

        return $store->files()->create([
            'openai_document_id' => $document->id,
            'openai_file_id' => $document->fileId,
            'filename' => $file->getClientOriginalName(),
            'file_indexing_status_id' => FileIndexingStatus::slug('pending')?->id,
        ]);
    }

    /**
     * Remove a file from its store on OpenAI, deleting the underlying File
     * object too (`deleteFile: true`, no orphan), then drop the local row only
     * after the remote step is confirmed/idempotent (RF-08/RNF-05).
     */
    public function removeFile(VectorStoreFile $file): void
    {
        $store = $file->vectorStore;

        $this->handleRemoteDeletion(
            fn (): bool => Stores::get($store->openai_vector_store_id)
                ->remove($file->openai_document_id, deleteFile: true),
        );

        $file->delete();
    }

    /**
     * Delete a store, IN THIS ORDER (RF-05): (1) remove every tracked document
     * with `deleteFile: true` so no File object is left orphaned on OpenAI, then
     * (2) delete the vector store on OpenAI, then (3) drop the local file and
     * store rows. Every remote step follows RNF-05 (preserve + idempotent-404).
     */
    public function deleteStore(VectorStore $store): void
    {
        $store->loadMissing('files');

        foreach ($store->files as $file) {
            $this->handleRemoteDeletion(
                fn (): bool => Stores::get($store->openai_vector_store_id)
                    ->remove($file->openai_document_id, deleteFile: true),
            );
        }

        $this->handleRemoteDeletion(
            fn (): bool => Stores::delete($store->openai_vector_store_id),
        );

        $store->files()->delete();
        $store->delete();
    }

    /**
     * Read the aggregate indexing state of a store (RF-07) from the store-level
     * counts + `ready`. The SDK exposes no per-file status, so this returns the
     * store aggregate only; each file row inherits it in the UI.
     *
     * @return array{state: string, completed: int, pending: int, failed: int, ready: bool}
     */
    public function indexingState(VectorStore $store): array
    {
        $remote = Stores::get($store->openai_vector_store_id);
        $counts = $remote->fileCounts;

        $state = match (true) {
            $counts->failed > 0 => self::StateFailed,
            ! $remote->ready => self::StateProcessing,
            default => self::StateReady,
        };

        return [
            'state' => $state,
            'completed' => $counts->completed,
            'pending' => $counts->pending,
            'failed' => $counts->failed,
            'ready' => $remote->ready,
        ];
    }

    /**
     * Run a remote deletion step under the resilience rule (RNF-05): a 404/"já
     * removido" is a successful idempotent no-op (caller proceeds to delete the
     * local row); any real failure (5xx/network/timeout) is rethrown as a PT-BR
     * operation exception, so the caller preserves the local row for retry.
     *
     * @param  Closure(): bool  $remoteCall
     */
    private function handleRemoteDeletion(Closure $remoteCall): void
    {
        try {
            $remoteCall();
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return;
            }

            throw new VectorStoreOperationException(self::RemoteDeletionFailureMessage, previous: $exception);
        } catch (Throwable $exception) {
            throw new VectorStoreOperationException(self::RemoteDeletionFailureMessage, previous: $exception);
        }
    }
}
