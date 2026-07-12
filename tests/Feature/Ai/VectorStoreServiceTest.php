<?php

use App\Models\FileIndexingStatus;
use App\Models\User;
use App\Models\VectorStore;
use App\Models\VectorStoreFile;
use App\Services\Ai\Exceptions\VectorStoreOperationException;
use App\Services\Ai\VectorStoreService;
use Database\Seeders\LookupSeeder;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;

function service(): VectorStoreService
{
    return app(VectorStoreService::class);
}

/**
 * A minimal OpenAI vector store GET payload the real gateway can marshal.
 *
 * @return array<string, mixed>
 */
function storePayload(string $id, string $status = 'completed', int $completed = 0, int $inProgress = 0, int $failed = 0): array
{
    return [
        'id' => $id,
        'name' => 'fake-store',
        'status' => $status,
        'file_counts' => [
            'completed' => $completed,
            'in_progress' => $inProgress,
            'failed' => $failed,
        ],
    ];
}

it('creates the store on OpenAI and persists the returned id', function () {
    Stores::fake();

    $company = User::factory()->create();

    $store = service()->createForCompany($company, 'Catálogo', 'Documentos de produto');

    expect($store->openai_vector_store_id)->toBe(Stores::fakeId('Catálogo'))
        ->and($store->user_id)->toBe($company->id)
        ->and($store->name)->toBe('Catálogo')
        ->and($store->description)->toBe('Documentos de produto');

    Stores::assertCreated('Catálogo');

    expect($company->vectorStores()->count())->toBe(1);
});

it('does not leave a local record when creation fails on OpenAI', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/vector_stores' => Http::response(['error' => ['message' => 'boom']], 500),
    ]);

    $company = User::factory()->create();

    expect(fn () => service()->createForCompany($company, 'Catálogo', 'Documentos'))
        ->toThrow(VectorStoreOperationException::class);

    expect($company->vectorStores()->count())->toBe(0);
});

it('renames locally without any OpenAI create or delete call', function () {
    Stores::fake();

    $store = VectorStore::factory()->create([
        'openai_vector_store_id' => 'vs_keep',
        'name' => 'Antigo',
        'description' => 'Descrição antiga',
    ]);

    service()->rename($store, 'Novo nome', 'Nova descrição');

    expect($store->fresh())
        ->name->toBe('Novo nome')
        ->description->toBe('Nova descrição')
        ->openai_vector_store_id->toBe('vs_keep');

    Stores::assertNothingCreated();
    Stores::assertNothingDeleted();
});

it('uploads a file and persists both the document and file ids', function () {
    $this->seed(LookupSeeder::class);
    Stores::fake();

    $store = VectorStore::factory()->create(['openai_vector_store_id' => 'vs_up']);

    $file = service()->addFile($store, File::create('manual.pdf', 10));

    expect($file->openai_document_id)->not->toBeEmpty()
        ->and($file->openai_file_id)->not->toBeEmpty()
        ->and($file->filename)->toBe('manual.pdf')
        ->and($file->vector_store_id)->toBe($store->id)
        ->and($file->file_indexing_status_id)->toBe(FileIndexingStatus::slug('pending')->id);

    expect($store->files()->count())->toBe(1);
});

it('removes a file with deleteFile true and drops the local row', function () {
    Stores::fake();

    $store = VectorStore::factory()->create(['openai_vector_store_id' => 'vs_rm']);
    $file = VectorStoreFile::factory()->for($store)->create(['openai_document_id' => 'doc_rm']);

    service()->removeFile($file);

    Files::assertDeleted('doc_rm');
    expect(VectorStoreFile::whereKey($file->id)->exists())->toBeFalse();
});

it('deletes a store removing every file first then the store and the local rows', function () {
    Stores::fake();

    $store = VectorStore::factory()->create(['openai_vector_store_id' => 'vs_del']);
    $fileA = VectorStoreFile::factory()->for($store)->create(['openai_document_id' => 'doc_a']);
    $fileB = VectorStoreFile::factory()->for($store)->create(['openai_document_id' => 'doc_b']);

    service()->deleteStore($store);

    Files::assertDeleted('doc_a');
    Files::assertDeleted('doc_b');
    Stores::assertDeleted('vs_del');

    expect(VectorStore::whereKey($store->id)->exists())->toBeFalse()
        ->and(VectorStoreFile::whereKey($fileA->id)->exists())->toBeFalse()
        ->and(VectorStoreFile::whereKey($fileB->id)->exists())->toBeFalse();
});

it('treats a 404 during file removal as an idempotent success and drops the local row', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/vector_stores/vs_x/files/doc_x' => Http::response(['error' => ['message' => 'not found']], 404),
        '*/vector_stores/vs_x' => Http::response(storePayload('vs_x'), 200),
    ]);

    $store = VectorStore::factory()->create(['openai_vector_store_id' => 'vs_x']);
    $file = VectorStoreFile::factory()->for($store)->create(['openai_document_id' => 'doc_x']);

    service()->removeFile($file);

    expect(VectorStoreFile::whereKey($file->id)->exists())->toBeFalse();
});

it('preserves the local row and throws when file removal fails remotely', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/vector_stores/vs_y/files/doc_y' => Http::response(['error' => ['message' => 'boom']], 500),
        '*/vector_stores/vs_y' => Http::response(storePayload('vs_y'), 200),
    ]);

    $store = VectorStore::factory()->create(['openai_vector_store_id' => 'vs_y']);
    $file = VectorStoreFile::factory()->for($store)->create(['openai_document_id' => 'doc_y']);

    expect(fn () => service()->removeFile($file))
        ->toThrow(VectorStoreOperationException::class);

    expect(VectorStoreFile::whereKey($file->id)->exists())->toBeTrue();
});

it('derives the aggregate indexing state from the store counts and ready flag', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/vector_stores/vs_ready' => Http::response(storePayload('vs_ready', 'completed', completed: 2), 200),
        '*/vector_stores/vs_proc' => Http::response(storePayload('vs_proc', 'in_progress', completed: 1, inProgress: 1), 200),
        '*/vector_stores/vs_fail' => Http::response(storePayload('vs_fail', 'completed', completed: 1, failed: 1), 200),
    ]);

    $ready = service()->indexingState(VectorStore::factory()->create(['openai_vector_store_id' => 'vs_ready']));
    expect($ready['state'])->toBe(VectorStoreService::StateReady)
        ->and($ready['ready'])->toBeTrue()
        ->and($ready['completed'])->toBe(2);

    $processing = service()->indexingState(VectorStore::factory()->create(['openai_vector_store_id' => 'vs_proc']));
    expect($processing['state'])->toBe(VectorStoreService::StateProcessing)
        ->and($processing['pending'])->toBe(1);

    $failed = service()->indexingState(VectorStore::factory()->create(['openai_vector_store_id' => 'vs_fail']));
    expect($failed['state'])->toBe(VectorStoreService::StateFailed)
        ->and($failed['failed'])->toBe(1);
});
