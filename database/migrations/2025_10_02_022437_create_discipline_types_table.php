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
        /**
         * bang nay thuc luu cac loai vi phu va trang
         */
        Schema::create('discipline_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // LATE, FIGHT, CHEAT, etc.
            $table->string('name'); // Đi trễ, Đánh nhau, Gian lận...
            // cap do vi pham
            $table->enum('severity_level', ['light', 'medium', 'serious', 'very_serious'])->default('light');
            $table->integer('default_penalty_points')->default(1); // Điểm trừ mặc định
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discipline_types');
    }
};
