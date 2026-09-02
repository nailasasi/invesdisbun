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
        Schema::create('aset', function (Blueprint $table) {
            $table->increments('id_aset');
            $table->unsignedInteger('id_barang')->nullable();
            $table->string('nomor_kartu_barang', 50)->unique()->nullable();
            $table->string('merk', 100)->nullable();
            $table->date('tanggal_pengadaan')->nullable();
            $table->date('tanggal_perolehan')->nullable();
            $table->date('tanggal_habis_pakai')->nullable();
            $table->decimal('nilai_perolehan', 15, 2)->nullable();
            $table->string('kondisi', 50)->nullable();
            $table->string('status_aset', 50)->nullable();
            $table->timestamps();

            $table->foreign('id_barang')->references('id_barang')->on('master_barang')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aset');
    }
};
