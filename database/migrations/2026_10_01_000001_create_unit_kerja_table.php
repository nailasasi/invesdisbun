<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel unit kerja (Bidang / UPT).
     *
     * Sengaja dibuat sebagai overlay aditif: tabel `skpd` beserta seluruh
     * foreign key yang sudah ada TIDAK diubah, sehingga modul lain
     * (dashboard, RKBMD, aset ruangan) tetap berperilaku sama persis.
     */
    public function up(): void
    {
        if (Schema::hasTable('unit_kerja')) {
            return;
        }

        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->increments('id_unit_kerja');
            $table->string('nama_unit_kerja', 100)->notNull();
            $table->enum('jenis_unit_kerja', ['Bidang', 'UPT'])->default('Bidang');
            $table->unsignedInteger('id_skpd')->nullable();
            $table->enum('status', ['Aktif', 'Nonaktif'])->default('Aktif');
            $table->timestamps();

            $table->unique('nama_unit_kerja');
            $table->foreign('id_skpd')->references('id_skpd')->on('skpd')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerja');
    }
};