<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `satuan` tidak lagi dipakai pada inventaris lokal.
     * Migration ini menghapusnya; `down()` mengembalikannya agar reversible.
     * Hanya menyentuh tabel `inventaris_ruangan` (tidak menyentuh aset resmi,
     * master_barang, maupun penempatan_aset).
     */
    public function up(): void
    {
        if (Schema::hasColumn('inventaris_ruangan', 'satuan')) {
            Schema::table('inventaris_ruangan', function (Blueprint $table) {
                $table->dropColumn('satuan');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventaris_ruangan', 'satuan')) {
            Schema::table('inventaris_ruangan', function (Blueprint $table) {
                $table->string('satuan', 30)->nullable()->after('merk');
            });
        }
    }
};
