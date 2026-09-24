<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * teachers — teacher directory (source of truth cho GetTeacherProfile gRPC).
     * user_id là logical FK -> identity.users.id, không ràng buộc cross-DB.
     */
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('logical FK -> identity.users.id, no DB constraint cross-DB');
            $table->string('teacher_code')->unique();
            $table->string('department')->nullable();
            $table->json('subjects')->nullable();
            $table->date('hire_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
