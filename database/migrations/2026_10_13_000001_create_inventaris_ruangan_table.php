<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventaris lokal ruangan: barang non-aset resmi (dispenser, kulkas, dll).
     * Terpisah total dari `aset` dan `master_barang`.
     */
    public function up(): void
    {
        Schema::create('inventaris_ruangan', function (Blueprint $table) {
            $table->increments('id_inventaris');
            $table->unsignedInteger('id_ruangan');
            $table->string('kode_inventaris', 50)->unique();
            $table->string('nama_barang', 150);
            $table->unsignedInteger('jumlah')->default(1);
            $table->string('satuan', 30)->nullable();
            $table->enum('kondisi', ['Baik', 'Rusak Ringan', 'Rusak Berat'])->nullable()->default('Baik');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('id_ruangan')->references('id_ruangan')->on('ruangan')->onDelete('cascade');
            $table->index('id_ruangan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaris_ruangan');
    }
};
