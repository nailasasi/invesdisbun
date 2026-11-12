<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Memperkuat integritas relasi Pegawai <-> User:
     * - Satu pegawai maksimal memiliki satu akun (UNIQUE).
     * - Setiap akun wajib terhubung dengan satu pegawai (NOT NULL).
     * - Pegawai yang masih memiliki akun tidak dapat dihapus (ON DELETE RESTRICT).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_id_pegawai_foreign');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('id_pegawai')->nullable(false)->change();
            $table->unique('id_pegawai', 'users_id_pegawai_unique');
            $table->foreign('id_pegawai', 'users_id_pegawai_foreign')
                ->references('id_pegawai')
                ->on('pegawai')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Mengembalikan struktur ke konfigurasi awal (reversibel penuh):
     * - Lepas foreign key RESTRICT dan UNIQUE index.
     * - Kembalikan users.id_pegawai menjadi nullable.
     * - Pasang kembali foreign key dengan ON DELETE SET NULL.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_id_pegawai_foreign');
            $table->dropUnique('users_id_pegawai_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('id_pegawai')->nullable()->change();
            $table->foreign('id_pegawai', 'users_id_pegawai_foreign')
                ->references('id_pegawai')
                ->on('pegawai')
                ->onDelete('set null')
                ->onUpdate('no action');
        });
    }
};

