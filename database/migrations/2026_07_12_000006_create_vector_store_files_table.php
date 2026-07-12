<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vector_store_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vector_store_id')->constrained()->cascadeOnDelete();
            $table->string('openai_document_id')->index();
            $table->string('openai_file_id');
            $table->string('filename');
            $table->foreignId('file_indexing_status_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vector_store_files');
    }
};
