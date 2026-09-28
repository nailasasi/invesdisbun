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
        Schema::table('izin_kendaraan', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable()->after('alasan_penolakan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('izin_kendaraan', function (Blueprint $table) {
            $table->dropColumn('dismissed_at');
        });
    }
};
