<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users_read_model — bảng bóng sync từ Identity Service qua user_events
     * (Task 3.2: Academic/Library query nhanh không gọi ngược về Identity)
     */
    public function up(): void
    {
        Schema::create('users_read_model', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // user_id từ identity (source of truth)
            $table->string('name');
            $table->string('email')->unique();
            $table->json('roles')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_read_model');
    }
};
