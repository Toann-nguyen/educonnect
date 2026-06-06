<?php

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
        Schema::create('student_conduct_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->tinyInteger('semester')->comment('1 or 2');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');

            $table->integer('total_merit_points')->default(0); // Thêm cột điểm cộng
            $table->integer('total_penalty_points')->default(0);
            $table->integer('final_score')->default(100); // Thêm cột điểm cuối cùng

            $table->string('conduct_grade', 50)->nullable(); // Nên dùng string thay vì enum

            $table->text('teacher_comment')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // SỬA LẠI DÒNG NÀY
            // Đặt một cái tên ngắn gọn cho ràng buộc unique
            $table->unique(
                ['student_id', 'semester', 'academic_year_id'],
                'student_semester_year_unique' // <-- Tên tùy chỉnh, ngắn gọn
            );

            $table->index('conduct_grade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_conduct_scores');
    }
};
