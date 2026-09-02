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
        Schema::create('izin_kendaraan', function (Blueprint $table) {
            $table->increments('id_izin');
            $table->unsignedInteger('id_kendaraan')->nullable();
            $table->unsignedInteger('id_pegawai_pengaju')->nullable();
            $table->date('tanggal_berangkat')->nullable();
            $table->time('waktu_berangkat')->nullable();
            $table->date('tanggal_kembali')->nullable();
            $table->time('waktu_kembali')->nullable();
            $table->text('tujuan')->nullable();
            $table->string('jenis_pengemudi', 50)->nullable();
            $table->unsignedInteger('id_pegawai_pengemudi')->nullable();
            $table->string('nama_pengemudi', 100)->nullable();
            $table->string('status_approval', 50)->nullable();
            $table->string('file_surat', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_kendaraan')->references('id_kendaraan')->on('kendaraan')->onDelete('cascade');
            $table->foreign('id_pegawai_pengaju')->references('id_pegawai')->on('pegawai')->onDelete('set null');
            $table->foreign('id_pegawai_pengemudi')->references('id_pegawai')->on('pegawai')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('izin_kendaraan');
    }
};
