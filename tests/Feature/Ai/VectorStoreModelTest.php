<?php

use App\Models\FileIndexingStatus;
use App\Models\User;
use App\Models\VectorStore;
use App\Models\VectorStoreFile;

it('resolves user->vectorStores scoped to the owner', function () {
    $company = User::factory()->create();
    $other = User::factory()->create();

    $store = VectorStore::factory()->for($company)->create();
    VectorStore::factory()->for($other)->create();

    expect($company->vectorStores)->toHaveCount(1)
        ->and($company->vectorStores->first()->id)->toBe($store->id);
});

it('traverses store->files and file->indexingStatus', function () {
    $store = VectorStore::factory()->create();
    $file = VectorStoreFile::factory()->for($store)->completed()->create();

    expect($store->files->pluck('id'))->toContain($file->id)
        ->and($file->vectorStore->id)->toBe($store->id)
        ->and($file->indexingStatus)->not->toBeNull()
        ->and($file->indexingStatus->slug)->toBe('completed');
});

it('resolves indexingStatus->files back-relation', function () {
    $store = VectorStore::factory()->create();
    $file = VectorStoreFile::factory()->for($store)->failed()->create();

    $status = FileIndexingStatus::slug('failed');

    expect($status)->not->toBeNull()
        ->and($status->files->pluck('id'))->toContain($file->id);
});

it('cascades deletes from user through stores to files', function () {
    $company = User::factory()->create();
    $store = VectorStore::factory()->for($company)->create();
    VectorStoreFile::factory()->for($store)->create();

    expect(VectorStore::count())->toBe(1)
        ->and(VectorStoreFile::count())->toBe(1);

    $company->delete();

    expect(VectorStore::count())->toBe(0)
        ->and(VectorStoreFile::count())->toBe(0);
});

it('cascades deletes from store to its files', function () {
    $store = VectorStore::factory()->create();
    VectorStoreFile::factory()->for($store)->create();

    $store->delete();

    expect(VectorStoreFile::count())->toBe(0);
});
