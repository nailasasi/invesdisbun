<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_dokumen', function (Blueprint $table) {
            $table->string('file_path')->nullable()->change();
            $table->string('tipe_berkas', 20)->nullable()->after('nama_template'); // word | excel
            $table->string('nama_file_asli')->nullable()->after('file_path');
            $table->string('status', 30)->default('Belum diunggah')->after('nama_file_asli');
        });

        // Template bawaan (definisi; berkas diunggah lewat menu Template Dokumen)
        $now = now();
        DB::table('template_dokumen')->insert([
            ['kode_template' => 'sppbi', 'nama_template' => 'Template SPPBI', 'file_path' => null, 'tipe_berkas' => 'word', 'status' => 'Belum diunggah', 'created_at' => $now, 'updated_at' => $now],
            ['kode_template' => 'sk_penghapusan', 'nama_template' => 'SK Penghapusan Aset', 'file_path' => null, 'tipe_berkas' => 'word', 'status' => 'Belum diunggah', 'created_at' => $now, 'updated_at' => $now],
            ['kode_template' => 'rekap_aset', 'nama_template' => 'Rekap Aset Barang', 'file_path' => null, 'tipe_berkas' => 'excel', 'status' => 'Belum diunggah', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('template_dokumen', function (Blueprint $table) {
            $table->dropColumn(['tipe_berkas', 'nama_file_asli', 'status']);
        });
    }
};