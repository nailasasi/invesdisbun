<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan nama kendaraan sebagai kolom tersendiri pada tabel
     * `kendaraan`, memisahkan nama kendaraan dari `master_barang`.
     *
     * Kolom aditif & nullable: baris kendaraan lama tetap aman tanpa
     * backfill. Panjang 150 disamakan dengan validasi form Kendaraan
     * (`nama_kendaraan` => max:150) agar tidak ada pemotongan nilai.
     */
    public function up(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->string('nama_kendaraan', 150)->nullable()->after('id_aset');
        });
    }

    public function down(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->dropColumn('nama_kendaraan');
        });
    }
};
