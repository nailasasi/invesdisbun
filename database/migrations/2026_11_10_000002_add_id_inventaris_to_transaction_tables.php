<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambahkan id_inventaris ke detail_mutasi_aset
        if (Schema::hasTable('detail_mutasi_aset') && ! Schema::hasColumn('detail_mutasi_aset', 'id_inventaris')) {
            Schema::table('detail_mutasi_aset', function (Blueprint $table) {
                $table->unsignedInteger('id_inventaris')->nullable()->after('id_aset');
                $table->foreign('id_inventaris')->references('id_inventaris')->on('inventaris_ruangan')->onDelete('set null');
                $table->index('id_inventaris');
            });
        }

        // 2. Tambahkan id_inventaris ke usulan_penghapusan
        if (Schema::hasTable('usulan_penghapusan') && ! Schema::hasColumn('usulan_penghapusan', 'id_inventaris')) {
            Schema::table('usulan_penghapusan', function (Blueprint $table) {
                $table->unsignedInteger('id_inventaris')->nullable()->after('id_aset');
                $table->foreign('id_inventaris')->references('id_inventaris')->on('inventaris_ruangan')->onDelete('set null');
                $table->index('id_inventaris');
            });
        }

        // 3. Tambahkan id_inventaris ke riwayat_penghapusan
        if (Schema::hasTable('riwayat_penghapusan') && ! Schema::hasColumn('riwayat_penghapusan', 'id_inventaris')) {
            Schema::table('riwayat_penghapusan', function (Blueprint $table) {
                $table->unsignedInteger('id_inventaris')->nullable()->after('id_aset');
                $table->foreign('id_inventaris')->references('id_inventaris')->on('inventaris_ruangan')->onDelete('set null');
                $table->index('id_inventaris');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('riwayat_penghapusan') && Schema::hasColumn('riwayat_penghapusan', 'id_inventaris')) {
            Schema::table('riwayat_penghapusan', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['id_inventaris']);
                }
                $table->dropIndex(['id_inventaris']);
                $table->dropColumn('id_inventaris');
            });
        }

        if (Schema::hasTable('usulan_penghapusan') && Schema::hasColumn('usulan_penghapusan', 'id_inventaris')) {
            Schema::table('usulan_penghapusan', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['id_inventaris']);
                }
                $table->dropIndex(['id_inventaris']);
                $table->dropColumn('id_inventaris');
            });
        }

        if (Schema::hasTable('detail_mutasi_aset') && Schema::hasColumn('detail_mutasi_aset', 'id_inventaris')) {
            Schema::table('detail_mutasi_aset', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['id_inventaris']);
                }
                $table->dropIndex(['id_inventaris']);
                $table->dropColumn('id_inventaris');
            });
        }
    }
};

