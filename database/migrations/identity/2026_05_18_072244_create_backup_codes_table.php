<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Hash::make($code, ['rounds' => 12]) — bcrypt, KHÔNG phải SHA256
            // Backup code chỉ 8 ký tự → SHA256 dễ bị rainbow table
            // varchar vì bcrypt output dài hơn 64 chars (~60 chars)
            $table->string('code_hash');

            // null = còn dùng được | datetime = đã dùng rồi
            // Không xóa record để có thể audit
            $table->timestamp('used_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Load 10 codes của 1 user nhanh
            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_codes');
    }
};
