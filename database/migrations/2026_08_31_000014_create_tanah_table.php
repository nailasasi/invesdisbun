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
        Schema::create('tanah', function (Blueprint $table) {
            $table->increments('id_tanah');
            $table->unsignedInteger('id_aset')->nullable();
            $table->decimal('luas_tanah', 10, 2)->nullable();
            $table->text('alamat')->nullable();
            $table->string('status_hak', 50)->nullable();
            $table->string('nomor_sertifikat', 100)->nullable();
            $table->date('tanggal_sertifikat')->nullable();
            $table->string('penggunaan', 100)->nullable();
            $table->string('kondisi', 50)->nullable();
            $table->string('nama_petugas', 100)->nullable();
            $table->string('nomor_hp_petugas', 20)->nullable();
            $table->text('google_maps')->nullable();
            $table->string('foto_tanah', 255)->nullable();
            $table->string('video_tanah', 255)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('id_aset')->references('id_aset')->on('aset')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tanah');
    }
};
