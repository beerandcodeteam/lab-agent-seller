<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_status_id')->constrained();
            $table->integer('pipelines_count')->default(0);
            $table->integer('custom_fields_count')->default(0);
            $table->integer('persons_count')->default(0);
            $table->integer('deals_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_scans');
    }
};
