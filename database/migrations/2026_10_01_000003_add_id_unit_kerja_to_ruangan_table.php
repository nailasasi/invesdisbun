<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ruangan mengikuti unit kerja. Ruangan bersama (id_skpd NULL) tetap
     * kosong di kolom ini supaya tetap terlihat oleh seluruh unit —
     * perbedaan dengan `id_skpd` yang jadi acuan modul lain.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('ruangan', 'id_unit_kerja')) {
            Schema::table('ruangan', function (Blueprint $table) {
                $table->unsignedInteger('id_unit_kerja')
                    ->nullable()
                    ->after('id_skpd');
            });
        }

        if (Schema::hasColumn('ruangan', 'id_unit_kerja') && ! $this->hasForeign('ruangan', 'id_unit_kerja')) {
            Schema::table('ruangan', function (Blueprint $table) {
                $table->foreign('id_unit_kerja')
                    ->references('id_unit_kerja')
                    ->on('unit_kerja')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ruangan', 'id_unit_kerja')) {
            return;
        }

        if ($this->hasForeign('ruangan', 'id_unit_kerja')) {
            Schema::table('ruangan', function (Blueprint $table) {
                $table->dropForeign(['id_unit_kerja']);
            });
        }

        Schema::table('ruangan', function (Blueprint $table) {
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