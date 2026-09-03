<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbox for Identity → user_events (RabbitMQ topic)
     * On identity connection; guarantees publish after commit.
     */
    public function up(): void
    {
        Schema::connection('identity')->create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 50)->default('user');
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type', 50); // user.created | user.updated | user.deleted
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_retry_at']);
            $table->index('aggregate_id');
        });
    }

    public function down(): void
    {
        Schema::connection('identity')->dropIfExists('outbox_events');
    }
};
