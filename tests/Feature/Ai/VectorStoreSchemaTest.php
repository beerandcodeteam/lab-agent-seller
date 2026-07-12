<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the three vector store tables with expected columns', function () {
    expect(Schema::hasColumns('file_indexing_statuses', ['id', 'name', 'slug', 'created_at', 'updated_at']))->toBeTrue();

    expect(Schema::hasColumns('vector_stores', [
        'id', 'user_id', 'openai_vector_store_id', 'name', 'description', 'created_at', 'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('vector_store_files', [
        'id', 'vector_store_id', 'openai_document_id', 'openai_file_id', 'filename',
        'file_indexing_status_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('enforces a unique openai_vector_store_id', function () {
    $company = User::factory()->create();

    DB::table('vector_stores')->insert([
        'user_id' => $company->id,
        'openai_vector_store_id' => 'vs_duplicate',
        'name' => 'Primeira base',
        'description' => 'Descrição',
    ]);

    DB::table('vector_stores')->insert([
        'user_id' => $company->id,
        'openai_vector_store_id' => 'vs_duplicate',
        'name' => 'Segunda base',
        'description' => 'Descrição',
    ]);
})->throws(QueryException::class);

it('cascades deletes from user to stores and files across two levels', function () {
    $company = User::factory()->create();

    $storeId = DB::table('vector_stores')->insertGetId([
        'user_id' => $company->id,
        'openai_vector_store_id' => 'vs_cascade',
        'name' => 'Base de conhecimento',
        'description' => 'Descrição',
    ]);

    DB::table('vector_store_files')->insert([
        'vector_store_id' => $storeId,
        'openai_document_id' => 'doc_1',
        'openai_file_id' => 'file_1',
        'filename' => 'manual.pdf',
        'file_indexing_status_id' => null,
    ]);

    expect(DB::table('vector_stores')->count())->toBe(1)
        ->and(DB::table('vector_store_files')->count())->toBe(1);

    $company->delete();

    expect(DB::table('vector_stores')->count())->toBe(0)
        ->and(DB::table('vector_store_files')->count())->toBe(0);
});
