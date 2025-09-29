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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            // Khóa ngoại đến bảng hóa đơn tổng
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');

            // Khóa ngoại đến bảng loại phí
            $table->foreignId('fee_type_id')->constrained('fee_types')->onDelete('restrict');

            $table->string('description');
            $table->decimal('unit_price', 15, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_amount', 15, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
