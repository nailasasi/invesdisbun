<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penempatan_aset', function (Blueprint $table) {
            $table->tinyInteger('is_finalized')->nullable()->after('status');
            $table->dateTime('finalized_at')->nullable()->after('is_finalized');
            $table->unsignedInteger('finalized_by')->nullable()->after('finalized_at');

            $table->foreign('finalized_by')
                ->references('id_user')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('penempatan_aset', function (Blueprint $table) {
            $table->dropForeign(['finalized_by']);
            $table->dropColumn(['is_finalized', 'finalized_at', 'finalized_by']);
        });
    }
};
