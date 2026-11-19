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
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->date('jatuh_tempo_pkb')->nullable()->after('tipe');
            $table->date('jatuh_tempo_stnk')->nullable()->after('jatuh_tempo_pkb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->dropColumn(['jatuh_tempo_pkb', 'jatuh_tempo_stnk']);
        });
    }
};
