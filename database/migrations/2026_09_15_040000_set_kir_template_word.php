<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('template_dokumen')
            ->where('kode_template', 'kir')
            ->update(['tipe_berkas' => 'word', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('template_dokumen')
            ->where('kode_template', 'kir')
            ->update(['tipe_berkas' => 'excel', 'updated_at' => now()]);
    }
};