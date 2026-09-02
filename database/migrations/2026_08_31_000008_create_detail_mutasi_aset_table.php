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
        Schema::create('detail_mutasi_aset', function (Blueprint $table) {
            $table->increments('id_detail');
            $table->unsignedInteger('id_mutasi')->nullable();
            $table->unsignedInteger('id_aset')->nullable();
            $table->unsignedInteger('pegawai_lama')->nullable();
            $table->unsignedInteger('pegawai_baru')->nullable();
            $table->unsignedInteger('ruangan_lama')->nullable();
            $table->unsignedInteger('ruangan_baru')->nullable();
            $table->timestamps();

            $table->foreign('id_mutasi')->references('id_mutasi')->on('mutasi_aset')->onDelete('cascade');
            $table->foreign('id_aset')->references('id_aset')->on('aset')->onDelete('cascade');
            $table->foreign('pegawai_lama')->references('id_pegawai')->on('pegawai')->onDelete('set null');
            $table->foreign('pegawai_baru')->references('id_pegawai')->on('pegawai')->onDelete('set null');
            $table->foreign('ruangan_lama')->references('id_ruangan')->on('ruangan')->onDelete('set null');
            $table->foreign('ruangan_baru')->references('id_ruangan')->on('ruangan')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_mutasi_aset');
    }
};
