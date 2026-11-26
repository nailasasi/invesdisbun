<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyamakan panjang kolom string pada tabel `kendaraan` dengan aturan
     * validasi form Tambah/Edit & Import Kendaraan (`jenis_kendaraan`: 150,
     * `merk`: 100, `tipe`: 100) agar tidak terjadi error SQL 1406.
     */
    public function up(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->string('jenis_kendaraan', 150)->nullable()->change();
            $table->string('merk', 100)->nullable()->change();
            $table->string('tipe', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->string('jenis_kendaraan', 50)->nullable()->change();
            $table->string('merk', 50)->nullable()->change();
            $table->string('tipe', 50)->nullable()->change();
        });
    }
};
