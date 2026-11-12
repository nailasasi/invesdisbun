<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan jenis dokumen pada arsip RKBMD (Inspektorat / BPKAD).
 *
 * Kolom nullable agar arsip lama yang sudah ada tetap valid tanpa backfill
 * paksa. Aplikasi memperlakukan nilai kosong sebagai "belum ditentukan".
 *
 * BELUM DIJALANKAN. Jalankan manual dengan `php artisan migrate` setelah
 * disetujui.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('arsip_rkbmd', 'jenis_dokumen')) {
            return;
        }

        Schema::table('arsip_rkbmd', function (Blueprint $table) {
            $table->string('jenis_dokumen', 20)
                ->nullable()
                ->after('nama_dokumen')
                ->comment('Inspektorat | BPKAD');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('arsip_rkbmd', 'jenis_dokumen')) {
            return;
        }

        Schema::table('arsip_rkbmd', function (Blueprint $table) {
            $table->dropColumn('jenis_dokumen');
        });
    }
};
