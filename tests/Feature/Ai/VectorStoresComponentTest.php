<?php

use App\Livewire\Agent\VectorStores;
use App\Models\User;
use App\Models\VectorStore;
use App\Models\VectorStoreFile;
use Database\Seeders\LookupSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Stores;
use Livewire\Livewire;

/**
 * A minimal OpenAI vector store GET payload the real gateway can marshal.
 *
 * @return array<string, mixed>
 */
function storeGetPayload(string $id, string $status = 'completed', int $completed = 0, int $inProgress = 0, int $failed = 0): array
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

it('lists only the authenticated company stores and shows an empty state otherwise', function () {
    $company = User::factory()->create();
    VectorStore::factory()->for($company)->create(['name' => 'Base Alpha']);

    $other = User::factory()->create();
    VectorStore::factory()->for($other)->create(['name' => 'Base Beta']);

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->assertSee('Base Alpha')
        ->assertDontSee('Base Beta');

    Livewire::actingAs(User::factory()->create())
        ->test(VectorStores::class)
        ->assertSee('Nenhuma base de conhecimento');
});

it('blocks creation with PT-BR validation when name or description are empty', function () {
    $company = User::factory()->create();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->set('name', '')
        ->set('description', '')
        ->call('save')
        ->assertHasErrors(['name', 'description'])
        ->assertSee('Informe o nome da base de conhecimento.')
        ->assertSee('Informe a descrição da base de conhecimento.');

    expect($company->vectorStores()->count())->toBe(0);
});

it('returns 403 when operating on a store owned by another company', function () {
    $company = User::factory()->create();
    $foreignStore = VectorStore::factory()->for(User::factory())->create();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->call('editStore', $foreignStore->id)
        ->assertForbidden();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->call('deleteStore', $foreignStore->id)
        ->assertForbidden();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->set('upload', UploadedFile::fake()->create('manual.pdf', 10))
        ->call('uploadFile', $foreignStore->id)
        ->assertForbidden();

    $foreignFile = VectorStoreFile::factory()->for($foreignStore)->create();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->call('removeFile', $foreignFile->id)
        ->assertForbidden();
});

it('rejects an unsupported file type with a PT-BR message', function () {
    $company = User::factory()->create();
    $store = VectorStore::factory()->for($company)->create();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->set('upload', UploadedFile::fake()->create('malware.exe', 10))
        ->call('uploadFile', $store->id)
        ->assertHasErrors(['upload'])
        ->assertSee('Tipo de arquivo não suportado pelo File Search da OpenAI.');

    expect($store->files()->count())->toBe(0);
});

it('rejects a file larger than 512 MB with a PT-BR message', function () {
    $company = User::factory()->create();
    $store = VectorStore::factory()->for($company)->create();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->set('upload', UploadedFile::fake()->create('huge.pdf', 524_289))
        ->call('uploadFile', $store->id)
        ->assertHasErrors(['upload'])
        ->assertSee('O arquivo excede o limite de 512 MB.');

    expect($store->files()->count())->toBe(0);
});

it('shows a friendly PT-BR banner without technical detail when OpenAI fails', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/vector_stores' => Http::response(['error' => ['message' => 'boom']], 500),
    ]);

    $company = User::factory()->create();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->set('name', 'Catálogo')
        ->set('description', 'Documentos')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('errorMessage', 'Não foi possível criar a base de conhecimento. Tente novamente em instantes.')
        ->assertSee('Não foi possível criar a base de conhecimento.')
        ->assertDontSee('VectorStoreOperationException')
        ->assertDontSee('boom');

    expect($company->vectorStores()->count())->toBe(0);
});

it('reflects the aggregate indexing state in the store badge', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/vector_stores/vs_badge' => Http::response(storeGetPayload('vs_badge', 'completed', completed: 1), 200),
    ]);

    $company = User::factory()->create();
    $store = VectorStore::factory()->for($company)->create(['openai_vector_store_id' => 'vs_badge']);
    VectorStoreFile::factory()->for($store)->create();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->assertSee('pronto');
});

it('creates a store and persists a file through the service delegation', function () {
    $this->seed(LookupSeeder::class);
    Stores::fake();

    $company = User::factory()->create();

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->set('name', 'Manual')
        ->set('description', 'Documentos de produto')
        ->call('save')
        ->assertHasNoErrors();

    $store = $company->vectorStores()->sole();
    expect($store->name)->toBe('Manual');

    Livewire::actingAs($company)
        ->test(VectorStores::class)
        ->set('upload', UploadedFile::fake()->create('manual.pdf', 10))
        ->call('uploadFile', $store->id)
        ->assertHasNoErrors();

    expect($store->files()->count())->toBe(1);
});
