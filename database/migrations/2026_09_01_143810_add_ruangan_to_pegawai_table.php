<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pegawai', 'id_ruangan')) {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->unsignedInteger('id_ruangan')
                    ->nullable()
                    ->after('id_pegawai');
            });
        } else {
            // Kolom mungkin sudah terlanjur dibuat sebagai SIGNED INT
            // pada percobaan migration sebelumnya.
            DB::statement(
                'ALTER TABLE `pegawai` MODIFY `id_ruangan` INT UNSIGNED NULL'
            );
        }

        // Pastikan foreign key belum ada sebelum dibuat.
        Schema::table('pegawai', function (Blueprint $table) {
            $table->foreign('id_ruangan')
                ->references('id_ruangan')
                ->on('ruangan')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pegawai', 'id_ruangan')) {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->dropForeign(['id_ruangan']);
                $table->dropColumn('id_ruangan');
            });
        }
    }
};