<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->string('name')->nullable()->after('id');

            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->string('bio', 500)->nullable()->after('avatar_url');

            $table->string('password_hash')->nullable()->after('password');
            $table->boolean('is_email_verified')->default(false)->after('email_verified_at');
            $table->boolean('is_phone_verified')->default(false)->after('is_email_verified');

            $table->boolean('is_active')->default(true)->after('status');
            $table->boolean('is_locked')->default(false)->after('is_active');
            $table->string('locked_reason')->nullable()->after('is_locked');
            $table->timestamp('locked_at')->nullable()->after('locked_reason');

            $table->unsignedSmallInteger('failed_login_count')->default(0)->after('locked_at');

            $table->unsignedInteger('token_version')->default(1)->after('failed_login_count');

            $table->string('provider')->nullable()->after('token_version');
            $table->string('provider_id')->nullable()->after('provider');

            $table->string('totp_secret')->nullable()->after('provider_id');
            $table->string('totp_secret_temp')->nullable()->after('totp_secret');
            $table->boolean('totp_enabled')->default(false)->after('totp_secret_temp');

            $table->boolean('phone_2fa_enabled')->default(false)->after('totp_enabled');

            $table->timestamp('last_login_at')->nullable()->after('phone_2fa_enabled');

            $table->index('provider_id');
            $table->index(['email', 'is_active']);
        });
        DB::statement("
            UPDATE users
            SET
                is_email_verified = CASE WHEN email_verified_at IS NOT NULL THEN true ELSE false END,
                is_active         = CASE WHEN status = 1 THEN true ELSE false END,
                password_hash     = password
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['provider_id']);
            $table->dropIndex(['email', 'is_active']);

            $table->dropColumn([
                'name', 'phone', 'avatar_url', 'bio',
                'password_hash',
                'is_email_verified', 'is_phone_verified',
                'is_active', 'is_locked', 'locked_reason', 'locked_at',
                'failed_login_count', 'token_version',
                'provider', 'provider_id',
                'totp_secret', 'totp_secret_temp', 'totp_enabled',
                'phone_2fa_enabled',
                'last_login_at',
            ]);
        });
    }
};
