<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pajak_kendaraan', function (Blueprint $table) {
            $table->date('tanggal_berakhir_stnk')->nullable()->after('tanggal_berakhir');
        });

        // Backfill data lama: untuk transaksi 5 tahunan lama yang sudah tersimpan,
        // tanggal_berakhir sebelumnya merepresentasikan masa berlaku 5 tahunan STNK.
        DB::table('pajak_kendaraan')
            ->where('pajak_5_tahunan', true)
            ->whereNull('tanggal_berakhir_stnk')
            ->whereNotNull('tanggal_berakhir')
            ->update([
                'tanggal_berakhir_stnk' => DB::raw('tanggal_berakhir'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pajak_kendaraan', function (Blueprint $table) {
            $table->dropColumn('tanggal_berakhir_stnk');
        });
    }
};
