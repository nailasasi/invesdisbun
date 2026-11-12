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
        if (Schema::hasTable('riwayat_penghapusan')) {
            return;
        }

        Schema::create('riwayat_penghapusan', function (Blueprint $table) {
            $table->increments('id_riwayat');
            $table->unsignedInteger('id_usulan_hapus')->nullable();
            $table->unsignedInteger('id_aset')->nullable();
            $table->unsignedInteger('id_user')->nullable();
            $table->string('no_sk', 100)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->string('nama_pelaksana', 100);
            $table->dateTime('waktu_penghapusan');
            $table->string('alasan_penghapusan', 255);
            $table->text('keterangan')->nullable();
            $table->string('foto_bukti');
            $table->json('snapshot_aset');
            $table->timestamps();

            $table->foreign('id_usulan_hapus')->references('id_usulan_hapus')->on('usulan_penghapusan')->onDelete('set null');
            $table->foreign('id_aset')->references('id_aset')->on('aset')->onDelete('set null');
            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('set null');
            $table->index('waktu_penghapusan');
            $table->index('tanggal_sk');
            $table->index('id_aset');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_penghapusan');
    }
};
