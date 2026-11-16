<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data asli kedua template dokumen sebelum dihapus,
     * disimpan secara presisi agar migration rollback dapat memulihkan data dengan sempurna.
     */
    private const DATA_LAMA = [
        [
            'id_template' => 2,
            'kode_template' => 'sk_penghapusan',
            'nama_template' => 'SK Penghapusan Aset',
            'tipe_berkas' => 'word',
            'file_path' => null,
            'nama_file_asli' => null,
            'status' => 'Belum Diunggah',
            'deskripsi' => null,
            'created_at' => '2026-09-14 03:02:59',
            'updated_at' => '2026-11-11 13:37:02',
        ],
        [
            'id_template' => 3,
            'kode_template' => 'rekap_aset',
            'nama_template' => 'Rekap Aset Barang',
            'tipe_berkas' => 'excel',
            'file_path' => null,
            'nama_file_asli' => null,
            'status' => 'Belum Diunggah',
            'deskripsi' => null,
            'created_at' => '2026-09-14 03:02:59',
            'updated_at' => '2026-09-14 03:07:40',
        ],
    ];

    /**
     * Run the migrations.
     * Menghapus record sk_penghapusan dan rekap_aset yang tidak pernah digunakan.
     */
    public function up(): void
    {
        DB::table('template_dokumen')
            ->whereIn('kode_template', ['sk_penghapusan', 'rekap_aset'])
            ->delete();
    }

    /**
     * Reverse the migrations.
     * Memulihkan kembali kedua record template dokumen dengan seluruh atribut aslinya.
     */
    public function down(): void
    {
        foreach (self::DATA_LAMA as $row) {
            $exists = DB::table('template_dokumen')
                ->where('kode_template', $row['kode_template'])
                ->exists();

            if (! $exists) {
                DB::table('template_dokumen')->insert($row);
            }
        }
    }
};
