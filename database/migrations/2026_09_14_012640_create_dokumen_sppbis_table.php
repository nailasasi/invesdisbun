<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_sppbi', function (Blueprint $table) {
            $table->increments('id_sppbi');
            $table->unsignedInteger('id_pegawai');
            $table->string('nomor_surat', 100);
            $table->date('tanggal_surat');
            $table->string('file_path')->nullable(); // PDF hasil scan bertanda tangan
            $table->string('status', 30)->default('aktif'); // aktif | arsip
            $table->text('catatan')->nullable();
            $table->unsignedInteger('id_user_penginput')->nullable();
            $table->timestamps();

            $table->foreign('id_pegawai')->references('id_pegawai')->on('pegawai')->onDelete('cascade');
            $table->foreign('id_user_penginput')->references('id_user')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_sppbi');
    }
};