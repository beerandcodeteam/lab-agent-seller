<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pipeline_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crm_person_id')->nullable()->constrained('crm_persons')->nullOnDelete();
            $table->foreignId('deal_status_id')->nullable()->constrained();
            $table->string('external_id');
            $table->string('title');
            $table->decimal('value', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['crm_connection_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
