<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan bukti pembayaran pajak kendaraan (file di disk `public`).
     * Kolom aditif & nullable: baris lama dan record hasil import tetap aman
     * tanpa backfill.
     */
    public function up(): void
    {
        Schema::table('pajak_kendaraan', function (Blueprint $table) {
            $table->string('bukti_pajak_path', 255)->nullable()->after('status');
            $table->string('bukti_pajak_nama', 255)->nullable()->after('bukti_pajak_path');
        });
    }

    public function down(): void
    {
        Schema::table('pajak_kendaraan', function (Blueprint $table) {
            $table->dropColumn(['bukti_pajak_path', 'bukti_pajak_nama']);
        });
    }
};
