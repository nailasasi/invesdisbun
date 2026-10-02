<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SKPD induk organisasi di lingkungan ini adalah "Sekretariat".
     * Seluruh SKPD selain itu sebenarnya sudah merupakan unit kerja
     * (Bidang-xx / UPT xx) — hanya saja selama ini direpresentasikan
     * sebagai baris `skpd`.
     *
     * Migration ini memirror data tersebut ke `unit_kerja` tanpa
     * memindahkan, menghapus, atau mengubah satu baris pun di `skpd`,
     * `pegawai`, maupun `ruangan`. Yang berubah hanya tambahan kolom
     * nullable `id_unit_kerja` yang diisi dari `id_skpd` yang sudah ada.
     */
    private const SKPD_INDUK = 'sekretariat';

    public function up(): void
    {
        if (! Schema::hasTable('unit_kerja')) {
            return;
        }

        $this->isiUnitKerja();

        // Pegawai: turunan unit kerja dari SKPD yang sedang terpasang.
        DB::statement(
            'UPDATE `pegawai` p
             INNER JOIN `unit_kerja` u ON u.`id_skpd` = p.`id_skpd`
             SET p.`id_unit_kerja` = u.`id_unit_kerja`
             WHERE p.`id_unit_kerja` IS NULL'
        );

        // Ruangan: ruang bersama (id_skpd NULL) sengaja dibiarkan NULL
        // agar tetap dapat diakses seluruh unit kerja.
        DB::statement(
            'UPDATE `ruangan` r
             INNER JOIN `unit_kerja` u ON u.`id_skpd` = r.`id_skpd`
             SET r.`id_unit_kerja` = u.`id_unit_kerja`
             WHERE r.`id_unit_kerja` IS NULL AND r.`id_skpd` IS NOT NULL'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('unit_kerja') || ! Schema::hasColumn('pegawai', 'id_unit_kerja')) {
            return;
        }

        DB::table('pegawai')->update(['id_unit_kerja' => null]);

        if (Schema::hasColumn('ruangan', 'id_unit_kerja')) {
            DB::table('ruangan')->update(['id_unit_kerja' => null]);
        }
    }

    private function isiUnitKerja(): void
    {
        $skpdList = DB::table('skpd')->orderBy('id_skpd')->get();

        foreach ($skpdList as $skpd) {
            $nama = trim((string) $skpd->nama_skpd);

            if ($nama === '' || mb_strtolower($nama) === self::SKPD_INDUK) {
                continue;
            }

            $jenis = str_starts_with(mb_strtoupper($nama), 'UPT') ? 'UPT' : 'Bidang';

            // id_skpd diarahkan ke baris SKPD yang selama ini memegang data
            // unit tersebut. Ini menjaga presisi filter ruangan tetap sama
            // dengan perilaku saat ini, tanpa mengubah query modul lain.
            $payload = [
                'jenis_unit_kerja' => $jenis,
                'id_skpd' => $skpd->id_skpd,
                'status' => 'Aktif',
                'updated_at' => now(),
            ];

            $existing = DB::table('unit_kerja')
                ->where('nama_unit_kerja', $nama)
                ->first();

            if ($existing) {
                DB::table('unit_kerja')
                    ->where('id_unit_kerja', $existing->id_unit_kerja)
                    ->update($payload);

                continue;
            }

            DB::table('unit_kerja')->insert($payload + [
                'nama_unit_kerja' => $nama,
                'created_at' => now(),
            ]);
        }
    }
};