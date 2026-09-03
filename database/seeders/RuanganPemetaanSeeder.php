<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use App\Models\Skpd;
use Illuminate\Database\Seeder;

class RuanganPemetaanSeeder extends Seeder
{
    /**
     * Petakan ulang ruangan ke SKPD masing-masing:
     *  - Ruangan umum (bersama)          -> id_skpd = null
     *  - Ruangan Sekretariat             -> SKPD 'Sekretariat'
     *  - Ruangan Bidang 1-4              -> SKPD Bidang terkait
     *  - Ruangan dengan 2 SKPD (Transit) -> id_skpd = null (umum)
     */
    public function run(): void
    {
        $skpd = [
            'sekretariat' => Skpd::where('nama_skpd', 'Sekretariat')->first(),
            'bidang1' => Skpd::where('nama_skpd', 'Bidang Pengelolahan dan Pemasaran Hasil')->first(),
            'bidang2' => Skpd::where('nama_skpd', 'Bidang Perlindungan Perkebunan')->first(),
            'bidang3' => Skpd::where('nama_skpd', 'Bidang Produksi Tanaman Semusim')->first(),
            'bidang4' => Skpd::where('nama_skpd', 'Bidang Produksi Tanaman Tahunan')->first(),
        ];

        $pemetaan = [
            // --- Umum (tidak terikat SKPD) ---
            'Ruang Rapat Kakao' => null,
            'Ruang Rapat Kopi' => null,
            'Ruang Record Center' => null,
            'Ruang Perpustakaan' => null,
            'Ruang Panel Listrik' => null,
            'Ruang Server' => null,
            'Ruang PPID' => null,
            'Ruang Rapat Tebu' => null,
            'Ruang Transit Dharmawanita Dinas Perkebunan' => null,
            'Ruang Kerja Kepala Dinas' => null,
            'Ruang Rapat Kepala Dinas' => null,
            'Ruang Receptionist Kepala Dinas' => null,

            // --- Sekretariat ---
            'Ruang Kerja Sekretaris Dinas' => $skpd['sekretariat']?->id_skpd,
            'Ruang Sub Bagian Umum' => $skpd['sekretariat']?->id_skpd,
            'Ruang Penyusunan Program Anggaran' => $skpd['sekretariat']?->id_skpd,
            'Ruang Sub Keuangan' => $skpd['sekretariat']?->id_skpd,
            'Ruang Aset' => $skpd['sekretariat']?->id_skpd,

            // --- Bidang 1 (Pengolahan dan Pemasaran Hasil) ---
            'Ruang Kepala Bidang 1 (Bidang Pengolahan dan Pemasaran Hasil)' => $skpd['bidang1']?->id_skpd,
            'Ruang Bidang 1 (Bidang Pengolahan dan Pemasaran Hasil)' => $skpd['bidang1']?->id_skpd,
            'Ruang Rapat Bidang 1 (Bidang Pengolahan dan Pemasaran Hasil)' => $skpd['bidang1']?->id_skpd,

            // --- Bidang 2 (Perlindungan Perkebunan) ---
            'Ruang Kepala Bidang 2 (Bidang Perlindungan Perkebunan)' => $skpd['bidang2']?->id_skpd,
            'Ruang Bidang 2 (Bidang Perlindungan Perkebunan)' => $skpd['bidang2']?->id_skpd,
            'Ruang Rapat Bidang 2 (Bidang Perlindungan Perkebunan)' => $skpd['bidang2']?->id_skpd,

            // --- Bidang 3 (Tanaman Semusim) ---
            'Ruang Kepala Bidang 3 (Bidang Tanaman Semusim)' => $skpd['bidang3']?->id_skpd,
            'Ruang Bidang 3 (Bidang Tanaman Semusim)' => $skpd['bidang3']?->id_skpd,
            'Ruang Rapat Bidang 3 (Bidang Tanaman Semusim)' => $skpd['bidang3']?->id_skpd,

            // --- Bidang 4 (Proteksi Tanaman Tahunan) ---
            'Ruang Kepala Bidang 4 (Bidang Proteksi Tanaman Tahunan)' => $skpd['bidang4']?->id_skpd,
            'Ruang Bidang 4 (Bidang Proteksi Tanaman Tahunan)' => $skpd['bidang4']?->id_skpd,
            'Ruang Rapat Bidang 4 (Bidang Proteksi Tanaman Tahunan)' => $skpd['bidang4']?->id_skpd,
        ];

        $updated = 0;
        foreach ($pemetaan as $namaRuangan => $idSkpd) {
            $ruangan = Ruangan::where('nama_ruangan', $namaRuangan)->first();
            if (!$ruangan) {
                continue;
            }
            $ruangan->update(['id_skpd' => $idSkpd]);
            $updated++;
        }

        $this->command->info("{$updated} ruangan dipetakan ulang.");
    }
}