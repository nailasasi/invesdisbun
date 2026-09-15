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
        ];

        foreach ($templates as $tpl) {
            TemplateDokumen::updateOrCreate(
                ['kode_template' => $tpl['kode_template']],
                $tpl
            );
        }
    }
}