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
        Schema::create('usulan_rkbmd', function (Blueprint $table) {
            $table->increments('id_usulan');
            $table->unsignedInteger('id_pegawai')->nullable();
            $table->year('tahun_anggaran')->nullable();
            $table->string('jenis_usulan', 50)->nullable();
            $table->text('program_kegiatan')->nullable();
            $table->string('status_usulan', 50)->nullable();
            $table->date('tanggal_usulan')->nullable();
            $table->timestamps();

            $table->foreign('id_pegawai')->references('id_pegawai')->on('pegawai')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usulan_rkbmd');
    }
};
