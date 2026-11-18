<?php

namespace Database\Seeders;

use App\Models\TemplateDokumen;
use Illuminate\Database\Seeder;

class TemplateDokumenSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'kode_template' => 'sppbi',
                'nama_template' => 'TEMPLATE SPPBI',
                'tipe_berkas' => 'word',
            ],
            [
                'kode_template' => 'bast_barang',
                'nama_template' => 'TEMPLATE BAST BARANG',
                'tipe_berkas' => 'word',
            ],
            [
                'kode_template' => 'bast_kendaraan',
                'nama_template' => 'TEMPLATE BAST KENDARAAN',
                'tipe_berkas' => 'word',
            ],
            [
                'kode_template' => 'sppkd',
                'nama_template' => 'TEMPLATE SPPKD',
                'tipe_berkas' => 'word',
            ],
            [
                'kode_template' => 'label',
                'nama_template' => 'TEMPLATE LABEL',
                'tipe_berkas' => 'excel',
            ],
            [
                'kode_template' => 'surat_izin_kendaraan',
                'nama_template' => 'SURAT IZIN KENDARAAN',
                'tipe_berkas' => 'word',
                'deskripsi' => 'Format file Word (.docx) untuk cetak surat izin pemakaian kendaraan dinas.',
            ],
            [
                'kode_template' => 'rkbmd_pengadaan',
                'nama_template' => 'TEMPLATE RKBMD PENGADAAN',
                'nama_file_asli' => 'RKBMD Pengadaan.xlsx',
                'file_path' => 'templates/rkbmd_pengadaan_template.xlsx',
                'tipe_berkas' => 'excel',
                'status' => 'Siap Digunakan',
                'deskripsi' => 'Format file Excel (.xlsx) untuk cetak/ekspor usulan RKBMD Pengadaan.',
            ],
            [
                'kode_template' => 'rkbmd_pemeliharaan',
                'nama_template' => 'TEMPLATE RKBMD PEMELIHARAAN',
                'nama_file_asli' => 'RKBMD Pemeliharaan.xlsx',
                'file_path' => 'templates/rkbmd_pemeliharaan_template.xlsx',
                'tipe_berkas' => 'excel',
                'status' => 'Siap Digunakan',
                'deskripsi' => 'Format file Excel (.xlsx) untuk cetak/ekspor usulan RKBMD Pemeliharaan.',
            ],
            [
                'kode_template' => 'rkbmd_penghapusan',
                'nama_template' => 'TEMPLATE RKBMD PENGHAPUSAN',
                'nama_file_asli' => 'RKBMD Penghapusan.xlsx',
                'file_path' => 'templates/rkbmd_penghapusan_template.xlsx',
                'tipe_berkas' => 'excel',
                'status' => 'Siap Digunakan',
                'deskripsi' => 'Format file Excel (.xlsx) untuk cetak/ekspor usulan RKBMD Penghapusan.',
            ],
            [
                'kode_template' => 'rkbmd_pemindahtanganan',
                'nama_template' => 'TEMPLATE RKBMD PEMINDAHTANGANAN',
                'nama_file_asli' => 'RKBMD Pemindahtanganan.xlsx',
                'file_path' => 'templates/rkbmd_pemindahtanganan_template.xlsx',
                'tipe_berkas' => 'excel',
                'status' => 'Siap Digunakan',
                'deskripsi' => 'Format file Excel (.xlsx) untuk cetak/ekspor usulan RKBMD Pemindahtanganan.',
            ],
            [
                'kode_template' => 'rkbmd_pemanfaatan',
                'nama_template' => 'TEMPLATE RKBMD PEMANFAATAN',
                'nama_file_asli' => 'RKBMD Pemanfaatan.xlsx',
                'file_path' => 'templates/rkbmd_pemanfaatan_template.xlsx',
                'tipe_berkas' => 'excel',
                'status' => 'Siap Digunakan',
                'deskripsi' => 'Format file Excel (.xlsx) untuk cetak/ekspor usulan RKBMD Pemanfaatan.',
            ],
        ];

        foreach ($templates as $tpl) {
            TemplateDokumen::updateOrCreate(
                ['kode_template' => $tpl['kode_template']],
                $tpl
            );
        }
    }
}