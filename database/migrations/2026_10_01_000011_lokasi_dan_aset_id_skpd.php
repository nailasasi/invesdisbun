<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Menambahkan relasi langsung ke `skpd` pada `lokasi` dan `aset`.
 *
 * - lokasi.id_skpd : lokasi UPT milik skpd UPT mana (null untuk kantor dinas)
 * - aset.id_skpd   : Unit Penanggung Jawab aset (organisasi pemilik),
 *                    TIDAK sama dengan lokasi fisik — aset milik Bidang bisa
 *                    ditaruh di UPT dan sebaliknya.
 *
 * Lihat 000010 (jenis_skpd) dan 000012 (hapus overlay unit_kerja).
 */
return new class extends Migration
{
    public function up(): void
    {
        // (a) lokasi.id_skpd
        if (! Schema::hasColumn('lokasi', 'id_skpd')) {
            Schema::table('lokasi', function (Blueprint $table) {
                $table->unsignedInteger('id_skpd')->nullable()->after('id_lokasi');
                $table->foreign('id_skpd')->references('id_skpd')->on('skpd')->nullOnDelete();
            });
        }

        // Lokasi UPT dipetakan ke skpd UPT yang namanya sama.
        // Nama lokasi "Kantor UPT P2BTP" → skpd "UPT P2BTP".
        foreach (DB::table('lokasi')->whereNull('id_skpd')->where('jenis_lokasi', 'UPT')->get() as $lokasi) {
            $suffix = trim(Str::after($lokasi->nama_lokasi, 'Kantor '));

            $idSkpd = DB::table('skpd')
                ->where('jenis_skpd', 'UPT')
                ->where('nama_skpd', $suffix)
                ->value('id_skpd');

            if ($idSkpd) {
                DB::table('lokasi')->where('id_lokasi', $lokasi->id_lokasi)->update(['id_skpd' => $idSkpd]);
            }
        }

        // (b) aset.id_skpd (Unit Penanggung Jawab aset)
        if (! Schema::hasColumn('aset', 'id_skpd')) {
            Schema::table('aset', function (Blueprint $table) {
                $table->unsignedInteger('id_skpd')->nullable()->after('id_barang');
                $table->foreign('id_skpd')->references('id_skpd')->on('skpd')->nullOnDelete();
            });
        }

        // Backfill dari overlay unit_kerja yang masih ada (migrasi 000012
        // akan menghapusnya). Aset tanpa unit kerja tetap NULL — unit PJ
        // diisi saat aset dibuat/mutasi.
        DB::statement('
            UPDATE aset a
            LEFT JOIN unit_kerja u ON u.id_unit_kerja = a.id_unit_kerja
            SET a.id_skpd = COALESCE(u.id_skpd, a.id_skpd)
        ');
    }

    public function down(): void
    {
        if (Schema::hasColumn('aset', 'id_skpd')) {
            Schema::table('aset', function (Blueprint $table) {
                $table->dropForeign(['id_skpd']);
                $table->dropColumn('id_skpd');
            });
        }

        if (Schema::hasColumn('lokasi', 'id_skpd')) {
            Schema::table('lokasi', function (Blueprint $table) {
                $table->dropForeign(['id_skpd']);
                $table->dropColumn('id_skpd');
            });
        }
    }
};
