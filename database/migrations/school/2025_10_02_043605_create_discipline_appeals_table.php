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
        Schema::create('discipline_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained('disciplines')->onDelete('cascade');
            $table->foreignId('appellant_user_id')->onDelete('cascade');
            $table->enum('appellant_type', ['student', 'parent']);
            $table->text('appeal_reason');
            $table->json('evidence')->nullable()->comment('Supporting documents/images');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_response')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['discipline_id', 'status']);
            $table->index('appellant_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discipline_appeals');
    }
};
