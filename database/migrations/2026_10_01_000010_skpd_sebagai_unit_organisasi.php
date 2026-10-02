<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `skpd` kembali menjadi acuan organisasi tunggal: mencakup Sekretariat,
 * Bidang-bidang, dan UPT. Jenisnya ditandai eksplisit lewat kolom baru
 * `jenis_skpd` (menggantikan inferensi dari nama).
 *
 * Migration ini bagian dari pembongkaran overlay `unit_kerja`. Lihat juga
 * 000011 dan 000012.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('skpd', 'jenis_skpd')) {
            Schema::table('skpd', function (Blueprint $table) {
                $table->enum('jenis_skpd', ['Sekretariat', 'Bidang', 'UPT'])
                    ->nullable()
                    ->after('nama_skpd');
                $table->index('jenis_skpd');
            });
        }

        // Backfill idempotent berdasarkan nama.
        DB::table('skpd')->whereNull('jenis_skpd')->update([
            'jenis_skpd' => DB::raw("CASE
                WHEN nama_skpd = 'Sekretariat' THEN 'Sekretariat'
                WHEN nama_skpd LIKE 'UPT%' THEN 'UPT'
                ELSE 'Bidang'
            END"),
        ]);

        $this->hapusSkpdUmbrella();
    }

    /**
     * Baris "Dinas Perkebunan Provinsi Jawa Timur" adalah sisa migrasi lama
     * yang merepresentasikan agency, bukan unit organisasi. Setelah `skpd`
     * menjadi unit organisasi tunggal, baris ini tidak dipakai lagi.
     *
     * Dihapus hanya bila tidak ada FK yang mereferensinya, agar aman dijalankan
     * ulang pada database yang sudah punya data terikat.
     */
    private function hapusSkpdUmbrella(): void
    {
        $id = DB::table('skpd')->where('nama_skpd', 'Dinas Perkebunan Provinsi Jawa Timur')->value('id_skpd');

        if (! $id) {
            return;
        }

        $terikat = DB::table('pegawai')->where('id_skpd', $id)->exists()
            || DB::table('ruangan')->where('id_skpd', $id)->exists()
            || DB::table('usulan_rkbmd')->where('id_skpd', $id)->exists()
            || DB::table('arsip_rkbmd')->where('id_skpd', $id)->exists();

        if ($terikat) {
            return;
        }

        DB::table('skpd')->where('id_skpd', $id)->delete();
    }

    public function down(): void
    {
        // Best-effort: baris umbrella dikembalikan tanpa id asli.
        DB::table('skpd')->insertOrIgnore([
            'nama_skpd' => 'Dinas Perkebunan Provinsi Jawa Timur',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasColumn('skpd', 'jenis_skpd')) {
            Schema::table('skpd', function (Blueprint $table) {
                $table->dropIndex(['jenis_skpd']);
                $table->dropColumn('jenis_skpd');
            });
        }
    }
};
