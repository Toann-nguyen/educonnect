<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // nullable + SET NULL: nếu user bị xóa vẫn giữ log
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Action constants
            $table->string('action', 50);

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Dữ liệu bổ sung: { session_id, device, target_user_id, ... }
            $table->json('metadata')->nullable();

            // Chỉ ghi, không cập nhật → chỉ cần created_at
            $table->timestamp('created_at')->useCurrent();

            // Query log của 1 user theo thời gian
            $table->index(['user_id', 'created_at']);
            // Filter theo loại action
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
