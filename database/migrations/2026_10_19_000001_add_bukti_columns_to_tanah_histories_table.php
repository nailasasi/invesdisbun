<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memperluas `tanah_histories` (Opsi A) untuk mendukung bukti otorisasi
     * (foto kamera) dan pencatatan aktivitas Retribusi Tanah, serta identitas
     * aktor sebagai snapshot.
     *
     * Semua kolom bersifat ADITIF & nullable (kecuali `objek` yang berdefault
     * 'tanah' sehingga baris lama otomatis terisi), sehingga tidak merusak
     * data/riwayat yang sudah ada. Tidak ada kolom yang dihapus.
     *
     * CATATAN: `id_retribusi` SENGAJA TIDAK memakai foreign key. Alasannya,
     * aktivitas HAPUS Retribusi harus tetap menyimpan bukti walau baris
     * retribusi-nya sudah tidak ada. Foreign key dengan cascade akan ikut
     * menghapus bukti tersebut. Konsistensi dijaga di level aplikasi.
     */
    public function up(): void
    {
        Schema::table('tanah_histories', function (Blueprint $table) {
            $table->unsignedInteger('id_retribusi')->nullable();
            $table->string('nama_aktor', 100)->nullable();
            $table->string('objek', 20)->default('tanah');
            $table->string('foto_bukti')->nullable();

            $table->index('id_retribusi', 'tanah_histories_id_retribusi_index');
        });
    }

    public function down(): void
    {
        Schema::table('tanah_histories', function (Blueprint $table) {
            $table->dropIndex('tanah_histories_id_retribusi_index');
            $table->dropColumn(['id_retribusi', 'nama_aktor', 'objek', 'foto_bukti']);
        });
    }
};
