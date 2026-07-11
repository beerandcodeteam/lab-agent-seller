<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_entity_id')->constrained();
            $table->string('external_id');
            $table->string('name');
            $table->string('field_key')->nullable();
            $table->string('field_type')->nullable();
            $table->timestamps();

            $table->unique(['crm_connection_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
