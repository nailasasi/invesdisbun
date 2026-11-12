<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mengubah FK `tanah_histories.id_user` dari cascadeOnDelete menjadi
     * nullOnDelete, dan membuat kolom `id_user` nullable.
     *
     * Tujuan: bukti aktivitas tetap dipertahankan saat akun pengguna dihapus.
     * Identitas aktor tetap terbaca dari kolom snapshot `nama_aktor`
     * (ditambahkan pada migration sebelumnya).
     *
     * Nama FK mengikuti konvensi Laravel: `tanah_histories_id_user_foreign`
     * (terverifikasi pada database MySQL: InnoDB, MySQL 8.0.41).
     *
     * Urutan aman (MySQL 8): lepas FK -> ubah kolom -> pasang FK baru.
     *
     * SQLite (dipakai pengujian) tidak mendukung dropForeign berdasarkan nama
     * dan FK-nya tidak ditegakkan pada level ini; migrasi dilewati agar suite
     * pengujian berbasis SQLite tidak gagal. Semantik nullOnDelete pada
     * produksi (MySQL) tetap diterapkan.
     *
     * CATATAN ROLLBACK: `down()` mengembalikan kolom menjadi NOT NULL. Bila
     * sudah ada baris dengan `id_user` NULL (akibat akun dihapus), rollback
     * akan GAGAL kecuali baris tersebut diisi lebih dulu. Hal ini disengaja
     * (tidak menghapus/mengubah data secara diam-diam).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('tanah_histories', function (Blueprint $table) {
            $table->dropForeign('tanah_histories_id_user_foreign');
        });

        Schema::table('tanah_histories', function (Blueprint $table) {
            $table->unsignedInteger('id_user')->nullable()->change();
        });

        Schema::table('tanah_histories', function (Blueprint $table) {
            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('tanah_histories', function (Blueprint $table) {
            $table->dropForeign('tanah_histories_id_user_foreign');
        });

        Schema::table('tanah_histories', function (Blueprint $table) {
            $table->unsignedInteger('id_user')->nullable(false)->change();
        });

        Schema::table('tanah_histories', function (Blueprint $table) {
            $table->foreign('id_user')
                ->references('id_user')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
