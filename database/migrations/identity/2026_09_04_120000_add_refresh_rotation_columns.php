<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('identity')->table('refresh_tokens', function (Blueprint $table) {
            if (!Schema::connection('identity')->hasColumn('refresh_tokens', 'sid')) {
                $table->char('sid', 36)->nullable()->after('token_hash')->index();
            }
            if (!Schema::connection('identity')->hasColumn('refresh_tokens', 'jti')) {
                $table->char('jti', 36)->nullable()->after('sid')->index();
            }
            if (!Schema::connection('identity')->hasColumn('refresh_tokens', 'family')) {
                $table->char('family', 36)->nullable()->after('jti')->index();
            }
            if (!Schema::connection('identity')->hasColumn('refresh_tokens', 'tv')) {
                $table->unsignedInteger('tv')->default(1)->after('family');
            }
            if (!Schema::connection('identity')->hasColumn('refresh_tokens', 'replaced_by')) {
                $table->char('replaced_by', 64)->nullable()->after('revoked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('identity')->table('refresh_tokens', function (Blueprint $table) {
            foreach (['sid','jti','family','tv','replaced_by'] as $col) {
                if (Schema::connection('identity')->hasColumn('refresh_tokens', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
