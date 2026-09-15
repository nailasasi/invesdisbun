<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            ['bast_barang', 'BAST Barang (Berita Acara Serah Terima Aset Barang)', 'word'],
            ['kir', 'KIR (Kartu Inventaris Ruangan)', 'excel'],
            ['sppkd', 'SPPKD (Surat Penetapan Penggunaan Kendaraan Dinas)', 'word'],
            ['bast_kendaraan', 'BAST Kendaraan (Berita Acara Serah Terima Kendaraan Dinas)', 'word'],
        ];

        $now = now();

        foreach ($items as [$kode, $nama, $tipe]) {
            $exists = DB::table('template_dokumen')->where('kode_template', $kode)->exists();

            if ($exists) {
                DB::table('template_dokumen')
                    ->where('kode_template', $kode)
                    ->update(['nama_template' => $nama, 'tipe_berkas' => $tipe, 'updated_at' => $now]);
            } else {
                DB::table('template_dokumen')->insert([
                    'kode_template' => $kode,
                    'nama_template' => $nama,
                    'file_path' => null,
                    'tipe_berkas' => $tipe,
                    'status' => 'Belum Diunggah',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('template_dokumen')
            ->whereIn('kode_template', ['bast_barang', 'sppkd', 'bast_kendaraan'])
            ->delete();
    }
};