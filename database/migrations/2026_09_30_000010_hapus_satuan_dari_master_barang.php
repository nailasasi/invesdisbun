<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus satuan dari master barang.
     *
     * Halaman detail aset mewakili satu unit fisik individual yang
     * diidentifikasi nomor KIB / kartu barang, sehingga informasi satuan
     * tidak relevan. Kolom ini tidak pernah terisi oleh alur aplikasi
     * (0 dari 30 baris), jadi tidak ada data yang hilang.
     */
    public function up(): void
    {
        if (Schema::hasColumn('master_barang', 'satuan')) {
            Schema::table('master_barang', function (Blueprint $table) {
                $table->dropColumn('satuan');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('master_barang', 'satuan')) {
            Schema::table('master_barang', function (Blueprint $table) {
                $table->string('satuan', 50)->nullable()->after('nama_barang');
            });
        }
    }
};