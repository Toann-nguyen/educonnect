<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('finance')->create('event_snapshot', function (Blueprint $table) {
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedInteger('version');
            $table->json('snapshot_payload');
            $table->timestamp('created_at', 3)->useCurrent();
            $table->primary(['aggregate_type', 'aggregate_id', 'version'], 'pk_finance_event_snapshot');
            $table->index(['aggregate_type', 'aggregate_id', 'version'], 'idx_finance_snapshot_latest');
        });
    }

    public function down(): void
    {
        Schema::connection('finance')->dropIfExists('event_snapshot');
    }
};
