<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('email_verifications');

        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();

            // foreignId → bigint unsigned, khớp với users.id (id())
            // unique → mỗi user chỉ có 1 bản ghi → updateOrCreate an toàn
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->cascadeOnDelete();

            // SHA256(raw_token) — 64 hex chars
            // UNIQUE đã đủ, không cần thêm index riêng
            $table->char('token_hash', 64)->unique();

            // NOW() + 24 giờ
            $table->timestamp('expires_at');

            // null = chưa verify | datetime = đã verify
            // Dùng verified_at thay vì used_at cho đúng semantic
            $table->timestamp('verified_at')->nullable();

            // Chỉ cần created_at, không cần updated_at
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Khôi phục về cấu trúc cũ của bạn
        Schema::dropIfExists('email_verifications');

        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index('token_hash');
        });
    }
};
