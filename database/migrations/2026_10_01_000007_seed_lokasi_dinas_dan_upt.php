<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed lokasi fisik: kantor dinas + satu lokasi per UPT.
 *
 * Tabel `skpd` sengaja tidak dibongkar (tetap ada demi fleksibilitas masa
 * depan), tetapi user management kini selalu memakai satu SKPD default
 * sebagai agency tunggal.
 */
return new class extends Migration
{
    /** Nama SKPD yang menjadi default seluruh pegawai. */
    public const SKPD_DEFAULT = 'Dinas Perkebunan Provinsi Jawa Timur';

    public function up(): void
    {
        // Pastikan SKPD default ada (idempotent).
        $ada = DB::table('skpd')->where('nama_skpd', self::SKPD_DEFAULT)->exists();

        if (! $ada) {
            DB::table('skpd')->insert([
                'nama_skpd' => self::SKPD_DEFAULT,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Lokasi kedua & ketiga: satu per UPT yang ada.
        $upt = DB::table('unit_kerja')->where('jenis_unit_kerja', 'UPT')->orderBy('id_unit_kerja')->get();

        foreach ($upt as $u) {
            $nama = 'Kantor '.$u->nama_unit_kerja;

            if (DB::table('lokasi')->where('nama_lokasi', $nama)->exists()) {
                continue;
            }

            DB::table('lokasi')->insert([
                'nama_lokasi' => $nama,
                'jenis_lokasi' => 'UPT',
                'keterangan' => 'Lokasi kerja '.$u->nama_unit_kerja.'.',
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Hapus lokasi UPT, sisakan kantor dinas.
        $uptIds = DB::table('unit_kerja')->where('jenis_unit_kerja', 'UPT')->pluck('nama_unit_kerja');

        foreach ($uptIds as $nama) {
            DB::table('lokasi')->where('nama_lokasi', 'Kantor '.$nama)->delete();
        }
    }
};