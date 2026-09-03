<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use App\Models\Skpd;
use Illuminate\Database\Seeder;

class RuanganSeeder extends Seeder
{
    /**
     * Daftar ruangan fisik di gedung Dinas Perkebunan.
     * Semua diisi default SKPD 'Sekretariat'; pemetaan per unit bisa diatur
     * ulang belakangan (lewat Edit di menu Aset Ruangan).
     */
    public function run(): void
    {
        $skpd = Skpd::where('nama_skpd', 'Sekretariat')->firstOrFail();

        $ruangan = [
            'Ruang Kerja Sekretaris Dinas',
            'Ruang Rapat Kakao',
            'Ruang Rapat Kopi',
            'Ruang Sub Bagian Umum',
            'Ruang Penyusunan Program Anggaran',
            'Ruang Sub Keuangan',
            'Ruang Record Center',
            'Ruang Aset',
            'Ruang Perpustakaan',
            'Ruang Transit Dharmawanita Dinas Perkebunan',
            'Ruang PPID',
            'Ruang Panel Listrik',
            'Ruang Server',
            'Ruang Kerja Kepala Dinas',
            'Ruang Rapat Kepala Dinas',
            'Ruang Receptionist Kepala Dinas',
            'Ruang Kepala Bidang 1 (Bidang Pengolahan dan Pemasaran Hasil)',
            'Ruang Bidang 1 (Bidang Pengolahan dan Pemasaran Hasil)',
            'Ruang Rapat Bidang 1 (Bidang Pengolahan dan Pemasaran Hasil)',
            'Ruang Kepala Bidang 2 (Bidang Perlindungan Perkebunan)',
            'Ruang Bidang 2 (Bidang Perlindungan Perkebunan)',
            'Ruang Rapat Bidang 2 (Bidang Perlindungan Perkebunan)',
            'Ruang Kepala Bidang 3 (Bidang Tanaman Semusim)',
            'Ruang Bidang 3 (Bidang Tanaman Semusim)',
            'Ruang Rapat Bidang 3 (Bidang Tanaman Semusim)',
            'Ruang Kepala Bidang 4 (Bidang Proteksi Tanaman Tahunan)',
            'Ruang Bidang 4 (Bidang Proteksi Tanaman Tahunan)',
            'Ruang Rapat Bidang 4 (Bidang Proteksi Tanaman Tahunan)',
            'Ruang Rapat Tebu',
        ];

        foreach ($ruangan as $namaRuangan) {
            Ruangan::firstOrCreate(
                ['nama_ruangan' => $namaRuangan],
                ['id_skpd' => $skpd->id_skpd]
            );
        }

        $this->command->info(count($ruangan) . ' ruangan diisikan ke SKPD Sekretariat.');
    }
}