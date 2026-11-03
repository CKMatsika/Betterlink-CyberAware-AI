<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Infrastructure-only queue: tenant and outbox identifiers, no recipient data.
        // The worker establishes tenant RLS scope before opening the outbox record.
        Schema::create('simulation_dispatch_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id')->index();
            $table->uuid('outbox_id');
            $table->timestampTz('available_at')->index();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['organisation_id', 'outbox_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_dispatch_queue');
    }
};
