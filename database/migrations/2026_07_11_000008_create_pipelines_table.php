<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->timestamps();

            $table->unique(['crm_connection_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
