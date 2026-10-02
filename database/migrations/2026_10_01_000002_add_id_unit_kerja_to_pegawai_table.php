<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unit kerja disimpan di tabel `pegawai` (bukan `users`) karena seluruh
     * field organizasional — jabatan, id_skpd, id_ruangan — sudah ada di
     * `pegawai`, sedangkan `users` hanya menyimpan `id_pegawai`.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('pegawai', 'id_unit_kerja')) {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->unsignedInteger('id_unit_kerja')
                    ->nullable()
                    ->after('id_skpd');
            });
        }

        if (Schema::hasColumn('pegawai', 'id_unit_kerja') && ! $this->hasForeign('pegawai', 'id_unit_kerja')) {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->foreign('id_unit_kerja')
                    ->references('id_unit_kerja')
                    ->on('unit_kerja')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pegawai', 'id_unit_kerja')) {
            return;
        }

        if ($this->hasForeign('pegawai', 'id_unit_kerja')) {
            Schema::table('pegawai', function (Blueprint $table) {
                $table->dropForeign(['id_unit_kerja']);
            });
        }

        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('id_unit_kerja');
        });
    }

    private function hasForeign(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) !== null;
    }
};