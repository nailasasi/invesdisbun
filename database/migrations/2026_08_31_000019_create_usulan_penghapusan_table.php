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
        Schema::create('usulan_penghapusan', function (Blueprint $table) {
            $table->increments('id_usulan_hapus');
            $table->unsignedInteger('id_pegawai_penghapus')->nullable();
            $table->date('tanggal_usulan')->nullable();
            $table->text('alasan_penghapusan')->nullable();
            $table->string('status_usulan', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('id_aset')->nullable();
            $table->timestamps();

            $table->foreign('id_pegawai_penghapus')->references('id_pegawai')->on('pegawai')->onDelete('set null');
            $table->foreign('id_aset')->references('id_aset')->on('aset')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usulan_penghapusan');
    }
};
