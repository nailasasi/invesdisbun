<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('template_dokumen', 'deskripsi')) {
            Schema::table('template_dokumen', function (Blueprint $table) {
                $table->text('deskripsi')->nullable()->after('nama_template');
            });
        }

        $now = now();

        if (DB::table('template_dokumen')->where('kode_template', 'surat_izin_kendaraan')->doesntExist()) {
            DB::table('template_dokumen')->insert([
                'kode_template' => 'surat_izin_kendaraan',
                'nama_template' => 'Surat Izin / Tugas Kendaraan Dinas',
                'deskripsi' => 'Format file Word (.docx) untuk cetak surat izin pemakaian kendaraan dinas.',
                'file_path' => null,
                'tipe_berkas' => 'word',
                'status' => 'Belum Diunggah',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('template_dokumen')
                ->where('kode_template', 'surat_izin_kendaraan')
                ->update([
                    'nama_template' => 'Surat Izin / Tugas Kendaraan Dinas',
                    'deskripsi' => 'Format file Word (.docx) untuk cetak surat izin pemakaian kendaraan dinas.',
                    'tipe_berkas' => 'word',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('template_dokumen')
            ->where('kode_template', 'surat_izin_kendaraan')
            ->delete();

        if (Schema::hasColumn('template_dokumen', 'deskripsi')) {
            Schema::table('template_dokumen', function (Blueprint $table) {
                $table->dropColumn('deskripsi');
            });
        }
    }
};