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
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Mã loại phí, VD: TUITION, BUS, MEAL');
            $table->string('name')->comment('Tên hiển thị');
            $table->decimal('default_amount', 15, 2)->default(0)->comment('Số tiền mặc định');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Bảng trung gian để lưu nhiều loại phí trong 1 invoice
        Schema::create('invoice_fee_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('fee_type_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2)->comment('Số tiền cụ thể cho loại phí này trong invoice');
            $table->text('note')->nullable()->comment('Ghi chú riêng cho loại phí này');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_fee_types');
        Schema::dropIfExists('fee_types');
    }
};
