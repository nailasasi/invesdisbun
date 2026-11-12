<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_kegiatan_rkbmd', function (Blueprint $table) {
            $table->bigIncrements('id_kegiatan');
            $table->string('bidang_urusan', 150);
            $table->string('nama_program', 200)->index('idx_mkr_nama_program');
            $table->string('nama_sub_kegiatan', 255);
            $table->timestamps();

            // Kombinasi Kolom A (Bidang Urusan), B (Program), dan D (Sub Kegiatan) harus unik
            $table->unique(
                ['bidang_urusan', 'nama_program', 'nama_sub_kegiatan'],
                'uk_mkr_bidang_prog_sub'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_kegiatan_rkbmd');
    }
};
