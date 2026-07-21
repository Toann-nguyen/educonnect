<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // SHA256(raw_token) — 64 hex chars, không lưu plaintext
            $table->char('token_hash', 64)->unique();

            // Parse từ User-Agent: { device, browser, os, type }
            $table->json('device_info')->nullable();

            // hash(userAgent + ip) — group session theo thiết bị
            $table->string('device_fingerprint', 64)->nullable();

            $table->string('ip_address', 45)->nullable();

            // remember_me=true → 30 ngày | false → 7 ngày
            $table->timestamp('expires_at');

            // Cập nhật mỗi lần token được dùng để refresh
            $table->timestamp('last_used_at')->nullable();

            // null = active | datetime = đã revoke (logout / rotation)
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // Query active sessions của user: WHERE user_id=? AND revoked_at IS NULL
            $table->index(['user_id', 'revoked_at']);
            $table->index('device_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
