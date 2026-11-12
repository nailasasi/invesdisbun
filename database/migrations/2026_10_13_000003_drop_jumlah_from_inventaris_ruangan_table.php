<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `jumlah` tidak lagi dipakai pada inventaris lokal (digantikan `merk`).
     * Migration ini menghapus kolom `jumlah`; `down()` mengembalikannya agar
     * reversible. Tidak menyentuh tabel aset/master_barang/penempatan_aset.
     */
    public function up(): void
    {
        Schema::table('inventaris_ruangan', function (Blueprint $table) {
            $table->dropColumn('jumlah');
        });
    }

    public function down(): void
    {
        Schema::table('inventaris_ruangan', function (Blueprint $table) {
            $table->unsignedInteger('jumlah')->default(1)->after('nama_barang');
        });
    }
};
