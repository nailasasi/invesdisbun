<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lokasi fisik untuk (a) ruangan dan (b) aset.
 *
 * - ruangan.id_lokasi  : letak ruangan → cascade dari unit_kerja ruangan
 * - aset.id_lokasi     : letak fisik aset, tetap terpisah dari
 *                        id_unit_kerja (unit penanggung jawab aset)
 *
 * `aset.id_unit_kerja` sengaja ada sendiri karena asset belonging != lokasi.
 * Aset milik Bidang bisa berada di UPT, dan sebaliknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $adaLokasi = fn (string $nama) => DB::table('lokasi')->where('nama_lokasi', $nama)->value('id_lokasi');

        $lokasiDinas = $adaLokasi('Kantor Dinas Perkebunan Provinsi Jawa Timur');

        // ------------------------------------------------------------------
        // (a) Lokasi untuk setiap ruangan.
        // ------------------------------------------------------------------
        if (! Schema::hasColumn('ruangan', 'id_lokasi')) {
            Schema::table('ruangan', function (Blueprint $table) {
                $table->unsignedInteger('id_lokasi')->nullable()->after('id_unit_kerja');
                $table->foreign('id_lokasi')->references('id_lokasi')->on('lokasi')->nullOnDelete();
            });
        }

        // Ruangan milik UPT → lokasi kantor UPT tersebut.
        $lokasiPerUnit = [];

        foreach (DB::table('unit_kerja')->where('jenis_unit_kerja', 'UPT')->orderBy('id_unit_kerja')->get() as $u) {
            $idLokasi = $adaLokasi('Kantor '.$u->nama_unit_kerja);

            if ($idLokasi) {
                $lokasiPerUnit[$u->id_unit_kerja] = $idLokasi;
            }
        }

        foreach ($lokasiPerUnit as $idUnit => $idLokasi) {
            DB::table('ruangan')->where('id_unit_kerja', $idUnit)->update(['id_lokasi' => $idLokasi]);
        }

        // Semua ruangan lain (Sekretariat, ruang bersama, Bidang) → kantor dinas.
        DB::table('ruangan')->whereNull('id_lokasi')->update(['id_lokasi' => $lokasiDinas]);

        // ------------------------------------------------------------------
        // (b) Unit penanggung jawab + lokasi fisik untuk aset.
        // ------------------------------------------------------------------
        if (! Schema::hasColumn('aset', 'id_unit_kerja')) {
            Schema::table('aset', function (Blueprint $table) {
                $table->unsignedInteger('id_unit_kerja')->nullable()->after('id_barang');
                $table->unsignedInteger('id_lokasi')->nullable()->after('id_unit_kerja');
                $table->foreign('id_unit_kerja')->references('id_unit_kerja')->on('unit_kerja')->nullOnDelete();
                $table->foreign('id_lokasi')->references('id_lokasi')->on('lokasi')->nullOnDelete();
            });
        }

        // Backfill: unit penanggung jawab dari pemegang aset aktif, lokasi dari
        // penempatan aktif (ruangan → lokasi).
        DB::statement('
            UPDATE aset a
            LEFT JOIN pemegang_aset pa
                ON pa.id_aset = a.id_aset AND pa.status = \'aktif\'
            LEFT JOIN pegawai p
                ON p.id_pegawai = pa.id_pegawai
            SET a.id_unit_kerja = COALESCE(p.id_unit_kerja, a.id_unit_kerja)
        ');

        DB::statement('
            UPDATE aset a
            LEFT JOIN penempatan_aset pt
                ON pt.id_aset = a.id_aset AND pt.status = \'aktif\'
            LEFT JOIN ruangan r
                ON r.id_ruangan = pt.id_ruangan
            SET a.id_lokasi = COALESCE(r.id_lokasi, a.id_lokasi)
        ');
    }

    public function down(): void
    {
        Schema::table('aset', function (Blueprint $table) {
            $table->dropForeign(['id_unit_kerja']);
            $table->dropForeign(['id_lokasi']);
            $table->dropColumn(['id_unit_kerja', 'id_lokasi']);
        });

        Schema::table('ruangan', function (Blueprint $table) {
            $table->dropForeign(['id_lokasi']);
            $table->dropColumn('id_lokasi');
        });
    }
};