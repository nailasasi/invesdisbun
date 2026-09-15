<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Standarisasi status aset menjadi huruf kecil mengikuti SOP BMD:
     * 'aktif', 'diusulkan_hapus', 'dihapuskan'.
     */
    public function up(): void
    {
        DB::table('aset')
            ->where('status_aset', 'Aktif')
            ->update(['status_aset' => 'aktif']);

        DB::table('aset')
            ->where('status_aset', 'Non-aktif')
            ->update(['status_aset' => 'nonaktif']);
    }

    public function down(): void
    {
        DB::table('aset')
            ->where('status_aset', 'aktif')
            ->update(['status_aset' => 'Aktif']);
    }
};