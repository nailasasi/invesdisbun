<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('template_dokumen')->where('kode_template', 'kir')->exists();

        if (!$exists) {
            $now = now();
            DB::table('template_dokumen')->insert([
                'kode_template' => 'kir',
                'nama_template' => 'Template KIR (Kartu Inventaris Ruangan)',
                'file_path' => null,
                'tipe_berkas' => 'excel',
                'status' => 'Belum diunggah',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('template_dokumen')->where('kode_template', 'kir')->delete();
    }
};