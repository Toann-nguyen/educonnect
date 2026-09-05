<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('identity')->table('outbox_events', function (Blueprint $table) {
            if (!Schema::connection('identity')->hasColumn('outbox_events', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->after('payload')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('identity')->table('outbox_events', function (Blueprint $table) {
            if (Schema::connection('identity')->hasColumn('outbox_events', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
