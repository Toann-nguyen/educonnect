<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('identity')->create('event_store', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedInteger('version');
            $table->string('event_type', 100);
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at', 3);
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(['aggregate_type', 'aggregate_id', 'version'], 'uq_identity_event_agg_version');
            $table->index(['event_type', 'occurred_at'], 'idx_identity_event_type_occurred');
        });
    }

    public function down(): void
    {
        Schema::connection('identity')->dropIfExists('event_store');
    }
};
