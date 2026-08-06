<?php

// DEPRECATED (Task 3.2): migrations đã move lên database/migrations/ gốc (Modular Monolith refactor).
// Giữ file để không phá lịch sử migration cũ. KHÔNG dùng cho DB mới.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discipline_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained('disciplines')->onDelete('cascade');
            $table->enum('action_type', ['warning', 'parent_meeting', 'detention', 'suspension', 'expulsion']);
            $table->text('action_description');
            $table->foreignId('executed_by_user_id')->nullable()->onDelete('set null');
            $table->timestamp('executed_at')->nullable();
            $table->enum('completion_status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();

            // Indexes
            $table->index('discipline_id');
            $table->index(['action_type', 'completion_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discipline_actions');
    }
};
