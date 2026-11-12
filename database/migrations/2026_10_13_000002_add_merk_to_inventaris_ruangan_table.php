<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventaris lokal ruangan: tambah kolom `merk` (nullable).
     * Aditif murni — tidak mengubah/menghapus data yang sudah ada.
     */
    public function up(): void
    {
        Schema::table('inventaris_ruangan', function (Blueprint $table) {
            $table->string('merk', 100)->nullable()->after('nama_barang');
        });
    }

    public function down(): void
    {
        Schema::table('inventaris_ruangan', function (Blueprint $table) {
            $table->dropColumn('merk');
        });
    }
};
