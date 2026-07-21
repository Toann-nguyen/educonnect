<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop bảng mặc định của Laravel nếu tồn tại
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();

            // Không dùng FK vì email có thể bị đổi sau khi tạo token
            // Index để lookup nhanh theo email
            $table->string('email')->index();

            // SHA256(raw_token) — 64 hex chars
            $table->char('token_hash', 64)->unique();

            // NOW() + 1 giờ
            $table->timestamp('expires_at');

            // null = chưa dùng | datetime = đã reset xong → chặn reuse
            $table->timestamp('used_at')->nullable();

            // Lưu IP người gửi yêu cầu (audit)
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
