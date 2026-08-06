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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->onDelete('cascade');
            $table->tinyInteger('day_of_week')->comment('2: Monday, 3: Tuesday...');
            $table->tinyInteger('period')->comment('Tiết học số mấy');
            $table->string('room')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};